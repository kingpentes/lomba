import math
from datetime import datetime, timedelta
import pandas as pd
import numpy as np
from airflow.decorators import dag, task
from airflow.providers.postgres.hooks.postgres import PostgresHook
from airflow.exceptions import AirflowSkipException

default_args = {
    'owner': 'geoguard',
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=1),
}

@dag(
    dag_id='geotech_prediction_dag',
    default_args=default_args,
    schedule='*/1 * * * *',
    start_date=datetime(2023, 1, 1),
    catchup=False,
    tags=['geoguard', 'prediction'],
)
def geotech_prediction_dag():

    @task()
    def extract_telemetry() -> dict:
        pg_hook = PostgresHook(postgres_conn_id='postgres_geoguard')
        
        # 1. Ambil ID Node
        node_record = pg_hook.get_first("SELECT id FROM monitoring_nodes WHERE node_code = 'INC_HW_01'")
        if not node_record:
            raise AirflowSkipException("Node INC_HW_01 tidak ditemukan.")
        node_id = node_record[0]
        
        # 2. Ambil 30 data terakhir
        sql = """
            SELECT recorded_at, pitch, roll 
            FROM telemetry_logs 
            WHERE node_id = %s 
            ORDER BY recorded_at DESC 
            LIMIT 30
        """
        records = pg_hook.get_records(sql, parameters=(node_id,))
        if len(records) < 5:
            raise AirflowSkipException(f"Data kurang dari 5 baris (hanya {len(records)}). Skip kalkulasi.")
            
        return {'node_id': node_id, 'records': records}

    @task()
    def calculate_fukuzono(data: dict) -> dict:
        records = data['records']
        node_id = data['node_id']
        
        # Parse ke DataFrame untuk manipulasi mudah (ascending time)
        df = pd.DataFrame(records, columns=['recorded_at', 'pitch', 'roll'])
        df['recorded_at'] = pd.to_datetime(df['recorded_at'])
        df = df.sort_values('recorded_at').reset_index(drop=True)
        
        # 3. Hitung Resultan Sudut
        df['resultant_angle'] = np.sqrt(df['pitch']**2 + df['roll']**2)
        
        # 4. Hitung Kecepatan (Velocity) menggunakan Linear Regression (Trend)
        # Menghitung selisih tiap 2 detik akan menghasilkan noise yang sangat tinggi (selalu > 0.20 deg/min)
        # Jadi kita cari slope (kemiringan garis tren) dari 30 data terakhir.
        df['time_elapsed_min'] = (df['recorded_at'] - df['recorded_at'].iloc[0]).dt.total_seconds() / 60.0
        
        # Mencegah error polyfit jika waktu tidak bergerak (kurang dari 1 detik total span)
        if df['time_elapsed_min'].max() < 0.016: 
            avg_velocity = 0.0
        else:
            # np.polyfit mengembalikan [slope, intercept]
            slope, _ = np.polyfit(df['time_elapsed_min'], df['resultant_angle'], 1)
            avg_velocity = abs(slope)
        
        # Cegah inf atau NaN agar tidak merusak Airflow XCom JSON
        if pd.isna(avg_velocity) or np.isinf(avg_velocity):
            avg_velocity = 0.0
        # 5. Aturan Inverse Velocity
        risk_level = 'STABLE'
        inv_velocity = 999.0
        estimated_collapse_time = None
        
        if avg_velocity < 0.05:
            risk_level = 'STABLE'
            inv_velocity = 999.0
        elif avg_velocity < 0.20:
            risk_level = 'WARNING'
            if avg_velocity > 0:
                inv_velocity = 1.0 / avg_velocity
        else:
            risk_level = 'CRITICAL'
            if avg_velocity > 0:
                inv_velocity = 1.0 / avg_velocity
            # Proyeksi linier waktu keruntuhan
            time_left_minutes = inv_velocity * 2.5
            estimated_collapse_time = df['recorded_at'].max() + timedelta(minutes=time_left_minutes)
            
        return {
            'node_id': node_id,
            'angular_velocity': float(avg_velocity),
            'inv_velocity': float(inv_velocity),
            'risk_level': risk_level,
            'estimated_collapse_time': estimated_collapse_time.isoformat() if estimated_collapse_time else None,
            'insar_displacement_rate': 0.0 # Placeholder
        }

    @task()
    def load_prediction(result: dict):
        pg_hook = PostgresHook(postgres_conn_id='postgres_geoguard')
        
        # 6. Simpan baris baru ke slope_risk_predictions
        insert_sql = """
            INSERT INTO slope_risk_predictions 
            (node_id, angular_velocity, inv_velocity, risk_level, estimated_collapse_time, insar_displacement_rate, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
        """
        now = datetime.now()
        pg_hook.run(insert_sql, parameters=(
            result['node_id'],
            result['angular_velocity'],
            result['inv_velocity'],
            result['risk_level'],
            result['estimated_collapse_time'],
            result['insar_displacement_rate'],
            now,
            now
        ))
        
        # 7. Update status monitoring_nodes
        update_sql = "UPDATE monitoring_nodes SET status = %s, updated_at = %s WHERE id = %s"
        pg_hook.run(update_sql, parameters=(result['risk_level'], now, result['node_id']))
        
        print(f"Prediction saved for node {result['node_id']} | Status: {result['risk_level']} | 1/v: {result['inv_velocity']}")

    # DAG Dependency Flow
    data = extract_telemetry()
    prediction = calculate_fukuzono(data)
    load_prediction(prediction)

dag = geotech_prediction_dag()

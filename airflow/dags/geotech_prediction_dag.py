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
        
        # 3. Terapkan Exponential Moving Average (EMA) untuk menghaluskan getaran (noise)
        df['resultant_angle'] = np.sqrt(df['pitch']**2 + df['roll']**2)
        df['smooth_angle'] = df['resultant_angle'].ewm(span=5, adjust=False).mean()
        df['time_elapsed_min'] = (df['recorded_at'] - df['recorded_at'].iloc[0]).dt.total_seconds() / 60.0
        
        # 4. Hitung Kecepatan Tren Keseluruhan (Linear Regression Slope)
        # Menghitung selisih 2 detik per baris sangat rentan noise MPU6050.
        # Slope tren memberikan kecepatan sudut sebenarnya (°/menit) tanpa terganggu jitter mikro.
        if df['time_elapsed_min'].max() > 0.01:
            slope, _ = np.polyfit(df['time_elapsed_min'], df['smooth_angle'], 1)
            avg_velocity = max(0.0, float(slope))
        else:
            avg_velocity = 0.0

        risk_level = 'STABLE'
        inv_velocity = 999.0 if avg_velocity <= 0.001 else (1.0 / avg_velocity)
        estimated_collapse_time = None

        if avg_velocity < 0.05:
            risk_level = 'STABLE'
        elif avg_velocity < 0.20:
            risk_level = 'WARNING'
        else:
            risk_level = 'CRITICAL'
            
        # 5. FUKUZONO SEJATI: Regresi Linear pada grafik 1/V terhadap Waktu jika lereng bergerak
        df['velocity'] = df['smooth_angle'].diff() / df['time_elapsed_min'].diff()
        df_valid = df.dropna().copy()
        df_valid = df_valid[df_valid['velocity'] > 0.001].copy()

        if len(df_valid) >= 5 and risk_level in ['WARNING', 'CRITICAL']:
            df_valid['inv_velocity'] = 1.0 / df_valid['velocity']
            inv_velocity = df_valid['inv_velocity'].iloc[-1]
            
            # Rumus Garis: Y = mX + c (dimana Y = 1/V, X = time_elapsed)
            m, c = np.polyfit(df_valid['time_elapsed_min'], df_valid['inv_velocity'], 1)
            
            # Syarat Fukuzono: Garis 1/V harus menukik turun (slope negatif)
            if m < -0.001:
                time_fail_min = -c / m
                time_left = time_fail_min - df_valid['time_elapsed_min'].iloc[-1]
                
                # Cegah prediksi tidak masuk akal (misal > 30 hari ke depan)
                if 0 < time_left < (60 * 24 * 30):
                    estimated_collapse_time = df['recorded_at'].iloc[-1] + timedelta(minutes=time_left)
            
        return {
            'node_id': node_id,
            'angular_velocity': float(avg_velocity),
            'inv_velocity': float(inv_velocity),
            'risk_level': risk_level,
            'estimated_collapse_time': estimated_collapse_time.isoformat() if estimated_collapse_time else None,
            'insar_displacement_rate': 0.0, # Placeholder
            'latest_record_time': df['recorded_at'].iloc[-1].to_pydatetime()
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
        
        # 7. Status monitoring_nodes dipimpin real-time oleh ESP32
        
        # 8. Send Telegram Alert dengan Informasi Lengkap Lokasi & Prediksi
        # Hanya kirim jika data segar (< 5 menit) dan status WARNING/CRITICAL atau ada TTF keruntuhan
        latest_time = result.get('latest_record_time')
        is_fresh_data = latest_time and (now - latest_time).total_seconds() < 300

        import requests
        if is_fresh_data and (result['risk_level'] in ['WARNING', 'CRITICAL'] or result['estimated_collapse_time']):
            token = '8889805869:AAGaKNoz1wuh3tHnXtmvgSpxMTRbCzFuYE4'
            chat_id = '-1003849589445'
            
            node_sql = "SELECT node_code, name, latitude, longitude, elevation FROM monitoring_nodes WHERE id = %s"
            node_row = pg_hook.get_first(node_sql, parameters=(result['node_id'],))
            node_code = node_row[0]
            node_name = node_row[1] or ""
            lat = node_row[2]
            lon = node_row[3]
            elev = node_row[4]
            
            emoji = "🚨" if result['risk_level'] == 'CRITICAL' else "⚠️"
            msg = f"{emoji} <b>AI FUKUZONO PREDICTION ALERT: {result['risk_level']}</b>\n\n"
            msg += f"📍 <b>Node:</b> {node_code}" + (f" ({node_name})\n" if node_name else "\n")
            if lat and lon:
                msg += f"🗺️ <b>Koordinat:</b> {lat}, {lon}\n"
                if elev:
                    msg += f"⛰️ <b>Elevasi:</b> {elev} m\n"
                msg += f"🌐 <b>Google Maps:</b> https://www.google.com/maps?q={lat},{lon}\n"
            msg += f"⏱️ <b>Waktu:</b> {now.strftime('%Y-%m-%d %H:%M:%S')}\n\n"
            
            msg += f"📊 <b>HASIL ANALISIS PREDIKSI (FUKUZONO):</b>\n"
            msg += f"• <b>Status Risiko:</b> {result['risk_level']}\n"
            msg += f"• <b>Kecepatan Sudut:</b> {result['angular_velocity']:.4f} °/menit\n"
            msg += f"• <b>Inverse Velocity (1/v):</b> {result['inv_velocity']:.3f}\n"
            
            if result['estimated_collapse_time']:
                ttf_str = result['estimated_collapse_time'].replace('T', ' ')
                msg += f"\n‼️ <b>PREDIKSI LONGSOR (TTF):</b> {ttf_str}\n"
                msg += "🚨 <b>TINDAKAN: SEGERA LAKUKAN EVAKUASI RADIUS 200m!</b>"
            elif result['risk_level'] == 'CRITICAL':
                msg += "\n🚨 <b>PERHATIAN:</b> Terjadi pergerakan lereng sangat cepat! Segera lakukan evakuasi dan inspeksi lokasi."
            else:
                msg += "\n🚧 <b>PERHATIAN:</b> Terdeteksi pergerakan lereng. Tingkatkan kewaspadaan dan pantau telemetri."
            
            try:
                requests.post(f"https://api.telegram.org/bot{token}/sendMessage", data={
                    'chat_id': chat_id,
                    'text': msg,
                    'parse_mode': 'HTML'
                })
            except Exception as e:
                print(f"Failed to send telegram: {e}")

        print(f"Prediction saved for node {result['node_id']} | Status: {result['risk_level']} | 1/v: {result['inv_velocity']}")

    # DAG Dependency Flow
    data = extract_telemetry()
    prediction = calculate_fukuzono(data)
    load_prediction(prediction)

dag = geotech_prediction_dag()

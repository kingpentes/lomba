import math
import logging
from datetime import datetime, timedelta
import pandas as pd
import numpy as np
from airflow.decorators import dag, task
from airflow.models import Variable
from airflow.providers.postgres.hooks.postgres import PostgresHook
from airflow.exceptions import AirflowSkipException

default_args = {
    'owner': 'geoguard',
    'depends_on_past': False,
    'retries': 2,
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
        
        # 1. C.3 FIX: Gunakan Airflow Variable agar dinamis dan tidak hardcoded literal
        target_node = Variable.get("geoguard_active_node", default_var="INC_HW_01")
        node_record = pg_hook.get_first(
            "SELECT id FROM monitoring_nodes WHERE node_code = %s", 
            parameters=(target_node,)
        )
        if not node_record:
            raise AirflowSkipException(f"Node {target_node} tidak ditemukan.")
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
            
        # C.7 FIX: Ambil nilai InSAR terakhir yang valid agar tidak menimpa menjadi 0.0
        last_insar_row = pg_hook.get_first(
            """SELECT insar_displacement_rate FROM slope_risk_predictions 
               WHERE node_id = %s AND insar_displacement_rate IS NOT NULL AND insar_displacement_rate != 0 
               ORDER BY created_at DESC LIMIT 1""",
            parameters=(node_id,)
        )
        latest_insar = float(last_insar_row[0]) if last_insar_row and last_insar_row[0] is not None else None

        return {'node_id': node_id, 'records': records, 'latest_insar_rate': latest_insar}

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
        else:
            # Pergerakan abnormal terdeteksi — mulai WARNING dulu
            risk_level = 'WARNING'
            
        # 5. FUKUZONO SEJATI: Regresi Linear pada grafik 1/V terhadap Waktu jika lereng bergerak
        # Buang duplikat timestamp agar diff waktu tidak pernah 0 (mencegah infinity velocity -> 1/v = 0)
        df_unique = df.drop_duplicates(subset=['time_elapsed_min']).copy()
        df_unique['velocity'] = df_unique['smooth_angle'].diff() / df_unique['time_elapsed_min'].diff()
        df_valid = df_unique.dropna().copy()
        df_valid = df_valid[df_valid['velocity'] > 0.001].copy()

        r_squared = None
        if len(df_valid) >= 5 and risk_level == 'WARNING':
            df_valid['inv_velocity'] = 1.0 / df_valid['velocity']
            inv_velocity = df_valid['inv_velocity'].iloc[-1]
            
            # Rumus Garis: Y = mX + c (dimana Y = 1/V, X = time_elapsed)
            m, c = np.polyfit(df_valid['time_elapsed_min'], df_valid['inv_velocity'], 1)
            
            # Hitung R^2 pada regresi 1/V vs Time (Fukuzono fit)
            y_actual = df_valid['inv_velocity'].values
            y_pred = m * df_valid['time_elapsed_min'].values + c
            ss_res = np.sum((y_actual - y_pred) ** 2)
            ss_tot = np.sum((y_actual - np.mean(y_actual)) ** 2)
            if ss_tot > 1e-6:
                r_squared = max(0.0, min(1.0, float(1.0 - (ss_res / ss_tot))))
            
            # Syarat Fukuzono: Garis 1/V harus menukik turun (slope negatif)
            if m < -0.001:
                # time_fail_min adalah X-intercept absolut dari titik nol (record pertama)
                time_fail_min = -c / m
                time_left_from_now = time_fail_min - df['time_elapsed_min'].iloc[-1]
                
                # Cegah prediksi tidak masuk akal (misal > 30 hari ke depan atau sudah runtuh)
                if 0 < time_left_from_now < (60 * 24 * 30):
                    # TTF Dihitung persis dari titik nol ditambah waktu menuju keruntuhan
                    estimated_collapse_time = df['recorded_at'].iloc[0] + timedelta(minutes=time_fail_min)
                    # TTF berhasil dihitung → eskalasi ke CRITICAL
                    risk_level = 'CRITICAL'
            

        return {
            'node_id': node_id,
            'angular_velocity': float(avg_velocity),
            'inv_velocity': float(inv_velocity),
            'risk_level': risk_level,
            'estimated_collapse_time': estimated_collapse_time.isoformat() if estimated_collapse_time else None,
            'fukuzono_r2': r_squared,
            'insar_displacement_rate': data.get('latest_insar_rate'),
            'latest_record_time': df['recorded_at'].iloc[-1].isoformat()
        }

    # ═══════════════════════════════════════════════════════════════════════
    # TASK 3: Simpan hasil prediksi ke PostgreSQL (Idempoten)
    # ═══════════════════════════════════════════════════════════════════════
    @task()
    def save_prediction_to_db(result: dict) -> dict:
        pg_hook = PostgresHook(postgres_conn_id='postgres_geoguard')
        
        insert_sql = """
            INSERT INTO slope_risk_predictions 
            (node_id, angular_velocity, inv_velocity, risk_level, estimated_collapse_time, insar_displacement_rate, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            RETURNING id
        """
        now = datetime.now()
        record_id = pg_hook.get_first(insert_sql, parameters=(
            result['node_id'],
            result['angular_velocity'],
            result['inv_velocity'],
            result['risk_level'],
            result['estimated_collapse_time'],
            result['insar_displacement_rate'],
            now,
            now
        ))[0]
        
        print(f"✅ Prediction saved: id={record_id}, node_id={result['node_id']}, risk={result['risk_level']}, 1/v={result['inv_velocity']:.3f}")
        result['prediction_id'] = record_id
        return result

    # ═══════════════════════════════════════════════════════════════════════
    # TASK 4: Kirim Alert Telegram (Terpisah agar retry tidak menduplikasi baris DB)
    # ═══════════════════════════════════════════════════════════════════════
    @task()
    def send_telegram_alert(result: dict):
        import html
        import requests

        now = datetime.now()
        latest_time_str = result.get('latest_record_time')
        latest_time = datetime.fromisoformat(latest_time_str) if latest_time_str else None
        is_fresh_data = latest_time and (now - latest_time).total_seconds() < 300

        if not (is_fresh_data and (result['risk_level'] in ['WARNING', 'CRITICAL'] or result.get('estimated_collapse_time'))):
            print("ℹ️ Tidak ada kondisi darurat (data normal/stale). Alert Telegram di-skip.")
            return

        # --- LOGIKA COOLDOWN SPAM ---
        # Baca waktu terakhir kita ngirim chat Fukuzono
        last_alert_str = Variable.get("last_fukuzono_alert_time", default_var="")
        if last_alert_str:
            last_alert_time = datetime.fromisoformat(last_alert_str)
            # Set cooldown 15 menit (900 detik)
            if (now - last_alert_time).total_seconds() < 900:
                print("ℹ️ Masih dalam masa cooldown (15 menit). Alert Telegram di-skip untuk mencegah SPAM.")
                return
        
        # Simpan waktu pengiriman sekarang agar tidak spam ke depannya
        Variable.set("last_fukuzono_alert_time", now.isoformat())
        # ----------------------------

        token = Variable.get("geoguard_telegram_token", default_var="8889805869:AAGaKNoz1wuh3tHnXtmvgSpxMTRbCzFuYE4")
        chat_id = Variable.get("geoguard_telegram_chat_id", default_var="-1003849589445")
        app_url = Variable.get("geoguard_app_url", default_var="https://winatra.indonesiacentral.cloudapp.azure.com").rstrip('/')

        pg_hook = PostgresHook(postgres_conn_id='postgres_geoguard')
        node_sql = "SELECT node_code, name, latitude, longitude, elevation FROM monitoring_nodes WHERE id = %s"
        node_row = pg_hook.get_first(node_sql, parameters=(result['node_id'],))
        
        node_code = html.escape(str(node_row[0])) if node_row else "UNKNOWN"
        node_name = html.escape(str(node_row[1] or "")) if node_row else ""
        lat = node_row[2] if node_row else None
        lon = node_row[3] if node_row else None
        elev = node_row[4] if node_row else None
        
        # Ambil insiden terbaru untuk foto snapshot dan visual AI crack confidence
        incident_sql = """
            SELECT snapshot_path, ai_confidence, trigger_type FROM incidents 
            WHERE node_id = %s 
            ORDER BY triggered_at DESC LIMIT 1
        """
        inc_row = pg_hook.get_first(incident_sql, parameters=(result['node_id'],))
        snapshot_path = inc_row[0] if inc_row else None
        ai_visual_conf = float(inc_row[1]) if inc_row and inc_row[1] is not None else None
        inc_trigger = inc_row[2] if inc_row else None

        emoji = "🚨" if result['risk_level'] == 'CRITICAL' else "⚠️"
        msg = f"{emoji} <b>AI FUKUZONO PREDICTION ALERT: {result['risk_level']}</b>\n\n"
        msg += f"📍 <b>Node:</b> {node_code}" + (f" ({node_name})\n" if node_name else "\n")
        if lat and lon:
            msg += f"🗺️ <b>Koordinat:</b> {lat}, {lon}\n"
            if elev:
                msg += f"⛰️ <b>Elevasi:</b> {elev} m\n"
            msg += f"🌐 <b>Google Maps:</b> https://www.google.com/maps?q={lat},{lon}\n"
        msg += f"⏱️ <b>Waktu:</b> {(now + timedelta(hours=8)).strftime('%Y-%m-%d %H:%M:%S')} WITA\n\n"
        
        msg += f"📊 <b>HASIL ANALISIS PREDIKSI (FUKUZONO):</b>\n"
        msg += f"• <b>Status Risiko:</b> {result['risk_level']}\n"
        msg += f"• <b>Kecepatan Sudut:</b> {result['angular_velocity']:.4f} °/menit\n"
        msg += f"• <b>Inverse Velocity (1/v):</b> {result['inv_velocity']:.3f}\n"

        # Tampilkan Model Confidence (R^2 Fukuzono Fit)
        r2_val = result.get('fukuzono_r2')
        if r2_val is not None:
            msg += f"• <b>Model Fit (R²):</b> {r2_val * 100:.1f}%\n"
        else:
            msg += f"• <b>Model Fit (R²):</b> Estimasi Linear (<5 titik)\n"

        # Tampilkan AI Visual Crack Confidence jika insiden terdeteksi kamera
        if ai_visual_conf is not None and inc_trigger == 'CRACK_DETECT':
            msg += f"• <b>AI Visual Crack Confidence:</b> {ai_visual_conf * 100:.1f}%\n"
        
        if result.get('estimated_collapse_time'):
            try:
                collapse_dt = datetime.fromisoformat(str(result['estimated_collapse_time']).replace('T', ' '))
                ttf_str     = collapse_dt.strftime('%d %B %Y, %H:%M WIB')
                delta       = collapse_dt - now
                total_mins  = int(delta.total_seconds() / 60)
                if total_mins < 0:
                    sisa_str = "⚠️ Waktu prediksi telah terlewati — kondisi masih kritis"
                elif total_mins < 60:
                    sisa_str = f"± {total_mins} menit lagi"
                elif total_mins < 1440:
                    sisa_str = f"± {total_mins // 60} jam {total_mins % 60} menit lagi"
                else:
                    sisa_str = f"± {total_mins // 1440} hari {(total_mins % 1440) // 60} jam lagi"
            except Exception:
                ttf_str  = str(result['estimated_collapse_time']).replace('T', ' ')
                sisa_str = "tidak dapat dihitung"

            msg += f"\n🕐 <b>PERKIRAAN WAKTU LONGSOR:</b>\n"
            msg += f"   📅 <b>{ttf_str}</b>  ({sisa_str})\n\n"
            msg += "🚨 <b>TINDAKAN DARURAT:</b> SEGERA EVAKUASI RADIUS 200m!\n"
            msg += "   Hentikan seluruh operasi tambang hingga ada clearance dari tim geoteknik."
        elif result['risk_level'] == 'CRITICAL':
            msg += "\n🚨 <b>PERHATIAN:</b> Terjadi pergerakan lereng sangat cepat!\n"
            msg += "   Segera lakukan evakuasi dan inspeksi lokasi. Harap berhati-hati untuk sementara waktu."
        else:
            msg += "\n🚧 <b>PERHATIAN:</b> Terdeteksi pergerakan lereng tahap awal.\n"
            msg += "   Tingkatkan kewaspadaan dan pantau telemetri secara berkala. Harap berhati-hati untuk sementara waktu."

        sent_photo = False
        if snapshot_path:
            try:
                photo_url = f"{app_url}/storage/{snapshot_path}"
                res = requests.post(f"https://api.telegram.org/bot{token}/sendPhoto", data={
                    'chat_id': chat_id,
                    'photo': photo_url,
                    'caption': msg,
                    'parse_mode': 'HTML'
                }, timeout=10)
                if res.status_code == 200:
                    sent_photo = True
                else:
                    logging.warning(f"Telegram sendPhoto failed ({res.status_code}): {res.text}")
            except Exception as pe:
                logging.warning(f"Error sending photo alert: {pe}")

        if not sent_photo:
            try:
                res = requests.post(f"https://api.telegram.org/bot{token}/sendMessage", data={
                    'chat_id': chat_id,
                    'text': msg,
                    'parse_mode': 'HTML'
                }, timeout=10)
                res.raise_for_status()
            except Exception as e:
                logging.error(f"Failed to send telegram alert: {e}")
                raise

    # ═══════════════════════════════════════════════════════════════════════
    # DAG FLOW: Extract -> Calculate -> Save DB -> Send Alert
    # ═══════════════════════════════════════════════════════════════════════
    data = extract_telemetry()
    prediction = calculate_fukuzono(data)
    saved_prediction = save_prediction_to_db(prediction)
    send_telegram_alert(saved_prediction)

dag = geotech_prediction_dag()

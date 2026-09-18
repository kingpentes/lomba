import json
import time
import requests
import cv2
import paho.mqtt.client as mqtt
from datetime import datetime

# ==========================================
# CONFIGURATION
# ==========================================
MQTT_BROKER = "127.0.0.1" # Mosquitto berjalan lokal di Raspberry Pi
MQTT_PORT = 1883
MQTT_TOPIC = "mine/pit1/inclinometer"

LARAVEL_API_URL = "http://10.191.154.58:8000/api/v1"
WEBCAM_INDEX = 0 # /dev/video0

# Cooldown incident (cegah spam jepret kamera berkali-kali)
last_incident_time = 0
INCIDENT_COOLDOWN = 10 # 10 detik

import threading

def capture_snapshot(filename="incident_snapshot.jpg"):
    print(f"[{datetime.now().time()}] Menghidupkan webcam untuk snapshot...")
    cap = cv2.VideoCapture(WEBCAM_INDEX)
    
    # Mempercepat pengambilan agar latensi tidak hancur
    # Cukup 2 frame awal saja untuk inisialisasi exposure
    for _ in range(2):
        cap.read()
        
    ret, frame = cap.read()
    if ret:
        cv2.imwrite(filename, frame)
        print(f"Snapshot berhasil disimpan: {filename}")
        cap.release()
        return filename
    else:
        print("Gagal mengambil gambar dari Webcam!")
        cap.release()
        return None

def task_send_incident(data):
    print("\n--- MENGIRIM INCIDENT KE LARAVEL ---")
    payload = {
        "device_id": data.get("node_code"),
        "angle_x": data.get("pitch", 0),
        "angle_y": data.get("roll", 0),
        "status": "BAHAYA"
    }
    
    # Jepret foto
    img_path = capture_snapshot()
    files = {}
    if img_path:
        files['photo'] = open(img_path, 'rb')
        
    try:
        url = f"{LARAVEL_API_URL}/incidents"
        res = requests.post(url, data=payload, files=files if files else None, timeout=5)
        print(f"[INCIDENT] Response ({res.status_code}): {res.text}")
    except Exception as e:
        print(f"[INCIDENT ERROR] Gagal mengirim insiden: {e}")
    finally:
        if 'photo' in files:
            files['photo'].close()

def send_incident(data):
    # Jalankan di thread terpisah agar MQTT tidak terblokir
    threading.Thread(target=task_send_incident, args=(data,)).start()

def task_send_telemetry(data):
    try:
        url = f"{LARAVEL_API_URL}/telemetry"
        res = requests.post(url, json=data, timeout=5)
        print(f"Telemetry Diteruskan -> Laravel | Status: {res.status_code}")
    except Exception as e:
        print(f"Gagal meneruskan Telemetry: {e}")

def send_telemetry(data):
    # Jalankan di thread terpisah
    threading.Thread(target=task_send_telemetry, args=(data,)).start()


def on_connect(client, userdata, flags, rc):
    if rc == 0:
        print("Terhubung ke MQTT Broker!")
        client.subscribe(MQTT_TOPIC)
        print(f"Subscribed ke topik: {MQTT_TOPIC}")
    else:
        print(f"Gagal terhubung, return code {rc}")

def on_message(client, userdata, msg):
    global last_incident_time
    
    try:
        payload = msg.payload.decode("utf-8")
        data = json.loads(payload)
        
        status = data.get("ai_classification", "SAFE")
        print(f"\n[MQTT] Pesan masuk dari {data.get('node_code')} | Status: {status}")
        
        # Selalu teruskan telemetri
        send_telemetry(data)
        
        # Cek apakah BAHAYA dan belum cooldown
        if status == "BAHAYA":
            current_time = time.time()
            if current_time - last_incident_time >= INCIDENT_COOLDOWN:
                last_incident_time = current_time
                send_incident(data)
            else:
                print("Status BAHAYA terdeteksi, namun masih dalam masa cooldown kamera.")
                
    except Exception as e:
        print(f"Error memproses pesan MQTT: {e}")

if __name__ == "__main__":
    print("=== GeoGuard Raspberry Pi Edge Gateway ===")
    print("Mempersiapkan koneksi ke MQTT...")
    
    client = mqtt.Client()
    client.on_connect = on_connect
    client.on_message = on_message
    
    try:
        client.connect(MQTT_BROKER, MQTT_PORT, 60)
        client.loop_forever()
    except KeyboardInterrupt:
        print("\nGateway dimatikan.")
    except Exception as e:
        print(f"Error: {e}")

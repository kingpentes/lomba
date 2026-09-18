import time
import random
import requests
from datetime import datetime
import math
import os

TELEMETRY_API = "http://127.0.0.1:8000/api/v1/telemetry"
INCIDENT_API = "http://127.0.0.1:8000/api/v1/incidents"
# Samakan persis dengan node yang ada di database seeder
NODE_CODE = "INC_HW_01"

def generate_telemetry(t_cycle, status):
    # Kurva eksponensial Fukuzono: t=0 s.d t=60
    base_pitch = 0.1 * math.exp(0.0883 * t_cycle) if t_cycle < 60 else 20.0
    
    pitch = base_pitch + random.gauss(0, 0.05)
    roll = -0.2 + random.gauss(0, 0.05)

    return {
        "node_code": NODE_CODE,
        "device_id": NODE_CODE,
        "timestamp": datetime.now().isoformat() + "Z",
        "acc_x": random.gauss(0, 0.05) if status != "CRITICAL" else random.gauss(5.0, 0.5),
        "acc_y": random.gauss(0, 0.05) if status != "CRITICAL" else random.gauss(-4.0, 0.5),
        "acc_z": random.gauss(9.81, 0.05) if status != "CRITICAL" else random.gauss(4.0, 1.0),
        "pitch": round(pitch, 2),
        "roll": round(roll, 2),
        "angle_x": round(pitch, 2),
        "angle_y": round(roll, 2),
        "vibration_freq": random.uniform(0.1, 1.5) if status == "NORMAL" else random.uniform(15.0, 30.0),
        "ppv_value": random.uniform(0.1, 1.0),
        "ai_classification": "SAFE" if status == "NORMAL" else status
    }

def send_incident(pitch, roll):
    print("\n--- TRIGGER INCIDENT -> POST /api/v1/incidents ---")
    payload = {
        "device_id": NODE_CODE,
        "node_code": NODE_CODE,
        "angle_x": pitch,
        "angle_y": roll,
        "status": "BAHAYA"
    }
    
    files = {}
    dummy_img = 'backend/latest_snapshot.jpg'
    if os.path.exists(dummy_img):
        files['photo'] = open(dummy_img, 'rb')
        
    try:
        res = requests.post(INCIDENT_API, data=payload, files=files if files else None, timeout=3)
        print(f"[INCIDENT] Response ({res.status_code}): {res.text}")
    except Exception as e:
        print(f"[INCIDENT ERROR] Gagal mengirim insiden: {e}")
    finally:
        if 'photo' in files:
            files['photo'].close()

if __name__ == "__main__":
    print(f"Starting GeoGuard Simulator untuk node [{NODE_CODE}]...")
    t = 0
    prev_state = "NORMAL"
    
    try:
        while True:
            cycle_t = t % 60
            
            if cycle_t < 40:
                state = "NORMAL"
            elif cycle_t < 50:
                state = "WASPADA"
            else:
                state = "CRITICAL"
                
            data = generate_telemetry(cycle_t, state)
            
            # Trigger foto insiden saat pertama kali masuk fase CRITICAL
            if state == "CRITICAL" and prev_state != "CRITICAL":
                send_incident(data["pitch"], data["roll"])
                
            prev_state = state
            
            try:
                res = requests.post(TELEMETRY_API, json=data, timeout=2)
                print(f"[{datetime.now().strftime('%H:%M:%S')}] Telemetry Sent | t={cycle_t:.1f}s | Pitch={data['pitch']}° | Status={res.status_code}")
            except requests.exceptions.ConnectionError:
                print(f"[{datetime.now().strftime('%H:%M:%S')}] Error: Gagal koneksi ke {TELEMETRY_API}")
                
            time.sleep(0.5)
            t += 0.5
            
    except KeyboardInterrupt:
        print("\nSimulator dimatikan.")
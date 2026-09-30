import json
import time
import os
import requests
import cv2
import numpy as np
import paho.mqtt.client as mqtt
from datetime import datetime
import threading

# ==========================================
# CONFIGURATION
# ==========================================
MQTT_BROKER = "127.0.0.1"       # Mosquitto berjalan lokal di Raspberry Pi
MQTT_PORT = 1883
MQTT_TOPIC = "mine/pit1/inclinometer"

LARAVEL_API_URL = "http://10.214.68.58:8000/api/v1"
WEBCAM_INDEX = 0                # /dev/video0

# Cooldown incident (cegah spam jepret kamera berkali-kali)
last_incident_time = 0
INCIDENT_COOLDOWN = 10          # 10 detik

# Proactive Crack Scanning (AI jalan otomatis tanpa tunggu sensor)
PROACTIVE_SCAN_INTERVAL = 60    # Scan setiap 60 detik
last_proactive_scan_time = 0

# ==========================================
# AI MODEL CONFIGURATION (ONNX Runtime)
# ==========================================
MODEL_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "models")
MODEL_PATH = os.path.join(MODEL_DIR, "best.onnx")

# Confidence threshold: hanya tampilkan deteksi dengan kepercayaan >= 50%
CONFIDENCE_THRESHOLD = 0.50

# Global: ONNX session hanya di-load SEKALI saat startup
onnx_session = None
CLASS_NAMES = {0: "crack"}  # Sesuaikan jika model punya multi-class


def load_model():
    """Load model ONNX ke memori. Dipanggil SEKALI saat startup."""
    global onnx_session

    if not os.path.exists(MODEL_PATH):
        print(f"[AI] ❌ Model ONNX tidak ditemukan di: {MODEL_PATH}")
        print(f"[AI]    Silakan copy file best.onnx ke folder models/")
        return False

    try:
        import onnxruntime as ort
        print(f"[AI] Loading ONNX model: {MODEL_PATH}")
        onnx_session = ort.InferenceSession(MODEL_PATH, providers=['CPUExecutionProvider'])

        # Warmup: jalankan inferensi dummy 1x agar model sudah siap di RAM
        input_name = onnx_session.get_inputs()[0].name
        dummy = np.zeros((1, 3, 640, 640), dtype=np.float32)
        onnx_session.run(None, {input_name: dummy})

        print("[AI] ✅ ONNX Crack Detection Engine AKTIF dan siap digunakan!")
        return True

    except ImportError:
        print("[AI] ❌ Library 'onnxruntime' belum terinstall!")
        print("[AI]    Jalankan: pip install onnxruntime")
        onnx_session = None
        return False
    except Exception as e:
        print(f"[AI] ❌ Gagal memuat model ONNX: {e}")
        onnx_session = None
        return False


def _preprocess(image_path):
    """Preprocess gambar untuk input ONNX YOLOv8 (1,3,640,640) float32."""
    img = cv2.imread(image_path)
    h_orig, w_orig = img.shape[:2]

    # Letterbox resize ke 640x640
    scale = min(640 / h_orig, 640 / w_orig)
    new_w, new_h = int(w_orig * scale), int(h_orig * scale)
    resized = cv2.resize(img, (new_w, new_h))

    canvas = np.full((640, 640, 3), 114, dtype=np.uint8)
    pad_x, pad_y = (640 - new_w) // 2, (640 - new_h) // 2
    canvas[pad_y:pad_y+new_h, pad_x:pad_x+new_w] = resized

    # HWC BGR -> CHW RGB, normalize 0-1
    blob = canvas[:, :, ::-1].transpose(2, 0, 1).astype(np.float32) / 255.0
    blob = np.expand_dims(blob, axis=0)

    return blob, img, scale, pad_x, pad_y


def _nms(boxes, scores, iou_threshold=0.45):
    """Simple Non-Maximum Suppression."""
    if len(boxes) == 0:
        return []

    x1 = boxes[:, 0]
    y1 = boxes[:, 1]
    x2 = boxes[:, 2]
    y2 = boxes[:, 3]
    areas = (x2 - x1) * (y2 - y1)
    order = scores.argsort()[::-1]

    keep = []
    while order.size > 0:
        i = order[0]
        keep.append(i)
        xx1 = np.maximum(x1[i], x1[order[1:]])
        yy1 = np.maximum(y1[i], y1[order[1:]])
        xx2 = np.minimum(x2[i], x2[order[1:]])
        yy2 = np.minimum(y2[i], y2[order[1:]])
        inter = np.maximum(0, xx2 - xx1) * np.maximum(0, yy2 - yy1)
        iou = inter / (areas[i] + areas[order[1:]] - inter)
        inds = np.where(iou <= iou_threshold)[0]
        order = order[inds + 1]

    return keep


def run_crack_detection(image_path):
    """
    Jalankan AI Crack Detection pada gambar menggunakan ONNX Runtime.
    Mengembalikan:
        - annotated_path (str): Path gambar yang sudah diberi bounding box
        - max_confidence (float): Confidence tertinggi dari semua deteksi (0.0 - 1.0)
        - crack_count (int): Jumlah retakan yang terdeteksi
    """
    if onnx_session is None:
        print("[AI] Model tidak tersedia, skip deteksi.")
        return image_path, 0.0, 0

    try:
        print(f"[AI] Menganalisis gambar: {image_path}")
        start_time = time.time()

        # Preprocess
        blob, frame, scale, pad_x, pad_y = _preprocess(image_path)

        # Jalankan inferensi ONNX
        input_name = onnx_session.get_inputs()[0].name
        outputs = onnx_session.run(None, {input_name: blob})

        # Output YOLOv8 ONNX: shape (1, 5, 8400) -> transpose ke (8400, 5)
        # Kolom: [cx, cy, w, h, conf_class0]
        preds = outputs[0][0].T  # (8400, 5)

        # Filter by confidence
        scores = preds[:, 4]  # confidence for class 0 (crack)
        mask = scores >= CONFIDENCE_THRESHOLD
        filtered = preds[mask]
        filtered_scores = scores[mask]

        # Convert cx,cy,w,h -> x1,y1,x2,y2 (masih di skala 640x640)
        boxes_640 = np.zeros((len(filtered), 4))
        boxes_640[:, 0] = filtered[:, 0] - filtered[:, 2] / 2  # x1
        boxes_640[:, 1] = filtered[:, 1] - filtered[:, 3] / 2  # y1
        boxes_640[:, 2] = filtered[:, 0] + filtered[:, 2] / 2  # x2
        boxes_640[:, 3] = filtered[:, 1] + filtered[:, 3] / 2  # y2

        # NMS
        keep_indices = _nms(boxes_640, filtered_scores)

        elapsed = time.time() - start_time
        crack_count = 0
        max_confidence = 0.0

        for idx in keep_indices:
            conf = float(filtered_scores[idx])
            if conf > max_confidence:
                max_confidence = conf

            # Konversi koordinat dari 640x640 letterbox -> gambar asli
            bx1 = (boxes_640[idx, 0] - pad_x) / scale
            by1 = (boxes_640[idx, 1] - pad_y) / scale
            bx2 = (boxes_640[idx, 2] - pad_x) / scale
            by2 = (boxes_640[idx, 3] - pad_y) / scale

            x1, y1, x2, y2 = int(bx1), int(by1), int(bx2), int(by2)
            crack_count += 1

            # ===== GAMBAR BOUNDING BOX YANG CANTIK =====
            # Warna berdasarkan confidence (hijau->kuning->merah)
            if conf >= 0.7:
                color = (0, 0, 255)       # Merah terang (BGR) - High confidence
            elif conf >= 0.4:
                color = (0, 165, 255)     # Orange (BGR) - Medium
            else:
                color = (0, 255, 255)     # Kuning (BGR) - Low

            # Box utama dengan ketebalan proporsional
            thickness = 3
            cv2.rectangle(frame, (x1, y1), (x2, y2), color, thickness)

            # Corner accents (garis kecil di sudut-sudut, gaya militer/HUD)
            corner_len = max(15, min(x2 - x1, y2 - y1) // 5)
            cv2.line(frame, (x1, y1), (x1 + corner_len, y1), color, thickness + 2)
            cv2.line(frame, (x1, y1), (x1, y1 + corner_len), color, thickness + 2)
            cv2.line(frame, (x2, y1), (x2 - corner_len, y1), color, thickness + 2)
            cv2.line(frame, (x2, y1), (x2, y1 + corner_len), color, thickness + 2)
            cv2.line(frame, (x1, y2), (x1 + corner_len, y2), color, thickness + 2)
            cv2.line(frame, (x1, y2), (x1, y2 - corner_len), color, thickness + 2)
            cv2.line(frame, (x2, y2), (x2 - corner_len, y2), color, thickness + 2)
            cv2.line(frame, (x2, y2), (x2, y2 - corner_len), color, thickness + 2)

            # Label background (kotak hitam semi-transparan di atas box)
            label = f"CRACK #{crack_count} | {conf*100:.0f}%"
            font = cv2.FONT_HERSHEY_SIMPLEX
            font_scale = 0.6
            (label_w, label_h), baseline = cv2.getTextSize(label, font, font_scale, 2)
            label_y = max(y1 - 10, label_h + 10)

            # Background label
            cv2.rectangle(frame, (x1, label_y - label_h - 8), (x1 + label_w + 10, label_y + 4), (0, 0, 0), -1)
            cv2.rectangle(frame, (x1, label_y - label_h - 8), (x1 + label_w + 10, label_y + 4), color, 2)

            # Teks label
            cv2.putText(frame, label, (x1 + 5, label_y - 2), font, font_scale, (255, 255, 255), 2)

        # ===== HEADER HUD OVERLAY =====
        h, w = frame.shape[:2]
        overlay = frame.copy()

        # Banner atas
        cv2.rectangle(overlay, (0, 0), (w, 40), (0, 0, 0), -1)
        cv2.addWeighted(overlay, 0.7, frame, 0.3, 0, frame)

        timestamp_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        cv2.putText(frame, f"GEOGUARD AI | CRACK DETECTION", (10, 28),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.65, (0, 255, 255), 2)
        cv2.putText(frame, timestamp_str, (w - 250, 28),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.55, (200, 200, 200), 1)

        # Status bar di bawah
        overlay2 = frame.copy()
        cv2.rectangle(overlay2, (0, h - 35), (w, h), (0, 0, 0), -1)
        cv2.addWeighted(overlay2, 0.7, frame, 0.3, 0, frame)

        if crack_count > 0:
            status_text = f"DETECTED: {crack_count} CRACK(S) | MAX CONF: {max_confidence*100:.0f}% | TIME: {elapsed:.2f}s"
            status_color = (0, 0, 255)  # Merah
        else:
            status_text = f"NO CRACKS DETECTED | SURFACE INTEGRITY OK | TIME: {elapsed:.2f}s"
            status_color = (0, 255, 0)  # Hijau

        cv2.putText(frame, status_text, (10, h - 12),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.5, status_color, 2)

        # Simpan gambar hasil analisis AI
        annotated_path = image_path.replace(".jpg", "_ai_analyzed.jpg")
        cv2.imwrite(annotated_path, frame, [cv2.IMWRITE_JPEG_QUALITY, 92])

        print(f"[AI] ✅ Analisis selesai dalam {elapsed:.2f}s")
        print(f"[AI]    Retakan terdeteksi: {crack_count}")
        print(f"[AI]    Confidence tertinggi: {max_confidence*100:.1f}%")
        print(f"[AI]    Hasil disimpan: {annotated_path}")

        return annotated_path, max_confidence, crack_count

    except Exception as e:
        print(f"[AI] ❌ Error saat deteksi: {e}")
        import traceback
        traceback.print_exc()
        return image_path, 0.0, 0


# ==========================================
# CAMERA & INCIDENT FUNCTIONS
# ==========================================

def capture_snapshot(filename="incident_snapshot.jpg"):
    """Mengambil foto dari webcam."""
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
    """Mengambil foto, menjalankan AI, dan mengirim hasil ke Laravel."""
    print("\n--- PIPELINE: CAPTURE → AI ANALYSIS → SEND INCIDENT ---")

    payload = {
        "device_id": data.get("node_code"),
        "angle_x": data.get("pitch", 0),
        "angle_y": data.get("roll", 0),
        "status": data.get("ai_classification", "CRITICAL")
    }

    # STEP 1: Jepret foto dari webcam
    raw_img_path = capture_snapshot()
    if not raw_img_path:
        print("[INCIDENT] Gagal mengambil foto. Abort.")
        return

    # STEP 2: Jalankan AI Crack Detection pada foto tersebut
    analyzed_img_path, ai_confidence, crack_count = run_crack_detection(raw_img_path)

    # STEP 3: Tentukan trigger type berdasarkan hasil AI
    if crack_count > 0:
        payload["trigger_type"] = "CRACK_DETECT"
        payload["status"] = "CRITICAL"
        # Override ai_confidence di Laravel dengan hasil deteksi AI sesungguhnya
        payload["ai_confidence"] = round(ai_confidence, 4)
    else:
        # Murni tilt alert karena sensor kemiringan trigger tanpa retakan
        payload["trigger_type"] = "TILT_ALERT"
        payload["ai_confidence"] = 0.85

    # STEP 4: Kirim foto hasil analisis AI ke Laravel
    files = {}
    try:
        files['photo'] = open(analyzed_img_path, 'rb')
    except Exception as e:
        print(f"[INCIDENT] Gagal membuka file foto: {e}")

    try:
        url = f"{LARAVEL_API_URL}/incidents"
        res = requests.post(url, data=payload, files=files if files else None, timeout=10)
        print(f"[INCIDENT] ✅ Response ({res.status_code}): {res.text}")
    except Exception as e:
        print(f"[INCIDENT ERROR] ❌ Gagal mengirim insiden: {e}")
    finally:
        if 'photo' in files:
            files['photo'].close()

    # Cleanup: hapus file foto temporary
    for f in [raw_img_path, analyzed_img_path]:
        try:
            if os.path.exists(f):
                os.remove(f)
        except:
            pass


def send_incident(data):
    """Jalankan di thread terpisah agar MQTT tidak terblokir."""
    threading.Thread(target=task_send_incident, args=(data,), daemon=True).start()


def task_send_telemetry(data):
    """Meneruskan data telemetri sensor ke Laravel API."""
    try:
        url = f"{LARAVEL_API_URL}/telemetry"
        res = requests.post(url, json=data, timeout=5)
        print(f"Telemetry Diteruskan -> Laravel | Status: {res.status_code}")
    except Exception as e:
        print(f"Gagal meneruskan Telemetry: {e}")


def send_telemetry(data):
    """Jalankan di thread terpisah."""
    threading.Thread(target=task_send_telemetry, args=(data,), daemon=True).start()


# ==========================================
# MQTT HANDLERS
# ==========================================

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

        status = data.get("ai_classification", "NORMAL")
        print(f"\n[MQTT] Pesan masuk dari {data.get('node_code')} | Status: {status}")

        # Selalu teruskan telemetri
        send_telemetry(data)

        # Cek apakah CRITICAL/WARNING dan belum cooldown
        if status in ["CRITICAL", "WARNING"]:
            current_time = time.time()
            if current_time - last_incident_time >= INCIDENT_COOLDOWN:
                last_incident_time = current_time
                send_incident(data)
            else:
                remaining = INCIDENT_COOLDOWN - (current_time - last_incident_time)
                print(f"Status BAHAYA terdeteksi, namun masih dalam masa cooldown kamera ({remaining:.0f}s lagi).")

    except Exception as e:
        print(f"Error memproses pesan MQTT: {e}")


# ==========================================
# PROACTIVE CRACK SCANNING (Autonomous)
# ==========================================

def proactive_crack_scan():
    """Background thread: secara berkala jepret foto dan jalankan AI.
    Jika retakan ditemukan, langsung kirim incident ke Laravel
    TANPA menunggu trigger dari sensor ESP32."""
    global last_proactive_scan_time

    print("[PROACTIVE] 🔍 Autonomous Crack Scanner AKTIF")
    print(f"[PROACTIVE]    Interval: setiap {PROACTIVE_SCAN_INTERVAL} detik\n")

    while True:
        time.sleep(PROACTIVE_SCAN_INTERVAL)

        if onnx_session is None:
            continue  # Skip jika AI model belum di-load

        try:
            print(f"\n[PROACTIVE] 📸 Scanning permukaan tanah...")
            raw_img_path = capture_snapshot("proactive_scan.jpg")
            if not raw_img_path:
                print("[PROACTIVE] ⚠️ Gagal mengambil foto, skip.")
                continue

            analyzed_img_path, ai_confidence, crack_count = run_crack_detection(raw_img_path)

            if crack_count > 0:
                print(f"[PROACTIVE] 🚨 RETAKAN TERDETEKSI! ({crack_count} crack, conf: {ai_confidence*100:.0f}%)")
                print(f"[PROACTIVE]    Mengirim incident otomatis ke Laravel...")

                payload = {
                    "device_id": "INC_HW_01",
                    "angle_x": 0,
                    "angle_y": 0,
                    "status": "CRITICAL",
                    "trigger_type": "CRACK_DETECT",
                    "ai_confidence": round(ai_confidence, 4),
                }

                files = {}
                try:
                    files['photo'] = open(analyzed_img_path, 'rb')
                except Exception as e:
                    print(f"[PROACTIVE] Gagal membuka file foto: {e}")

                try:
                    url = f"{LARAVEL_API_URL}/incidents"
                    res = requests.post(url, data=payload, files=files if files else None, timeout=10)
                    print(f"[PROACTIVE] ✅ Incident terkirim ({res.status_code}): {res.text}")
                except Exception as e:
                    print(f"[PROACTIVE] ❌ Gagal mengirim: {e}")
                finally:
                    if 'photo' in files:
                        files['photo'].close()
            else:
                print(f"[PROACTIVE] ✅ Tidak ada retakan. Permukaan aman.")

            # Cleanup
            for f in [raw_img_path, analyzed_img_path]:
                try:
                    if f and os.path.exists(f):
                        os.remove(f)
                except:
                    pass

        except Exception as e:
            print(f"[PROACTIVE] ❌ Error: {e}")


# ==========================================
# MAIN ENTRY POINT
# ==========================================

if __name__ == "__main__":
    print("=" * 60)
    print("  GEOGUARD - Raspberry Pi Edge Gateway")
    print("  with YOLOv8 AI Crack Detection Engine")
    print("=" * 60)
    print()

    # STEP 1: Load AI Model sekali di awal (download jika belum ada)
    print("[STARTUP] Mempersiapkan AI Engine...")
    ai_ready = load_model()
    if ai_ready:
        print("[STARTUP] ✅ AI Engine: ONLINE")
    else:
        print("[STARTUP] ⚠️ AI Engine: OFFLINE (sistem tetap berjalan tanpa AI)")
    print()

    # STEP 2: Koneksi ke MQTT Broker
    print("[STARTUP] Mempersiapkan koneksi ke MQTT...")
    client = mqtt.Client()
    client.on_connect = on_connect
    client.on_message = on_message

    try:
        client.connect(MQTT_BROKER, MQTT_PORT, 60)
        print("[STARTUP] ✅ MQTT: Menunggu data dari ESP32...")

        # STEP 3: Jalankan Proactive Crack Scanner di background
        if ai_ready:
            scan_thread = threading.Thread(target=proactive_crack_scan, daemon=True)
            scan_thread.start()
            print("[STARTUP] ✅ Proactive Crack Scanner: AKTIF")
        else:
            print("[STARTUP] ⚠️ Proactive Crack Scanner: NONAKTIF (AI offline)")
        print()

        client.loop_forever()
    except KeyboardInterrupt:
        print("\n[SHUTDOWN] Gateway dimatikan oleh operator.")
    except Exception as e:
        print(f"[FATAL] Error: {e}")

#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <MPU6050_tockn.h>
#include <Wire.h>
#include <TinyGPS++.h>
#include "time.h"
#include "config.h"

TinyGPSPlus gps;
HardwareSerial SerialGPS(2);

MPU6050 mpu(Wire);
WiFiClient espClient;
PubSubClient client(espClient);

unsigned long lastTelemetryTime = 0;
unsigned long telemetryInterval = 2000; // 2 detik

String currentState = "NORMAL";
bool incidentTriggered = false;
bool isBuzzerMuted = false;

// Variabel untuk pola beep non-blocking
unsigned long lastBuzzerToggle = 0;
bool buzzerState = false;

float lastAccX = 0, lastAccY = 0, lastAccZ = 0;

const char* ntpServer = "pool.ntp.org";
const long  gmtOffset_sec = 25200; // WIB (GMT+7)
const int   daylightOffset_sec = 0;

void sendDebugLog(String message) {
    Serial.println(message); 
    if (client.connected()) {
        client.publish("mine/pit1/debug", message.c_str());
    }
}

void setFeedback(String state) {
    // Matikan semua lampu dulu
    digitalWrite(PIN_LED_YELLOW, LOW);
    digitalWrite(PIN_LED_RED, LOW);

    if (state == "NORMAL") {
        digitalWrite(PIN_LED_GREEN, HIGH);
        isBuzzerMuted = false; // Reset mute status when normal
    } else if (state == "WASPADA") {
        digitalWrite(PIN_LED_GREEN, LOW);
        digitalWrite(PIN_LED_YELLOW, HIGH);
    } else if (state == "CRITICAL") {
        digitalWrite(PIN_LED_GREEN, LOW);
        digitalWrite(PIN_LED_RED, HIGH);
    }
}

void reconnectMQTT() {
    while (!client.connected()) {
        Serial.print("Menghubungkan ke MQTT Broker...");
        String clientId = "GeoGuard-Node-";
        clientId += String(random(0xffff), HEX);
        if (client.connect(clientId.c_str())) {
            Serial.println("Berhasil!");
            // Subscribe ke topik command
            client.subscribe("mine/pit1/command");
        } else {
            Serial.print("Gagal, rc=");
            Serial.print(client.state());
            Serial.println(" Coba lagi dalam 5 detik");
            delay(5000);
        }
    }
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
    String msg;
    for (unsigned int i = 0; i < length; i++) {
        msg += (char)payload[i];
    }
    Serial.print("Pesan MQTT masuk [");
    Serial.print(topic);
    Serial.print("]: ");
    Serial.println(msg);

    if (String(topic) == "mine/pit1/command") {
        StaticJsonDocument<256> doc;
        DeserializationError error = deserializeJson(doc, msg);
        if (!error) {
            String targetNode = doc["node"] | "";
            if (targetNode == NODE_CODE || targetNode == "ALL") {
                String cmd = doc["command"];
                if (cmd == "buzzer_off") {
                    isBuzzerMuted = true;
                    sendDebugLog("[CMD] Mematikan Buzzer karena perintah dashboard.");
                    setFeedback(currentState); // Terapkan feedback baru
                } else if (cmd == "set_interval") {
                    unsigned long newInterval = doc["interval"];
                    if (newInterval > 0) {
                        telemetryInterval = newInterval;
                        sendDebugLog("[CMD] Mengubah Interval Telemetri ke: " + String(telemetryInterval) + " ms");
                    }
                }
            }
        }
    }
}

void setup() {
    Serial.begin(115200);
    while (!Serial) delay(10);

    Serial.println("\n--- GeoGuard ESP32 MQTT Node ---");
    
    pinMode(PIN_LED_GREEN, OUTPUT);
    pinMode(PIN_LED_YELLOW, OUTPUT);
    pinMode(PIN_LED_RED, OUTPUT);
    pinMode(PIN_BUZZER, OUTPUT);
    
    setFeedback("NORMAL");

    // Inisialisasi GPS (RX=16, TX=17)
    SerialGPS.begin(9600, SERIAL_8N1, 16, 17);

    // Inisialisasi I2C secara spesifik (SDA=21, SCL=22)
    Wire.begin(21, 22);
    mpu.begin();
    Serial.println("Kalibrasi MPU6050... Jangan gerakkan sensor!");
    mpu.calcGyroOffsets(true);
    Serial.println("\nMPU6050 Siap!");

    Serial.print("Menghubungkan ke WiFi: ");
    Serial.println(WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.println("\nWiFi Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
    
    

    configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
    
    client.setServer(MQTT_BROKER_IP, MQTT_PORT);
    client.setCallback(mqttCallback);
    client.setBufferSize(512); 
}

void sendTelemetry(float accX, float accY, float accZ, float pitch, float roll, float freq, float ppv) {
    if (!client.connected()) {
        reconnectMQTT();
    }
    client.loop();

    StaticJsonDocument<512> doc;
    doc["node_code"] = NODE_CODE;
    
    struct tm timeinfo;
    if(!getLocalTime(&timeinfo)){
        doc["timestamp"] = "2026-09-12T00:00:00Z"; 
    } else {
        char timeStringBuff[50];
        strftime(timeStringBuff, sizeof(timeStringBuff), "%Y-%m-%dT%H:%M:%SZ", &timeinfo);
        doc["timestamp"] = String(timeStringBuff);
    }
    
    doc["acc_x"] = accX;
    doc["acc_y"] = accY;
    doc["acc_z"] = accZ;
    doc["pitch"] = pitch;
    doc["roll"] = roll;
    doc["vibration_freq"] = freq;
    doc["ppv_value"] = ppv;
    
    // GPS
    if (gps.location.isValid()) {
        doc["latitude"] = gps.location.lat();
        doc["longitude"] = gps.location.lng();
        doc["elevation"] = gps.altitude.meters();
    } else {
        doc["latitude"] = 0.0;
        doc["longitude"] = 0.0;
    }
    
    // Status klasifikasi (konsisten: CRITICAL / WARNING / NORMAL)
    if (currentState == "CRITICAL") doc["ai_classification"] = "CRITICAL";
    else if (currentState == "WASPADA") doc["ai_classification"] = "WARNING";
    else doc["ai_classification"] = "NORMAL";

    String requestBody;
    serializeJson(doc, requestBody);

    if(client.publish(MQTT_TOPIC, requestBody.c_str())) {
        Serial.print("MQTT Terkirim: ");
        Serial.println(requestBody);
    } else {
        Serial.println("Gagal publish MQTT!");
    }
}

void loop() {
    if (!client.connected()) {
        reconnectMQTT();
    }
    client.loop();

    // Baca data dari GPS
    while (SerialGPS.available() > 0) {
        gps.encode(SerialGPS.read());
    }

    mpu.update();

    float accX = mpu.getAccX();
    float accY = mpu.getAccY();
    float accZ = mpu.getAccZ();
    
    // getAngleX dan getAngleY sudah memberikan nilai absolut dari pitch dan roll yang sudah difilter
    float pitch = mpu.getAngleX();
    float roll = mpu.getAngleY();

    float accDiff = abs(accX - lastAccX) + abs(accY - lastAccY) + abs(accZ - lastAccZ);
    // Estimasi frekuensi getaran dinamis (Hz) berbasis dinamika akselerasi
    float freq = (accDiff > 0.05) ? round((5.0f + accDiff * 20.0f) * 10.0f) / 10.0f : 0.5f;
    // Estimasi PPV dinamis (mm/s) berbasis getaran puncak akselerasi
    float ppv = (accDiff > 0.05) ? round((accDiff * 9.81f * 10.0f / max(freq, 1.0f)) * 100.0f) / 100.0f : 0.15f;
    ppv = min(max(ppv, 0.1f), 100.0f);
    
    lastAccX = accX;
    lastAccY = accY;
    lastAccZ = accZ;

    float maxTilt = max(abs(pitch), abs(roll));
    
    String prevState = currentState;
    if (maxTilt >= TILT_CRITICAL_THRESHOLD) {
        currentState = "CRITICAL";
    } else if (maxTilt >= TILT_WARNING_THRESHOLD) {
        currentState = "WASPADA";
    } else {
        currentState = "NORMAL";
    }

    if (prevState != currentState) {
        setFeedback(currentState);
        
        sendDebugLog("[STATUS] Terjadi perubahan status dari " + prevState + " menjadi " + currentState + "!");
        if (currentState == "CRITICAL" && !isBuzzerMuted) {
            sendDebugLog("[BUZZER] Menyala panjang (CRITICAL)");
        } else if (currentState == "WASPADA" && !isBuzzerMuted) {
            sendDebugLog("[BUZZER] Menyala putus-putus (WASPADA)");
        } else if (isBuzzerMuted) {
            sendDebugLog("[BUZZER] Tidak menyala karena sedang dalam status MUTED.");
        }

        // Langsung paksa publish saat status berubah agar responsif
        sendTelemetry(accX, accY, accZ, pitch, roll, freq, ppv);
        lastTelemetryTime = millis();
    }

    if (millis() - lastTelemetryTime >= telemetryInterval) {
        lastTelemetryTime = millis();
        sendTelemetry(accX, accY, accZ, pitch, roll, freq, ppv);
    }

    // --- LOGIKA BUZZER NON-BLOCKING ---
    if (!isBuzzerMuted) {
        if (currentState == "CRITICAL") {
            // Beep Panjang (Nyala terus menerus)
            digitalWrite(PIN_BUZZER, HIGH);
        } else if (currentState == "WASPADA") {
            // Beep Berjeda (500ms ON, 500ms OFF)
            if (millis() - lastBuzzerToggle >= 500) {
                lastBuzzerToggle = millis();
                buzzerState = !buzzerState;
                digitalWrite(PIN_BUZZER, buzzerState ? HIGH : LOW);
            }
        } else {
            // Normal -> Mati
            digitalWrite(PIN_BUZZER, LOW);
        }
    } else {
        // Dimatikan manual dari panel
        digitalWrite(PIN_BUZZER, LOW);
    }
    
    delay(10);
}

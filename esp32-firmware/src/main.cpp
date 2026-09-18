#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <MPU6050_tockn.h>
#include <Wire.h>
#include "time.h"
#include "config.h"

MPU6050 mpu(Wire);
WiFiClient espClient;
PubSubClient client(espClient);

unsigned long lastTelemetryTime = 0;
unsigned long telemetryInterval = 2000; // 2 detik

String currentState = "NORMAL";
bool incidentTriggered = false;

float lastAccX = 0, lastAccY = 0, lastAccZ = 0;

const char* ntpServer = "pool.ntp.org";
const long  gmtOffset_sec = 25200; // WIB (GMT+7)
const int   daylightOffset_sec = 0;

void setFeedback(String state) {
    // Matikan semua dulu
    digitalWrite(PIN_LED_GREEN, LOW);
    digitalWrite(PIN_LED_YELLOW, LOW);
    digitalWrite(PIN_LED_RED, LOW);
    digitalWrite(PIN_BUZZER, LOW);

    if (state == "NORMAL") {
        digitalWrite(PIN_LED_GREEN, HIGH);
    } else if (state == "WASPADA") {
        digitalWrite(PIN_LED_YELLOW, HIGH);
        // Beep pelan
        digitalWrite(PIN_BUZZER, HIGH);
        delay(50);
        digitalWrite(PIN_BUZZER, LOW);
    } else if (state == "CRITICAL") {
        digitalWrite(PIN_LED_RED, HIGH);
        // Sirine nyala
        digitalWrite(PIN_BUZZER, HIGH);
    }
}

void reconnectMQTT() {
    while (!client.connected()) {
        Serial.print("Menghubungkan ke MQTT Broker...");
        String clientId = "GeoGuard-Node-";
        clientId += String(random(0xffff), HEX);
        if (client.connect(clientId.c_str())) {
            Serial.println("Berhasil!");
        } else {
            Serial.print("Gagal, rc=");
            Serial.print(client.state());
            Serial.println(" Coba lagi dalam 5 detik");
            delay(5000);
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
    client.setBufferSize(512); // WAJIB DITAMBAHKAN KARENA PAYLOAD JSON KITA CUKUP BESAR
}

void sendTelemetry(float accX, float accY, float accZ, float pitch, float roll, float freq) {
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
    doc["ppv_value"] = 0.5;
    
    // Status BAHAYA (dipetakan dari CRITICAL)
    if (currentState == "CRITICAL") doc["ai_classification"] = "BAHAYA";
    else if (currentState == "WASPADA") doc["ai_classification"] = "WASPADA";
    else doc["ai_classification"] = "SAFE";

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

    // Mengambil data MPU terbaru (sudah ditangani secara cerdas oleh tockn)
    mpu.update();

    float accX = mpu.getAccX();
    float accY = mpu.getAccY();
    float accZ = mpu.getAccZ();
    
    // getAngleX dan getAngleY sudah memberikan nilai absolut dari pitch dan roll yang sudah difilter (Complementary Filter internal)
    float pitch = mpu.getAngleX();
    float roll = mpu.getAngleY();

    float accDiff = abs(accX - lastAccX) + abs(accY - lastAccY) + abs(accZ - lastAccZ);
    float freq = (accDiff > 0.5) ? accDiff * 2.0 : 0.1;
    
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
        
        // Langsung paksa publish saat status berubah agar responsif
        sendTelemetry(accX, accY, accZ, pitch, roll, freq);
        lastTelemetryTime = millis();
    }

    if (millis() - lastTelemetryTime >= telemetryInterval) {
        lastTelemetryTime = millis();
        sendTelemetry(accX, accY, accZ, pitch, roll, freq);
    }
    
    delay(10);
}

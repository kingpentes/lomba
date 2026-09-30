#ifndef CONFIG_H
#define CONFIG_H

// ==========================================
// WIFI SETTINGS
// ==========================================
#define WIFI_SSID "halo"
#define WIFI_PASSWORD "razitganteng"
// ==========================================
// MQTT SETTINGS (Raspberry Pi Gateway)
// ==========================================
#define MQTT_BROKER_IP "10.94.19.91"
#define MQTT_PORT 1883
#define MQTT_TOPIC "mine/pit1/inclinometer"

// ==========================================
// DEVICE SETTINGS
// ==========================================
#define NODE_CODE "INC_HW_01"

// Threshold Miring (dalam derajat) untuk memicu peringatan (WASPADA)
#define TILT_WARNING_THRESHOLD 15.0

// Threshold Miring (dalam derajat) untuk memicu insiden (CRITICAL / BAHAYA)
#define TILT_CRITICAL_THRESHOLD 30.0

// ==========================================
// HARDWARE PINS
// ==========================================
#define PIN_LED_GREEN 12
#define PIN_LED_YELLOW 13
#define PIN_LED_RED 14
#define PIN_BUZZER 25

#endif

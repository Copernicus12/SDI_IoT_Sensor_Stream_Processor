# Configurare ESP32 pentru IoT Sensor Stream

## Librării Necesare Arduino IDE

Înainte de a încărca codul, instalează următoarele librării în Arduino IDE:

### Metoda 1: Prin Library Manager (Recomandat)
1. Deschide Arduino IDE
2. **Tools > Manage Libraries** (Ctrl+Shift+I)
3. Caută și instalează:

- **PubSubClient** by Nick O'Leary (v2.8.0+)
  - Pentru comunicarea MQTT
  
- **DHT sensor library** by Adafruit (v1.4.4+)
  - Pentru ESP32 Node 1 (DHT11)
  
- **Adafruit Unified Sensor** by Adafruit
  - Dependență pentru DHT sensor library

### Metoda 2: Manual
Descarcă și copiază în folder-ul `Arduino/libraries/`:
- https://github.com/knolleary/pubsubclient
- https://github.com/adafruit/DHT-sensor-library
- https://github.com/adafruit/Adafruit_Sensor

## Configurare Board ESP32

### Adăugare Board Manager URL
1. **File > Preferences**
2. În "Additional Board Manager URLs" adaugă:
```
https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
```

### Instalare ESP32 Board
1. **Tools > Board > Boards Manager**
2. Caută "esp32"
3. Instalează **esp32 by Espressif Systems**

### Selectare Board
1. **Tools > Board > ESP32 Arduino**
2. Selectează: **ESP32 Dev Module**

### Setări Port
1. Conectează ESP32 via USB
2. **Tools > Port**
3. Selectează portul corespunzător:
   - macOS: `/dev/cu.usbserial-*` sau `/dev/cu.SLAB_USBtoUART`
   - Windows: `COM3`, `COM4`, etc.
   - Linux: `/dev/ttyUSB0`

## Pasul 1: Pregătirea fișierului .env

1. Copiază fișierul `.env.example` în `.env`:
   ```bash
   cp .env.example .env
   ```

2. Editează fișierul `.env` cu credențialele tale reale:
   ```
   WIFI_SSID=numele_tau_wifi
   WIFI_PASSWORD=parola_ta_wifi
   MQTT_SERVER=ip_server_mqtt
   ```

## Pasul 2: Actualizarea codului

În fiecare fișier `.ino`, înlocuiește liniile de configurare cu:

```cpp
#include "config.h"
```

Și apoi folosește variabilele din `config.h` în loc de valori hardcodate.

## Pasul 3: Actualizarea valorilor în config.h

Editează fișierul `config.h` și înlocuiește valorile placeholder cu cele reale din `.env`.

## Securitate

⚠️ **IMPORTANT:**
- **NU** partaja niciodată fișierul `.env`
- **NU** încărca `.env` pe GitHub/GitLab
- Fișierul `.gitignore` este configurat să ignore `.env` automat
- Partajează doar `.env.example` ca template

## Note

Arduino IDE nu suportă în mod nativ citirea fișierelor `.env` la compile-time.
Din acest motiv, trebuie să copiezi manual valorile din `.env` în `config.h`.

Pentru proiecte mai complexe, poți folosi PlatformIO care suportă build flags și environment variables.

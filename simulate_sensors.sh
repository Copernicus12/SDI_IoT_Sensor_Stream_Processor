#!/bin/bash

# Script pentru simularea datelor de la senzori ESP32
# Trimite date random la topicurile MQTT

echo "🎲 Simulator date senzori ESP32"
echo "================================"
echo ""

# Verifică dacă Mosquitto este instalat
if ! command -v mosquitto_pub &> /dev/null; then
    echo "❌ Mosquitto nu este instalat!"
    echo "Instalează cu: brew install mosquitto"
    exit 1
fi

echo "✅ Mosquitto găsit"
echo "📡 Trimit date simulate..."
echo ""

# Inițializare valori de pornire
TEMP=24.0
HUM=60.0
SOIL=500
CURR=2.5

# Funcție pentru generare float random între min și max cu pas mic
calc_float() {
    local current=$1
    local min=$2
    local max=$3
    local step=$4
    
    # Generăm o schimbare random între -step și +step
    # Folosim perl pentru calcule float mai ușoare pe orice sistem, sau bc
    # Aici folosim bc. Random 0-200 -> -100 la 100 -> împărțit la (100/step)
    
    local change=$(echo "scale=3; ($RANDOM % 200 - 100) / 100 * $step" | bc -l)
    local new_val=$(echo "scale=3; $current + $change" | bc -l)
    
    # Verificăm limitele
    if (( $(echo "$new_val > $max" | bc -l) )); then new_val=$max; fi
    if (( $(echo "$new_val < $min" | bc -l) )); then new_val=$min; fi
    
    printf "%.2f" $new_val
}

# Funcție pentru generare int random cu pas mic
calc_int() {
    local current=$1
    local min=$2
    local max=$3
    local step=$4
    
    local change=$((RANDOM % (step * 2 + 1) - step))
    local new_val=$((current + change))
    
    if [ $new_val -gt $max ]; then new_val=$max; fi
    if [ $new_val -lt $min ]; then new_val=$min; fi
    
    echo $new_val
}

# Loop infinit pentru trimitere date
while true; do
    # Temperatură (18-30°C), pas max 0.5
    TEMP=$(calc_float $TEMP 18 30 0.5)
    mosquitto_pub -h localhost -t "iot/esp32_node1/temperatura" -m "{\"value\": $TEMP}"
    echo "🌡️  Temperatură: $TEMP °C"
    
    # Umiditate (40-80%), pas max 2.0
    HUM=$(calc_float $HUM 40 80 2.0)
    mosquitto_pub -h localhost -t "iot/esp32_node1/umiditate" -m "{\"value\": $HUM}"
    echo "💧 Umiditate: $HUM %"
    
    # Umiditate sol (300-800 ADC), pas max 20
    SOIL=$(calc_int $SOIL 300 800 20)
    mosquitto_pub -h localhost -t "iot/esp32_node2/umiditate_sol" -m "{\"value\": $SOIL}"
    echo "🌱 Umiditate sol: $SOIL ADC"
    
    # Curent (0.5-4.5A), pas max 0.2
    CURR=$(calc_float $CURR 0.5 4.5 0.2)
    mosquitto_pub -h localhost -t "iot/esp32_node3/curent" -m "{\"value\": $CURR}"
    echo "⚡ Curent: $CURR A"
    
    echo "---"
    sleep 1
done

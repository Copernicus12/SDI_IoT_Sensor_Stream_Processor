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

# Loop infinit pentru trimitere date
while true; do
    # Temperatură (18-30°C)
    TEMP=$(printf "%.1f" "$(echo "scale=2; 18 + $RANDOM % 120 / 10" | bc -l)")
    mosquitto_pub -h localhost -t "iot/esp32_node1/temperatura" -m "{\"value\": $TEMP}"
    echo "🌡️  Temperatură: $TEMP °C"
    
    # Umiditate (40-80%)
    HUM=$(printf "%.1f" "$(echo "scale=2; 40 + $RANDOM % 400 / 10" | bc -l)")
    mosquitto_pub -h localhost -t "iot/esp32_node1/umiditate" -m "{\"value\": $HUM}"
    echo "💧 Umiditate: $HUM %"
    
    # Umiditate sol (300-800 ADC)
    SOIL=$(echo "$RANDOM % 500 + 300" | bc)
    mosquitto_pub -h localhost -t "iot/esp32_node2/umiditate_sol" -m "{\"value\": $SOIL}"
    echo "🌱 Umiditate sol: $SOIL ADC"
    
    # Curent (0.5-4.5A)
    CURR=$(printf "%.2f" "$(echo "scale=3; 0.5 + $RANDOM % 400 / 100" | bc -l)")
    mosquitto_pub -h localhost -t "iot/esp32_node3/curent" -m "{\"value\": $CURR}"
    echo "⚡ Curent: $CURR A"
    
    echo "---"
    sleep 1
done

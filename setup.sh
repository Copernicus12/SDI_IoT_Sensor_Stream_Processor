
set -e

echo "🚀 IoT Sensor Stream Processor - Setup"
echo "========================================"
echo ""

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' 

if [ ! -f frontend/.env ]; then
    echo -e "${YELLOW}📝 Creez fișierul frontend/.env...${NC}"
    cp frontend/.env.example frontend/.env
    php frontend/artisan key:generate
    echo -e "${GREEN}✅ .env creat${NC}"
else
    echo -e "${GREEN}✅ frontend/.env există deja${NC}"
fi

echo -e "${YELLOW}📦 Instalez dependențe PHP (Composer)...${NC}"
composer --working-dir frontend install --no-interaction

echo -e "${YELLOW}📦 Instalez dependențe Node.js (NPM)...${NC}"
npm --prefix frontend install

echo ""
echo -e "${YELLOW}🗄️  Configurare bază de date${NC}"
read -p "Ai configurat baza de date în frontend/.env? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Rulez migrațiile...${NC}"
    php frontend/artisan migrate
    
    echo -e "${YELLOW}Populez baza de date cu senzori...${NC}"
    php frontend/artisan db:seed --class=SensorSeeder
    
    echo -e "${GREEN}✅ Baza de date configurată${NC}"
else
    echo -e "${RED}⚠️  Te rog să configurezi baza de date în frontend/.env și rulează:${NC}"
    echo "   php frontend/artisan migrate"
    echo "   php frontend/artisan db:seed --class=SensorSeeder"
fi

echo ""
echo -e "${YELLOW}🎨 Compilez assets frontend...${NC}"
npm --prefix frontend run build

echo ""
echo -e "${YELLOW}🔌 Verificare MQTT Broker (Mosquitto)...${NC}"
if command -v mosquitto &> /dev/null; then
    echo -e "${GREEN}✅ Mosquitto este instalat${NC}"
    
    if pgrep -x "mosquitto" > /dev/null; then
        echo -e "${GREEN}✅ Mosquitto rulează${NC}"
    else
        echo -e "${YELLOW}⚠️  Mosquitto nu rulează. Pornesc...${NC}"
        if [[ "$OSTYPE" == "darwin"* ]]; then
            brew services start mosquitto
        else
            sudo systemctl start mosquitto
        fi
    fi
else
    echo -e "${RED} Mosquitto nu este instalat${NC}"
    echo ""
    echo "Instalează Mosquitto:"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        echo "  brew install mosquitto"
        echo "  brew services start mosquitto"
    else
        echo "  sudo apt-get install mosquitto mosquitto-clients"
        echo "  sudo systemctl start mosquitto"
    fi
fi

echo ""
echo -e "${GREEN} Setup complet!${NC}"
echo ""
echo "📋 Pași următori:"
echo ""
echo "1. Configurează ESP32:"
echo "   - Editează esp32_code/config.h cu credențialele WiFi și IP-ul serverului MQTT"
echo "   - Încarcă codul pe fiecare ESP32"
echo ""
echo "2. Pornește MQTT Subscriber (Terminal 1):"
echo -e "   ${YELLOW}php frontend/artisan mqtt:subscribe${NC}"
echo ""
echo "3. Pornește Laravel Server (Terminal 2):"
echo -e "   ${YELLOW}php frontend/artisan serve${NC}"
echo ""
echo "4. (Opțional) Development mode (Terminal 3):"
echo -e "   ${YELLOW}npm --prefix frontend run dev${NC}"
echo ""
echo "5. Accesează dashboard-ul:"
echo -e "   ${YELLOW}http://localhost:8000/sensors${NC}"
echo ""
echo "🎉 Succes!"

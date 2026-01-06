#include <WiFi.h>
#include <PubSubClient.h>
#include "DHT.h"
#include "config.h"

#define DHTPIN 4
#define DHTTYPE DHT11

WiFiClient espClient;
PubSubClient client(espClient);
DHT dht(DHTPIN, DHTTYPE);

void setup_wifi() {
  Serial.println();
  Serial.print("Conectare la WiFi ");
  Serial.println(ssid);

  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("\nWiFi conectat.");
  Serial.println(WiFi.localIP());
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Conectare MQTT...");
    if (client.connect(mqtt_client_node1)) {
      Serial.println("conectat!");
    } else {
      Serial.print("Eroare, rc=");
      Serial.print(client.state());
      Serial.println(" -> retry in 5 sec");
      delay(5000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  dht.begin();
  setup_wifi();
  client.setServer(mqtt_server, mqtt_port);
}

void loop() {
  if (!client.connected()) reconnect();
  client.loop();

  float t = dht.readTemperature();
  float h = dht.readHumidity();

  if (!isnan(t)) {
    char buf[32];
    sprintf(buf, "{\"value\": %.2f}", t);
    client.publish("iot/esp32_node1/temperatura", buf);
  }

  if (!isnan(h)) {
    char buf[32];
    sprintf(buf, "{\"value\": %.2f}", h);
    client.publish("iot/esp32_node1/umiditate", buf);
  }

  delay(2000);
}

#include <WiFi.h>
#include <PubSubClient.h>
#include "config.h"

// === CONFIG ACS712 ===
#define ACS_PIN 35

WiFiClient espClient;
PubSubClient client(espClient);

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
    if (client.connect(mqtt_client_node3)) {
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
  setup_wifi();
  client.setServer(mqtt_server, mqtt_port);
}

void loop() {
  if (!client.connected()) reconnect();
  client.loop();

  int acs = analogRead(ACS_PIN);

  char buf[32];
  sprintf(buf, "{\"value\": %d}", acs);
  client.publish("iot/esp32_node3/curent", buf);

  delay(2000);
}

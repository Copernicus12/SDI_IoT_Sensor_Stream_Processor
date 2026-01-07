package sdi.iot.bootstrap;

import org.springframework.boot.CommandLineRunner;
import org.springframework.stereotype.Component;
import sdi.iot.model.ApiToken;
import sdi.iot.model.Sensor;
import sdi.iot.model.SensorReading;
import sdi.iot.repo.ApiTokenRepository;
import sdi.iot.repo.SensorReadingRepository;
import sdi.iot.repo.SensorRepository;

import java.time.Instant;
import java.util.List;
import java.util.Random;
import java.util.Random;

@Component
public class DataSeeder implements CommandLineRunner {
    private final SensorRepository sensors;
    private final SensorReadingRepository readings;
    private final ApiTokenRepository tokens;

    public DataSeeder(SensorRepository sensors, SensorReadingRepository readings, ApiTokenRepository tokens) {
        this.sensors = sensors; this.readings = readings; this.tokens = tokens;
    }

    @Override
    public void run(String... args) throws Exception {
        if (sensors.count() == 0) {
            Sensor t = new Sensor(); t.setName("DHT11 Temperature"); t.setType("temperatura"); t.setUnit("°C"); t.setNodeId("node-1"); t.setActive(true); t.setMqttTopic("iot/esp32_node1/temperatura"); sensors.save(t);
            Sensor h = new Sensor(); h.setName("DHT11 Humidity"); h.setType("umiditate"); h.setUnit("%"); h.setNodeId("node-1"); h.setActive(true); h.setMqttTopic("iot/esp32_node1/umiditate"); sensors.save(h);
            Sensor s = new Sensor(); s.setName("Soil Moisture"); s.setType("umiditate_sol"); s.setUnit("%"); s.setNodeId("node-2"); s.setActive(true); s.setMqttTopic("iot/esp32_node2/umiditate_sol"); sensors.save(s);
            Sensor c = new Sensor(); c.setName("ACS712 Current"); c.setType("curent"); c.setUnit("A"); c.setNodeId("node-3"); c.setActive(true); c.setMqttTopic("iot/esp32_node3/curent"); sensors.save(c);

            Random rnd = new Random(42);
            var now = Instant.now();
            for (Sensor sensor : List.of(t,h,s,c)) {
                for (int i = 0; i < 300; i++) {
                    SensorReading r = new SensorReading();
                    r.setSensor(sensor);
                    double base = switch (sensor.getType()) {
                        case "temperatura" -> 21.0;
                        case "umiditate" -> 50.0;
                        case "umiditate_sol" -> 35.0;
                        case "curent" -> 0.5;
                        default -> 0.0;
                    };
                    double val = base + rnd.nextGaussian() * (sensor.getType().equals("curent") ? 0.05 : 2.0);
                    r.setValue(val);
                    r.setCreatedAt(now.minusSeconds(60L * (300 - i)));
                    readings.save(r);
                }
            }
        }
        if (tokens.count() == 0) {
            ApiToken tok = new ApiToken();
            tok.setName("dev-token");
            tok.setToken("dev-12345");
            tokens.save(tok);
        }
    }
}

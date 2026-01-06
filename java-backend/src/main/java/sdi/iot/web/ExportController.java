package sdi.iot.web;

import org.springframework.http.HttpHeaders;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import sdi.iot.model.Sensor;
import sdi.iot.model.SensorReading;
import sdi.iot.repo.SensorReadingRepository;
import sdi.iot.repo.SensorRepository;

import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.time.Instant;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.stream.Collectors;

@RestController
@RequestMapping("/api/export")
public class ExportController {
    private final SensorRepository sensors;
    private final SensorReadingRepository readings;

    public ExportController(SensorRepository sensors, SensorReadingRepository readings) {
        this.sensors = sensors; this.readings = readings;
    }

    @GetMapping("/sensors/{id}.json")
    public ResponseEntity<?> exportJson(@PathVariable long id, @RequestParam(defaultValue = "24") int hours) {
        Sensor s = sensors.findById(id).orElseThrow();
        Instant after = Instant.now().minus(Duration.ofHours(hours));
        List<SensorReading> list = readings.findBySensorAndCreatedAtAfterOrderByCreatedAtDesc(s, after);
        String json = list.stream()
                .map(r -> String.format("{\"value\":%s,\"timestamp\":\"%s\"}", r.getValue(), r.getCreatedAt()))
                .collect(Collectors.joining(",","[", "]"));
        return ResponseEntity.ok()
                .contentType(MediaType.APPLICATION_JSON)
                .body("{\"success\":true,\"data\":{\"readings\": " + json + "}} ");
    }

    @GetMapping(value = "/sensors/{id}.csv", produces = "text/csv")
    public ResponseEntity<byte[]> exportCsv(@PathVariable long id, @RequestParam(defaultValue = "24") int hours) {
        Sensor s = sensors.findById(id).orElseThrow();
        Instant after = Instant.now().minus(Duration.ofHours(hours));
        List<SensorReading> list = readings.findBySensorAndCreatedAtAfterOrderByCreatedAtDesc(s, after);
        StringBuilder sb = new StringBuilder();
        sb.append("value,timestamp\n");
        DateTimeFormatter fmt = DateTimeFormatter.ISO_INSTANT;
        for (SensorReading r : list) {
            sb.append(r.getValue()).append(',').append(fmt.format(r.getCreatedAt())).append('\n');
        }
        byte[] bytes = sb.toString().getBytes(StandardCharsets.UTF_8);
        return ResponseEntity.ok()
                .header(HttpHeaders.CONTENT_DISPOSITION, "attachment; filename=export_" + id + ".csv")
                .contentType(MediaType.parseMediaType("text/csv"))
                .body(bytes);
    }
}

package sdi.iot.web;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.dao.DataAccessException;
import org.springframework.data.domain.PageRequest;
import org.springframework.http.MediaType;
import org.springframework.web.bind.annotation.*;
import sdi.iot.model.Sensor;
import sdi.iot.model.SensorReading;
import sdi.iot.repo.SensorReadingRepository;
import sdi.iot.repo.SensorRepository;
import sdi.iot.web.dto.ApiResponse;
import sdi.iot.web.dto.SensorDtos.*;

import java.time.Duration;
import java.time.Instant;
import java.util.*;
import java.util.concurrent.atomic.AtomicBoolean;

@RestController
@RequestMapping(path = "/api", produces = MediaType.APPLICATION_JSON_VALUE)
public class SensorController {
    private static final Logger log = LoggerFactory.getLogger(SensorController.class);
    private final SensorRepository sensors;
    private final SensorReadingRepository readings;
    private final AtomicBoolean nativeAggregationDisabled = new AtomicBoolean(false);

    public SensorController(SensorRepository sensors, SensorReadingRepository readings) {
        this.sensors = sensors; this.readings = readings;
    }

    @GetMapping("/sensors")
    public ApiResponse<List<SensorItem>> listSensors() {
        List<Sensor> all = sensors.findAllOrdered();
        List<SensorItem> out = new ArrayList<>();
        for (Sensor s : all) {
            Optional<SensorReading> last = readings.findTopBySensorOrderByCreatedAtDesc(s);
            Double latestValue = last.map(SensorReading::getValue).orElse(null);
            var latestAt = last.map(SensorReading::getCreatedAt).orElse(null);
            out.add(new SensorItem(s.getId(), s.getNodeId(), s.getName(), s.getType(), s.getUnit(), latestValue, latestAt));
        }
        return ApiResponse.ok(out);
    }

    @GetMapping("/sensors/statistics")
    public ApiResponse<List<StatItem>> statistics(@RequestParam(defaultValue = "24") int hours) {
        Instant after = Instant.now().minus(Duration.ofHours(hours));
        List<StatItem> out = new ArrayList<>();
        for (Sensor s : sensors.findAll()) {
            var stats = readings.statsForSensor(s, after);
            double avg = stats == null || stats.getAvg() == null ? 0.0 : stats.getAvg();
            double min = stats == null || stats.getMin() == null ? 0.0 : stats.getMin();
            double max = stats == null || stats.getMax() == null ? 0.0 : stats.getMax();
            long cnt = stats == null || stats.getCnt() == null ? 0L : stats.getCnt();
            out.add(new StatItem(s.getId(), s.getName(), s.getType(), s.getUnit(), avg, min, max, cnt));
        }
        return ApiResponse.ok(out);
    }

    @GetMapping("/sensors/{id}/readings")
    public ApiResponse<Map<String, Object>> readingsBySensor(@PathVariable long id,
                                                             @RequestParam(defaultValue = "2") int hours,
                                                             @RequestParam(required = false) Integer limit) {
        Sensor s = sensors.findById(id).orElseThrow();
        Instant after = Instant.now().minus(Duration.ofHours(hours));
        List<SensorReading> list;
        if (limit != null) {
            list = readings.latestReadings(s, after, PageRequest.of(0, Math.max(1, Math.min(1000, limit))));
        } else {
            list = readings.findBySensorAndCreatedAtAfterOrderByCreatedAtDesc(s, after);
        }
        List<Reading> mapped = list.stream()
                .map(r -> new Reading(r.getValue(), r.getCreatedAt()))
                .toList();
        Map<String, Object> payload = Map.of("readings", mapped);
        return ApiResponse.ok(payload);
    }

    @GetMapping("/sensors/stream")
    public ApiResponse<Map<String, Object>> globalStream(@RequestParam(defaultValue = "10") int limit) {
        var list = readings.globalStream(PageRequest.of(0, Math.max(1, Math.min(1000, limit))));
        var mapped = list.stream().map(r -> new StreamItem(
                r.getSensor().getType(),
                r.getSensor().getUnit(),
                r.getValue(),
                r.getCreatedAt()
        )).toList();
        return ApiResponse.ok(Map.of("readings", mapped));
    }

    @GetMapping("/sensors/{id}/aggregates")
    public ApiResponse<List<AggregatePoint>> aggregates(@PathVariable long id,
                                                        @RequestParam(defaultValue = "24") int hours,
                                                        @RequestParam(defaultValue = "hour") String period) {
        Sensor s = sensors.findById(id).orElseThrow();
        Instant after = Instant.now().minus(Duration.ofHours(hours));
        String p = switch (period) {
            case "day" -> "day"; default -> "hour";
        };
        if (!nativeAggregationDisabled.get()) {
            try {
                var rows = readings.aggregates(s.getId(), after, p);
                var out = rows.stream().map(this::mapAggregateRow).toList();
                return ApiResponse.ok(out);
            } catch (DataAccessException | UnsupportedOperationException ex) {
                nativeAggregationDisabled.set(true);
                log.warn("Native aggregation query failed for sensor {} ({}). Disabling native aggregation and falling back to in-memory computation.",
                        id, ex.getMessage());
            }
        }
        var fallback = aggregateInMemory(s, after, p);
        return ApiResponse.ok(fallback);
    }

    @GetMapping("/sensors/{id}/anomalies")
    public ApiResponse<List<Anomaly>> anomalies(@PathVariable long id,
                                                @RequestParam(defaultValue = "24") int hours,
                                                @RequestParam(defaultValue = "3.0") double z) {
        Sensor s = sensors.findById(id).orElseThrow();
        Instant after = Instant.now().minus(Duration.ofHours(hours));
        var list = readings.findBySensorAndCreatedAtAfterOrderByCreatedAtDesc(s, after);
        if (list.isEmpty()) return ApiResponse.ok(List.of());
        // Compute mean and std
        double mean = list.stream().mapToDouble(SensorReading::getValue).average().orElse(0);
        double variance = list.stream().mapToDouble(r -> Math.pow(r.getValue() - mean, 2)).average().orElse(0);
        double std = Math.sqrt(variance);
        if (std == 0) return ApiResponse.ok(List.of());
        List<Anomaly> out = new ArrayList<>();
        for (SensorReading r : list) {
            double zscore = (r.getValue() - mean) / std;
            if (Math.abs(zscore) >= z) {
                out.add(new Anomaly(r.getCreatedAt(), r.getValue(), zscore));
            }
        }
        // Return chronological order
        out.sort(Comparator.comparing(Anomaly::timestamp));
        return ApiResponse.ok(out);
    }

    private AggregatePoint mapAggregateRow(Object[] r) {
        java.time.Instant bucket;
        Object b = r[0];
        if (b instanceof java.sql.Timestamp ts) {
            bucket = ts.toInstant();
        } else if (b instanceof java.time.OffsetDateTime odt) {
            bucket = odt.toInstant();
        } else if (b instanceof java.time.LocalDateTime ldt) {
            bucket = ldt.toInstant(java.time.ZoneOffset.UTC);
        } else if (b instanceof java.time.Instant instantBucket) {
            bucket = instantBucket;
        } else {
            bucket = java.time.Instant.now();
        }
        return new AggregatePoint(
                bucket,
                r[1] == null ? null : ((Number) r[1]).doubleValue(),
                r[2] == null ? null : ((Number) r[2]).doubleValue(),
                r[3] == null ? null : ((Number) r[3]).doubleValue(),
                r[4] == null ? null : ((Number) r[4]).intValue()
        );
    }

    private List<AggregatePoint> aggregateInMemory(Sensor sensor, Instant after, String period) {
        List<SensorReading> raw = new ArrayList<>(readings.findBySensorAndCreatedAtAfterOrderByCreatedAtDesc(sensor, after));
        if (raw.isEmpty()) return List.of();
        raw.sort(Comparator.comparing(SensorReading::getCreatedAt));
        long bucketSeconds = "day".equals(period) ? Duration.ofDays(1).getSeconds() : Duration.ofHours(1).getSeconds();
        Map<Long, BucketAccumulator> acc = new LinkedHashMap<>();
        for (SensorReading reading : raw) {
            long epoch = reading.getCreatedAt().getEpochSecond();
            long bucketStartEpoch = (epoch / bucketSeconds) * bucketSeconds;
            BucketAccumulator bucket = acc.computeIfAbsent(bucketStartEpoch, BucketAccumulator::new);
            bucket.accept(reading.getValue());
        }
        List<AggregatePoint> out = new ArrayList<>(acc.size());
        for (BucketAccumulator bucket : acc.values()) {
            out.add(new AggregatePoint(
                    Instant.ofEpochSecond(bucket.bucketEpoch),
                    bucket.avg(),
                    bucket.min,
                    bucket.max,
                    (int) bucket.count
            ));
        }
        return out;
    }

    private static final class BucketAccumulator {
        private final long bucketEpoch;
        private double sum = 0;
        private double min = Double.POSITIVE_INFINITY;
        private double max = Double.NEGATIVE_INFINITY;
        private long count = 0;

        BucketAccumulator(long bucketEpoch) {
            this.bucketEpoch = bucketEpoch;
        }

        void accept(double value) {
            sum += value;
            if (value < min) min = value;
            if (value > max) max = value;
            count++;
        }

        Double avg() {
            return count == 0 ? null : sum / count;
        }
    }
}

package sdi.iot.web.dto;

import java.time.Instant;

public class SensorDtos {
    public record SensorItem(Long id, String node_id, String name, String type, String unit, Double latest_value, Instant latest_reading_at) {}
    public record StatItem(Long sensor_id, String sensor_name, String sensor_type, String unit, double avg, double min, double max, long total_readings) {}
    public record Reading(double value, Instant timestamp) {}
    public record StreamItem(String type, String unit, double value, Instant timestamp) {}
    public record AggregatePoint(Instant bucket_start, Double avg, Double min, Double max, Integer cnt) {}
    public record Anomaly(Instant timestamp, double value, double zscore) {}
}

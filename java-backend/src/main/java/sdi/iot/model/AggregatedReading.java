package sdi.iot.model;

import jakarta.persistence.*;
import java.time.Instant;

@Entity
@Table(name = "aggregated_readings", uniqueConstraints = @UniqueConstraint(columnNames = {"sensor_id","bucket_start","period"}))
public class AggregatedReading {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "sensor_id", nullable = false)
    private Sensor sensor;

    @Column(name = "bucket_start", nullable = false)
    private Instant bucketStart;

    @Column(nullable = false, length = 10)
    private String period; // hour, day

    private Double avg;
    private Double min;
    private Double max;
    private Integer cnt;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public Sensor getSensor() { return sensor; }
    public void setSensor(Sensor sensor) { this.sensor = sensor; }
    public Instant getBucketStart() { return bucketStart; }
    public void setBucketStart(Instant bucketStart) { this.bucketStart = bucketStart; }
    public String getPeriod() { return period; }
    public void setPeriod(String period) { this.period = period; }
    public Double getAvg() { return avg; }
    public void setAvg(Double avg) { this.avg = avg; }
    public Double getMin() { return min; }
    public void setMin(Double min) { this.min = min; }
    public Double getMax() { return max; }
    public void setMax(Double max) { this.max = max; }
    public Integer getCnt() { return cnt; }
    public void setCnt(Integer cnt) { this.cnt = cnt; }
}

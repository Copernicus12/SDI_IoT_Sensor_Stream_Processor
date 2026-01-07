package sdi.iot.model;

import jakarta.persistence.*;
import java.math.BigDecimal;
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

    @Column(nullable = false, columnDefinition = "ENUM('hour', 'day', 'week')")
    private String period; // hour, day

    @Column(name = "avg_value")
    private BigDecimal avg;
    
    @Column(name = "min_value")
    private BigDecimal min;

    @Column(name = "max_value")
    private BigDecimal max;

    @Column(name = "count")
    private Integer cnt;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public Sensor getSensor() { return sensor; }
    public void setSensor(Sensor sensor) { this.sensor = sensor; }
    public Instant getBucketStart() { return bucketStart; }
    public void setBucketStart(Instant bucketStart) { this.bucketStart = bucketStart; }
    public String getPeriod() { return period; }
    public void setPeriod(String period) { this.period = period; }
    public BigDecimal getAvg() { return avg; }
    public void setAvg(BigDecimal avg) { this.avg = avg; }
    public BigDecimal getMin() { return min; }
    public void setMin(BigDecimal min) { this.min = min; }
    public BigDecimal getMax() { return max; }
    public void setMax(BigDecimal max) { this.max = max; }
    public Integer getCnt() { return cnt; }
    public void setCnt(Integer cnt) { this.cnt = cnt; }
}

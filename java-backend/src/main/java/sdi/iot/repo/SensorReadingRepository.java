package sdi.iot.repo;

import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import sdi.iot.model.Sensor;
import sdi.iot.model.SensorReading;

import java.time.Instant;
import java.util.List;
import java.util.Optional;

public interface SensorReadingRepository extends JpaRepository<SensorReading, Long> {
    List<SensorReading> findBySensorAndCreatedAtAfterOrderByCreatedAtDesc(Sensor sensor, Instant after);

    @Query("select r from SensorReading r where r.sensor = :sensor and r.createdAt > :after order by r.createdAt desc")
    List<SensorReading> latestReadings(@Param("sensor") Sensor sensor, @Param("after") Instant after, Pageable pageable);

    Optional<SensorReading> findTopBySensorOrderByCreatedAtDesc(Sensor sensor);

    @Query("select r from SensorReading r join fetch r.sensor order by r.createdAt desc")
    List<SensorReading> globalStream(Pageable pageable);

    public static interface SensorStats {
        Double getAvg();
        Double getMin();
        Double getMax();
        Long getCnt();
    }

    @Query("select avg(r.value) as avg, min(r.value) as min, max(r.value) as max, count(r) as cnt from SensorReading r where r.sensor = :sensor and r.createdAt > :after")
    SensorStats statsForSensor(@Param("sensor") Sensor sensor, @Param("after") Instant after);

    @Query(value = "select " +
        "case when upper(:period) = 'DAY' " +
        "     then DATE_FORMAT(created_at, '%Y-%m-%d 00:00:00') " +
        "     else DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') end as bucket, " +
        "avg(value) as avg, min(value) as min, max(value) as max, count(*) as cnt " +
        "from sensor_readings where sensor_id = :sensorId and created_at > :after group by bucket order by bucket asc", nativeQuery = true)
    List<Object[]> aggregates(@Param("sensorId") long sensorId, @Param("after") Instant after, @Param("period") String period);
}

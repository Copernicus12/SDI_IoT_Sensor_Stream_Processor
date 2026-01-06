package sdi.iot.repo;

import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import sdi.iot.model.Sensor;

import java.util.List;

public interface SensorRepository extends JpaRepository<Sensor, Long> {
    List<Sensor> findBySensorType(String type);

    @Query("select s from Sensor s order by s.id asc")
    List<Sensor> findAllOrdered();
}

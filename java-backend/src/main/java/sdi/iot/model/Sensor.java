package sdi.iot.model;

import jakarta.persistence.*;
import java.time.Instant;

@Entity
@Table(name = "sensors")
public class Sensor {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "node_id", length = 64)
    private String nodeId;

    @Column(nullable = false, length = 100)
    private String name;

    @Column(name = "sensor_type", nullable = false, length = 40)
    private String sensorType; // temperatura, umiditate, umiditate_sol, curent

    @Column(nullable = false, length = 16)
    private String unit; // C, %, A

    @Column(columnDefinition = "text")
    private String description;

    @Column(name = "mqtt_topic", length = 150)
    private String mqttTopic;

    @Column(name = "is_active")
    private Boolean active;

    @Column(name = "created_at")
    private Instant createdAt;

    @Column(name = "updated_at")
    private Instant updatedAt;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getNodeId() { return nodeId; }
    public void setNodeId(String nodeId) { this.nodeId = nodeId; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getType() { return sensorType; }
    public void setType(String sensorType) { this.sensorType = sensorType; }
    public String getUnit() { return unit; }
    public void setUnit(String unit) { this.unit = unit; }
    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }
    public String getMqttTopic() { return mqttTopic; }
    public void setMqttTopic(String mqttTopic) { this.mqttTopic = mqttTopic; }
    public Boolean getActive() { return active; }
    public void setActive(Boolean active) { this.active = active; }
    public Instant getCreatedAt() { return createdAt; }
    public void setCreatedAt(Instant createdAt) { this.createdAt = createdAt; }
    public Instant getUpdatedAt() { return updatedAt; }
    public void setUpdatedAt(Instant updatedAt) { this.updatedAt = updatedAt; }
}

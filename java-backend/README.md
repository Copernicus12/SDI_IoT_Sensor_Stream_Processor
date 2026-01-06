# SDI IoT Java Backend (Spring Boot)

A minimal Spring Boot backend that exposes the same `/api` endpoints your Vue frontend already uses.

## What it provides
- `/api/sensors` — list sensors with latest values
- `/api/sensors/statistics?hours=24` — stats per sensor (avg/min/max/count)
- `/api/sensors/{id}/readings?hours=2&limit=20` — recent readings for one sensor
- `/api/sensors/stream?limit=10` — recent global stream
- `/api/sensors/{id}/aggregates?hours=24&period=hour|day` — aggregated buckets
- `/api/sensors/{id}/anomalies?hours=24&z=3.0` — simple z-score anomalies
- `/api/export/sensors/{id}.csv|.json?hours=24&api_token=...` — exports (require API token)

## Quick start (dev)
This project is configured to use in-memory H2 for quick start and seeds sample data.

1. Prerequisites: Java 21, Maven 3.9+
2. Run the app:

```bash
mvn -q -f java-backend/pom.xml spring-boot:run
```

The API will be available at `http://localhost:8081`.

Seeded token for export endpoints: `dev-12345` (header `X-API-Token` or `?api_token=`)

## Integrate with the existing frontend
Your frontend currently calls relative endpoints like `/api/...` from the Laravel app. You have two options:

- Reverse proxy (recommended): Route `/api/**` to `http://localhost:8081` in your web server (Nginx/Apache) so the frontend code remains unchanged.
- Change fetch base: Introduce a central API base in your JS (e.g. `VITE_API_BASE`) and replace `fetch('/api/...')` with `fetch(`${import.meta.env.VITE_API_BASE}/api/...`)`. Set `VITE_API_BASE=http://localhost:8081` during dev.

## Switch to a real database
Update `src/main/resources/application.yml` to point to PostgreSQL/MySQL and add the driver:

```yaml
spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/iot
    username: iot
    password: iot
  jpa:
    hibernate:
      ddl-auto: validate
```

Also add the dependency in `pom.xml`:

```xml
<dependency>
  <groupId>org.postgresql</groupId>
  <artifactId>postgresql</artifactId>
  <scope>runtime</scope>
</dependency>
```

Flyway migrations are in `src/main/resources/db/migration`.

## Next steps
- Add MQTT ingestion (Eclipse Paho) to consume `sensors/+/readings` and insert readings
- Add thresholds/alerts endpoints if you want UI to manage them
- Optional: WebSocket push for live updates

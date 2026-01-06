package sdi.iot.repo;

import org.springframework.data.jpa.repository.JpaRepository;
import sdi.iot.model.ApiToken;

import java.util.Optional;

public interface ApiTokenRepository extends JpaRepository<ApiToken, Long> {
    Optional<ApiToken> findByTokenAndActiveTrue(String token);
}

package sdi.iot.repo;

import org.springframework.data.jpa.repository.JpaRepository;
import sdi.iot.model.AppSetting;

import java.util.Optional;

public interface AppSettingRepository extends JpaRepository<AppSetting, Long> {
    Optional<AppSetting> findByKey(String key);
}

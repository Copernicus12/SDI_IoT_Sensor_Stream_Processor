package sdi.iot.web;

import org.springframework.http.MediaType;
import org.springframework.web.bind.annotation.*;
import sdi.iot.model.AppSetting;
import sdi.iot.repo.AppSettingRepository;
import sdi.iot.web.dto.ApiResponse;

import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping(path = "/api/settings", produces = MediaType.APPLICATION_JSON_VALUE)
public class SettingsController {
    
    private final AppSettingRepository settingRepo;

    public SettingsController(AppSettingRepository settingRepo) {
        this.settingRepo = settingRepo;
    }

    @GetMapping("/anomaly-detection")
    public ApiResponse<Map<String, Object>> getAnomalySettings() {
        double z = getDoubleValue("anomaly.z_threshold", 3.0);
        int window = getIntValue("anomaly.window_size", 30);
        int hours = getIntValue("anomaly.lookback_hours", 6);

        Map<String, Object> data = new HashMap<>();
        data.put("z", z);
        data.put("window", window);
        data.put("hours", hours);

        return ApiResponse.ok(data);
    }

    @PostMapping("/anomaly-detection")
    public ApiResponse<Map<String, String>> saveAnomalySettings(@RequestBody Map<String, Object> payload) {
        try {
            double z = ((Number) payload.get("z")).doubleValue();
            int window = ((Number) payload.get("window")).intValue();
            int hours = ((Number) payload.get("hours")).intValue();

            // Validation
            if (z < 1 || z > 5) {
                return ApiResponse.error("Invalid z-score threshold (must be between 1 and 5)");
            }
            if (window < 10 || window > 100) {
                return ApiResponse.error("Invalid window size (must be between 10 and 100)");
            }
            if (hours < 1 || hours > 48) {
                return ApiResponse.error("Invalid lookback hours (must be between 1 and 48)");
            }

            saveOrUpdate("anomaly.z_threshold", String.valueOf(z));
            saveOrUpdate("anomaly.window_size", String.valueOf(window));
            saveOrUpdate("anomaly.lookback_hours", String.valueOf(hours));

            Map<String, String> response = new HashMap<>();
            response.put("message", "Anomaly detection settings saved successfully");
            return ApiResponse.ok(response);
        } catch (Exception e) {
            return ApiResponse.error("Failed to save settings: " + e.getMessage());
        }
    }

    private double getDoubleValue(String key, double defaultValue) {
        return settingRepo.findByKey(key)
                .map(s -> {
                    try {
                        return Double.parseDouble(s.getValue());
                    } catch (NumberFormatException e) {
                        return defaultValue;
                    }
                })
                .orElse(defaultValue);
    }

    private int getIntValue(String key, int defaultValue) {
        return settingRepo.findByKey(key)
                .map(s -> {
                    try {
                        return Integer.parseInt(s.getValue());
                    } catch (NumberFormatException e) {
                        return defaultValue;
                    }
                })
                .orElse(defaultValue);
    }

    private void saveOrUpdate(String key, String value) {
        AppSetting setting = settingRepo.findByKey(key)
                .orElse(new AppSetting(key, value));
        setting.setValue(value);
        settingRepo.save(setting);
    }
}

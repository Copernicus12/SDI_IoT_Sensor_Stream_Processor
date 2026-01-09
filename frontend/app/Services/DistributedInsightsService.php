<?php

namespace App\Services;

use App\Models\Sensor;
use App\Models\SensorReading;
use Carbon\Carbon;

class DistributedInsightsService
{
    public function compute(
        int $windowMinutes = 60,
        string $timezone = 'Europe/Bucharest',
        ?float $zWarn = null,
        ?float $zCritical = null,
        ?int $stalenessThresholdSeconds = null,
    ): array
    {
        $windowMinutes = max(10, min(360, $windowMinutes));

        $zWarn = $zWarn === null ? 2.0 : (float) $zWarn;
        $zCritical = $zCritical === null ? 3.0 : (float) $zCritical;
        $zWarn = max(0.5, min(10.0, $zWarn));
        $zCritical = max(0.5, min(10.0, $zCritical));
        if ($zCritical < $zWarn) {
            $zCritical = $zWarn;
        }

        $stalenessThresholdSeconds = $stalenessThresholdSeconds === null ? 180 : (int) $stalenessThresholdSeconds;
        $stalenessThresholdSeconds = max(10, min(3600, $stalenessThresholdSeconds));

        $now = now();
        $start = $now->copy()->subMinutes($windowMinutes);

        // Be tolerant to different seeders/DB states (some may have is_active NULL).
        $sensors = Sensor::query()
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->get();

        $readings = SensorReading::query()
            ->with('sensor')
            ->where('created_at', '>=', $start)
            ->orderBy('created_at')
            ->get();

        $bucketBySensor = [];
        $latestBySensor = [];

        foreach ($readings as $reading) {
            if (!$reading->sensor) {
                continue;
            }

            $sensorId = $reading->sensor_id;
            $minuteKey = $reading->created_at->copy()->setTimezone($timezone)->format('Y-m-d H:i');

            $bucketBySensor[$sensorId][$minuteKey]['sum'] = ($bucketBySensor[$sensorId][$minuteKey]['sum'] ?? 0.0) + (float) $reading->value;
            $bucketBySensor[$sensorId][$minuteKey]['count'] = ($bucketBySensor[$sensorId][$minuteKey]['count'] ?? 0) + 1;

            $latestBySensor[$sensorId] = $reading; // readings are ordered asc
        }

        $seriesBySensor = [];
        $bucketCount = 0;
        foreach ($bucketBySensor as $sensorId => $buckets) {
            foreach ($buckets as $minuteKey => $agg) {
                $bucketCount++;
                $seriesBySensor[$sensorId][$minuteKey] = $agg['count'] > 0
                    ? ($agg['sum'] / $agg['count'])
                    : null;
            }
        }

        $nodes = [
            'esp32_node1' => [
                'label' => 'ESP32 Node 1 (DHT11)',
                'metrics' => [
                    'temperatura' => ['label' => 'Temperatura', 'unit' => '°C'],
                    'umiditate' => ['label' => 'Umiditate', 'unit' => '%'],
                ],
            ],
            'esp32_node2' => [
                'label' => 'ESP32 Node 2 (Soil)',
                'metrics' => [
                    'umiditate_sol' => ['label' => 'Umiditate Sol', 'unit' => 'ADC'],
                ],
            ],
            'esp32_node3' => [
                'label' => 'ESP32 Node 3 (ACS712)',
                'metrics' => [
                    'curent' => ['label' => 'Curent', 'unit' => 'A'],
                ],
            ],
        ];

        $sensorIndex = [];
        foreach ($sensors as $sensor) {
            $nodeKey = $this->normalizeNodeKey($sensor->node_id, $sensor->mqtt_topic);
            if (!$nodeKey) {
                continue;
            }
            if (!$sensor->sensor_type) {
                continue;
            }
            $sensorIndex[$nodeKey][$sensor->sensor_type] = $sensor;
        }

        $nodeSummaries = [];

        $freshestNodeTimestamp = null;

        // Derived series: Microclimate index (temp+humidity) for node1
        $derivedSeries = [];
        $derivedLatest = [];

        $node1Temp = $sensorIndex['esp32_node1']['temperatura'] ?? null;
        $node1Hum = $sensorIndex['esp32_node1']['umiditate'] ?? null;

        if ($node1Temp && $node1Hum) {
            $tempSeries = $seriesBySensor[$node1Temp->id] ?? [];
            $humSeries = $seriesBySensor[$node1Hum->id] ?? [];
            $allKeys = array_unique(array_merge(array_keys($tempSeries), array_keys($humSeries)));
            sort($allKeys);

            foreach ($allKeys as $minuteKey) {
                $t = $tempSeries[$minuteKey] ?? null;
                $h = $humSeries[$minuteKey] ?? null;
                if ($t === null || $h === null) {
                    continue;
                }

                // Simple microclimate index: highlights “sticky heat” (distributed derived signal)
                $micro = (float) $t + 0.1 * (float) $h;
                $derivedSeries['microclimate'][$minuteKey] = $micro;
            }

            $latestT = $latestBySensor[$node1Temp->id] ?? null;
            $latestH = $latestBySensor[$node1Hum->id] ?? null;
            if ($latestT && $latestH) {
                $derivedLatest['microclimate'] = (float) $latestT->value + 0.1 * (float) $latestH->value;
            }
        }

        // Node summaries
        $zWarnCount = 0;
        $zCriticalCount = 0;
        foreach ($nodes as $nodeId => $nodeCfg) {
            $nodeSensors = $sensorIndex[$nodeId] ?? [];

            $nodeLatestAt = null;
            $nodeReadingsCount = 0;

            $metrics = [];

            foreach ($nodeCfg['metrics'] as $sensorType => $meta) {
                $sensor = $nodeSensors[$sensorType] ?? null;
                if (!$sensor) {
                    $metrics[] = [
                        'sensor_type' => $sensorType,
                        'sensor_name' => $meta['label'],
                        'unit' => $meta['unit'],
                        'latest' => null,
                        'mean' => null,
                        'std' => null,
                        'z' => null,
                        'count' => 0,
                    ];
                    continue;
                }

                $latest = $latestBySensor[$sensor->id] ?? null;
                if ($latest) {
                    $nodeLatestAt = $nodeLatestAt ? max($nodeLatestAt, $latest->created_at) : $latest->created_at;
                }

                $series = $seriesBySensor[$sensor->id] ?? [];
                $values = array_values($series);
                $stats = $this->stats($values);

                $z = null;
                if ($latest && $stats['std'] !== null && $stats['std'] > 0 && $stats['mean'] !== null) {
                    $z = ((float) $latest->value - (float) $stats['mean']) / (float) $stats['std'];
                    $z = round($z, 2);
                }

                $severity = null;
                if ($z !== null) {
                    $az = abs((float) $z);
                    if ($az >= $zCritical) {
                        $severity = 'critical';
                        $zCriticalCount++;
                    } elseif ($az >= $zWarn) {
                        $severity = 'warn';
                        $zWarnCount++;
                    } else {
                        $severity = 'ok';
                    }
                }

                $metrics[] = [
                    'sensor_type' => $sensorType,
                    'sensor_name' => $sensor->name ?? $meta['label'],
                    'unit' => $sensor->unit ?? $meta['unit'],
                    'latest' => $latest ? (float) $latest->value : null,
                    'mean' => $stats['mean'],
                    'std' => $stats['std'],
                    'z' => $z,
                    'severity' => $severity,
                    'count' => (int) $stats['count'],
                    'availability' => $windowMinutes > 0 ? round(min(1, count(array_keys($series)) / $windowMinutes), 3) : 0.0,
                    'missing_minutes' => $windowMinutes > 0 ? max(0, $windowMinutes - count(array_keys($series))) : 0,
                ];

                $nodeReadingsCount += $readings->where('sensor_id', $sensor->id)->count();
            }

            // Add derived metric to node1 (microclimate)
            if ($nodeId === 'esp32_node1') {
                $derivedValues = array_values($derivedSeries['microclimate'] ?? []);
                $dstats = $this->stats($derivedValues);
                $dlatest = $derivedLatest['microclimate'] ?? null;

                $dz = null;
                if ($dlatest !== null && $dstats['std'] !== null && $dstats['std'] > 0 && $dstats['mean'] !== null) {
                    $dz = ($dlatest - (float) $dstats['mean']) / (float) $dstats['std'];
                    $dz = round($dz, 2);
                }

                $dSeverity = null;
                if ($dz !== null) {
                    $az = abs((float) $dz);
                    if ($az >= $zCritical) {
                        $dSeverity = 'critical';
                        $zCriticalCount++;
                    } elseif ($az >= $zWarn) {
                        $dSeverity = 'warn';
                        $zWarnCount++;
                    } else {
                        $dSeverity = 'ok';
                    }
                }

                $metrics[] = [
                    'sensor_type' => 'microclimate',
                    'sensor_name' => 'Microclimate Index (Temp + 0.1×Hum)',
                    'unit' => 'index',
                    'latest' => $dlatest !== null ? (float) $dlatest : null,
                    'mean' => $dstats['mean'],
                    'std' => $dstats['std'],
                    'z' => $dz,
                    'severity' => $dSeverity,
                    'count' => (int) $dstats['count'],
                    'availability' => $windowMinutes > 0 ? round(min(1, count(array_keys($derivedSeries['microclimate'] ?? [])) / $windowMinutes), 3) : 0.0,
                    'missing_minutes' => $windowMinutes > 0 ? max(0, $windowMinutes - count(array_keys($derivedSeries['microclimate'] ?? []))) : 0,
                ];
            }

            $stalenessSeconds = null;
            if ($nodeLatestAt) {
                $stalenessSeconds = $now->diffInSeconds(Carbon::parse($nodeLatestAt));
            }

            if ($nodeLatestAt) {
                $freshestNodeTimestamp = $freshestNodeTimestamp
                    ? max($freshestNodeTimestamp, Carbon::parse($nodeLatestAt))
                    : Carbon::parse($nodeLatestAt);
            }

            // Node-level availability based on union of minute buckets across that node's base sensors
            $nodeMinuteKeys = [];
            foreach ($nodeSensors as $sensor) {
                $keys = array_keys($seriesBySensor[$sensor->id] ?? []);
                foreach ($keys as $k) {
                    $nodeMinuteKeys[$k] = true;
                }
            }
            $nodeBucketCount = count($nodeMinuteKeys);
            $nodeAvailability = $windowMinutes > 0 ? round(min(1, $nodeBucketCount / $windowMinutes), 3) : 0.0;

            $nodeSummaries[] = [
                'node_id' => $nodeId,
                'label' => $nodeCfg['label'],
                'last_update' => $nodeLatestAt ? Carbon::parse($nodeLatestAt)->setTimezone($timezone)->format('d.m.Y H:i:s') : 'Never',
                'staleness_seconds' => $stalenessSeconds,
                'throughput_rpm' => round(($nodeReadingsCount / max(1, $windowMinutes)) * 1.0, 2),
                'availability' => $nodeAvailability,
                'missing_minutes' => $windowMinutes > 0 ? max(0, $windowMinutes - $nodeBucketCount) : 0,
                'metrics' => $metrics,
            ];
        }

        // Cross-node correlations (derived microclimate vs soil/current, soil vs current)
        $corr = [];

        $soilSensor = $sensorIndex['esp32_node2']['umiditate_sol'] ?? null;
        $acsSensor = $sensorIndex['esp32_node3']['curent'] ?? null;

        $micro = $derivedSeries['microclimate'] ?? [];
        $soil = $soilSensor ? ($seriesBySensor[$soilSensor->id] ?? []) : [];
        $cur = $acsSensor ? ($seriesBySensor[$acsSensor->id] ?? []) : [];

        $corr[] = $this->correlationRow('Microclimate', $micro, 'Soil Moisture', $soil);
        $corr[] = $this->correlationRow('Current', $cur, 'Soil Moisture', $soil);
        $corr[] = $this->correlationRow('Microclimate', $micro, 'Current', $cur);

        // Distributed “health” score: completeness + clock skew + staleness
        $commonKeys = $this->commonKeys([$micro, $soil, $cur]);
        $completeness = $windowMinutes > 0 ? (count($commonKeys) / $windowMinutes) : 0.0;
        $completeness = max(0.0, min(1.0, $completeness));

        $latestTimes = [];
        foreach ($nodeSummaries as $node) {
            if ($node['staleness_seconds'] === null) {
                continue;
            }

            // Reverse staleness to “latest timestamp” in seconds for skew estimate
            $latestTimes[] = $now->copy()->subSeconds((int) $node['staleness_seconds'])->timestamp;
        }

        $skewSeconds = 0;
        if (count($latestTimes) >= 2) {
            $skewSeconds = max($latestTimes) - min($latestTimes);
        }

        // Per-node offset from freshest node (a concrete skew/lag indicator)
        $freshestTs = $freshestNodeTimestamp ? $freshestNodeTimestamp->timestamp : null;
        $nodeOffsets = [];
        foreach ($nodeSummaries as $node) {
            if ($freshestTs === null || $node['staleness_seconds'] === null) {
                $nodeOffsets[] = [
                    'node_id' => $node['node_id'],
                    'label' => $node['label'],
                    'offset_from_freshest_seconds' => null,
                    'staleness_seconds' => $node['staleness_seconds'],
                    'availability' => $node['availability'],
                    'missing_minutes' => $node['missing_minutes'],
                ];
                continue;
            }

            $nodeTs = $now->copy()->subSeconds((int) $node['staleness_seconds'])->timestamp;
            $nodeOffsets[] = [
                'node_id' => $node['node_id'],
                'label' => $node['label'],
                'offset_from_freshest_seconds' => max(0, $freshestTs - $nodeTs),
                'staleness_seconds' => $node['staleness_seconds'],
                'availability' => $node['availability'],
                'missing_minutes' => $node['missing_minutes'],
            ];
        }

        $score = (int) round(100 * $completeness);
        $score -= (int) min(30, floor($skewSeconds / 10));

        foreach ($nodeSummaries as $node) {
            if (($node['staleness_seconds'] ?? 999999) > $stalenessThresholdSeconds) {
                $score -= 10;
            }
        }

        $score -= (int) min(20, ($zWarnCount * 2) + ($zCriticalCount * 5));

        $score = max(0, min(100, $score));

        $notes = [];
        if ($completeness < 0.5) {
            $notes[] = 'Low alignment across nodes (missing minute-level overlaps)';
        }
        if ($skewSeconds > 60) {
            $notes[] = 'Clock skew / ingestion lag between nodes is noticeable';
        }
        if ($zCriticalCount > 0) {
            $notes[] = 'One or more signals are in critical anomaly range (z-score)';
        } elseif ($zWarnCount > 0) {
            $notes[] = 'Some signals are in warning anomaly range (z-score)';
        }

        return [
            'computedAt' => $now->setTimezone($timezone)->format('d.m.Y H:i:s'),
            'windowMinutes' => $windowMinutes,
            'thresholds' => [
                'z_warn' => $zWarn,
                'z_critical' => $zCritical,
                'staleness_threshold_s' => $stalenessThresholdSeconds,
            ],
            'anomalies' => [
                'warn_count' => $zWarnCount,
                'critical_count' => $zCriticalCount,
            ],
            'rawReadingsCount' => $readings->count(),
            'bucketCount' => $bucketCount,
            'nodeSummaries' => $nodeSummaries,
            'nodeDiagnostics' => [
                'freshest_node_timestamp' => $freshestNodeTimestamp ? $freshestNodeTimestamp->setTimezone($timezone)->format('d.m.Y H:i:s') : null,
                'node_offsets' => $nodeOffsets,
            ],
            'correlations' => array_values(array_filter($corr, fn ($r) => $r !== null)),
            'distributedHealth' => [
                'score' => $score,
                'completeness' => round($completeness, 3),
                'skew_seconds' => $skewSeconds,
                'notes' => $notes,
            ],
        ];
    }

    private function normalizeNodeKey(?string $nodeId, ?string $mqttTopic): ?string
    {
        $topic = $mqttTopic ?? '';

        // Prefer mqtt topic because it is stable across different node_id naming conventions.
        if ($topic !== '') {
            if (str_contains($topic, 'esp32_node1')) return 'esp32_node1';
            if (str_contains($topic, 'esp32_node2')) return 'esp32_node2';
            if (str_contains($topic, 'esp32_node3')) return 'esp32_node3';
        }

        $id = $nodeId ?? '';
        if ($id === '') {
            return null;
        }

        // Accept both formats: 'esp32_node1' and 'node-1' (Java seeder).
        if ($id === 'esp32_node1' || $id === 'node-1') return 'esp32_node1';
        if ($id === 'esp32_node2' || $id === 'node-2') return 'esp32_node2';
        if ($id === 'esp32_node3' || $id === 'node-3') return 'esp32_node3';

        return null;
    }

    private function stats(array $values): array
    {
        $values = array_values(array_filter($values, fn ($v) => $v !== null));
        $count = count($values);
        if ($count === 0) {
            return ['count' => 0, 'mean' => null, 'std' => null, 'min' => null, 'max' => null];
        }

        $min = min($values);
        $max = max($values);
        $mean = array_sum($values) / $count;

        $var = 0.0;
        foreach ($values as $v) {
            $var += ($v - $mean) * ($v - $mean);
        }
        $std = sqrt($var / $count);

        return [
            'count' => $count,
            'mean' => round($mean, 4),
            'std' => round($std, 4),
            'min' => round((float) $min, 4),
            'max' => round((float) $max, 4),
        ];
    }

    private function correlationRow(string $aLabel, array $aSeries, string $bLabel, array $bSeries): ?array
    {
        $keys = $this->commonKeys([$aSeries, $bSeries]);
        if (count($keys) < 5) {
            return null;
        }

        $xs = [];
        $ys = [];
        foreach ($keys as $k) {
            $xs[] = (float) $aSeries[$k];
            $ys[] = (float) $bSeries[$k];
        }

        $r = $this->pearson($xs, $ys);

        return [
            'a' => $aLabel,
            'b' => $bLabel,
            'r' => $r,
            'n' => count($keys),
        ];
    }

    private function commonKeys(array $seriesList): array
    {
        $keys = null;
        foreach ($seriesList as $series) {
            $k = array_keys($series);
            $keys = $keys === null ? $k : array_values(array_intersect($keys, $k));
        }

        $keys = $keys ?? [];
        sort($keys);
        return $keys;
    }

    private function pearson(array $xs, array $ys): ?float
    {
        $n = min(count($xs), count($ys));
        if ($n < 2) {
            return null;
        }

        $xs = array_slice($xs, 0, $n);
        $ys = array_slice($ys, 0, $n);

        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($ys) / $n;

        $num = 0.0;
        $denX = 0.0;
        $denY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $xs[$i] - $meanX;
            $dy = $ys[$i] - $meanY;
            $num += $dx * $dy;
            $denX += $dx * $dx;
            $denY += $dy * $dy;
        }

        $den = sqrt($denX * $denY);
        if ($den <= 0.0) {
            return null;
        }

        return round($num / $den, 4);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([
                'results' => [],
            ]);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $results = [];

        // Static pages (quick navigation)
        $pages = [
            [
                'kind' => 'page',
                'title' => 'Distributed Insights',
                'subtitle' => 'Cross-sensor distributed metrics',
                'href' => route('dashboard.distributed-insights'),
                'keywords' => ['distributed', 'insights', 'network', 'correlation', 'health'],
            ],
            [
                'kind' => 'page',
                'title' => 'Trends',
                'subtitle' => 'Time series overview',
                'href' => route('dashboard.trends'),
                'keywords' => ['trend', 'history', 'chart'],
            ],
            [
                'kind' => 'page',
                'title' => 'Anomalies',
                'subtitle' => 'Outliers and unusual readings',
                'href' => route('dashboard.anomalies'),
                'keywords' => ['anomaly', 'outlier', 'z'],
            ],
            [
                'kind' => 'page',
                'title' => 'Export',
                'subtitle' => 'Download data',
                'href' => route('dashboard.export'),
                'keywords' => ['export', 'csv', 'download'],
            ],
            [
                'kind' => 'page',
                'title' => 'App Settings',
                'subtitle' => 'Energy and distributed thresholds',
                'href' => route('app-settings.edit'),
                'keywords' => ['settings', 'energy', 'kwh', 'threshold'],
            ],
        ];

        $qLower = mb_strtolower($q);
        foreach ($pages as $page) {
            $haystack = mb_strtolower($page['title'] . ' ' . $page['subtitle'] . ' ' . implode(' ', $page['keywords']));
            if (str_contains($haystack, $qLower)) {
                $results[] = [
                    'kind' => $page['kind'],
                    'title' => $page['title'],
                    'subtitle' => $page['subtitle'],
                    'href' => $page['href'],
                ];
            }
        }

        $sensors = Sensor::query()
            ->where(function ($query) use ($like) {
                $query
                    ->where('name', 'like', $like)
                    ->orWhere('sensor_type', 'like', $like)
                    ->orWhere('node_id', 'like', $like)
                    ->orWhere('mqtt_topic', 'like', $like);
            })
            ->limit(8)
            ->get();

        foreach ($sensors as $sensor) {
            $results[] = [
                'kind' => 'sensor',
                'title' => $sensor->name ?? $sensor->sensor_type ?? 'Sensor',
                'subtitle' => trim(implode(' · ', array_filter([
                    $sensor->sensor_type,
                    $sensor->node_id,
                ]))),
                'href' => $this->sensorHref((string) $sensor->sensor_type),
            ];
        }

        // De-dup by href+title
        $deduped = [];
        $seen = [];
        foreach ($results as $r) {
            $key = ($r['href'] ?? '') . '|' . ($r['title'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $r;
        }

        return response()->json([
            'results' => array_slice($deduped, 0, 10),
        ]);
    }

    private function sensorHref(string $sensorType): string
    {
        $sensorType = mb_strtolower($sensorType);

        if (in_array($sensorType, ['temperatura', 'umiditate'], true)) {
            return route('dashboard.dht11');
        }

        if ($sensorType === 'umiditate_sol') {
            return route('dashboard.soil');
        }

        if ($sensorType === 'curent') {
            return route('dashboard.acs');
        }

        return route('dashboard');
    }
}

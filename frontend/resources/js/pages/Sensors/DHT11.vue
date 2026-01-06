<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { apiFetch } from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
  BarElement
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler, BarElement);

interface SensorReading { value: number; timestamp: string }
interface Sensor { id: number; node_id: string; name: string; type: string; unit: string; latest_value: number | null; latest_reading_at: string | null }

const sensors = ref<Sensor[]>([]);
const historicalData = ref<Record<number, SensorReading[]>>({});
const stats24h = ref<{ sensor_type: string; avg: number; min: number; max: number; unit: string }[]>([]);
const loading = ref(true);
const showSmoothing = ref(true);
const smoothingWindow = ref(5);
let intervalId: number | null = null;

const isDark = computed(() => document.documentElement.classList.contains('dark'));

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: { enabled: true } },
  scales: {
    x: { display: true, grid: { display: false } },
    y: { display: true, grid: { color: isDark.value ? 'rgba(51,65,85,.3)' : 'rgba(226,232,240,.8)' } },
  },
  interaction: { intersect: false, mode: 'index' as const },
}));

const getChartColor = (type: string, alpha = 1) => ({
  temperatura: `rgba(239,68,68,${alpha})`,
  umiditate: `rgba(59,130,246,${alpha})`,
}[type] || `rgba(107,114,128,${alpha})`);

const getSensorIcon = (type: string) => ({ temperatura: '🌡️', umiditate: '💧' }[type] || '📊');
const sortByTimestamp = (arr: SensorReading[]) => arr.slice().sort((a,b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());
const movingAverage = (values: Array<number | null>, window = 5) => {
  const out: Array<number | null> = [];
  const buf: number[] = [];
  for (const v of values) {
    if (v == null) {
      out.push(null);
      continue;
    }
    buf.push(v);
    if (buf.length > window) buf.shift();
    out.push(buf.reduce((a,b) => a+b, 0) / buf.length);
  }
  return out;
};
const rollingStats = (readings: SensorReading[], minutes = 30) => {
  const cutoff = Date.now() - minutes * 60_000;
  const recent = sortByTimestamp(readings).filter(r => new Date(r.timestamp).getTime() >= cutoff);
  if (!recent.length) return null;
  const vals = recent.map(r => r.value);
  const avg = vals.reduce((a,b) => a+b, 0) / vals.length;
  const min = Math.min(...vals), max = Math.max(...vals);
  const delta = vals[vals.length - 1] - vals[0];
  return { avg, min, max, delta, count: vals.length };
};
const zScoreForLatest = (readings: SensorReading[]) => {
  if (!readings.length) return null;
  const vals = readings.map(r => r.value);
  const mean = vals.reduce((a,b)=>a+b,0) / vals.length;
  const variance = vals.reduce((acc,v)=>acc + (v-mean)**2, 0) / Math.max(vals.length-1, 1);
  const std = Math.sqrt(variance);
  const latest = vals[vals.length-1];
  if (!std) return 0;
  return (latest - mean) / std;
};
const slopePerMinute = (readings: SensorReading[], minutes = 10) => {
  const cutoff = Date.now() - minutes * 60_000;
  const recent = sortByTimestamp(readings).filter(r => new Date(r.timestamp).getTime() >= cutoff);
  if (recent.length < 2) return null;
  const start = recent[0], end = recent[recent.length-1];
  const deltaMinutes = (new Date(end.timestamp).getTime() - new Date(start.timestamp).getTime()) / 60_000;
  if (deltaMinutes <= 0) return null;
  return (end.value - start.value) / deltaMinutes;
};

// --- Derived metrics ---
const tempSensor = computed(() => sensors.value.find(s => s.type === 'temperatura'));
const humSensor = computed(() => sensors.value.find(s => s.type === 'umiditate'));

const latestTemp = computed(() => tempSensor.value?.latest_value ?? null);
const latestHum = computed(() => humSensor.value?.latest_value ?? null);

// Heat index in Celsius
function heatIndexC(tempC: number, rh: number): number {
  if (isNaN(tempC) || isNaN(rh)) return NaN;
  const T = tempC * 9/5 + 32; // to F
  const R = rh;
  const HI = -42.379 + 2.04901523*T + 10.14333127*R - 0.22475541*T*R - 0.00683783*T*T - 0.05481717*R*R + 0.00122874*T*T*R + 0.00085282*T*R*R - 0.00000199*T*T*R*R;
  return (HI - 32) * 5/9; // back to C
}

// Dew point in Celsius (Magnus)
function dewPointC(tempC: number, rh: number): number {
  if (isNaN(tempC) || isNaN(rh) || rh <= 0) return NaN;
  const a = 17.27, b = 237.7;
  const gamma = (a * tempC) / (b + tempC) + Math.log(rh/100);
  return (b * gamma) / (a - gamma);
}

function comfortLevel(tempC?: number | null, rh?: number | null): string {
  if (tempC == null || rh == null) return 'N/A';
  if (tempC >= 26 && rh >= 60) return 'Foarte cald și umed';
  if (tempC >= 24 && rh >= 50) return 'Cald și umed';
  if (tempC >= 21 && rh <= 40) return 'Uscat';
  if (tempC >= 20 && rh >= 40 && rh <= 60) return 'Confortabil';
  if (tempC < 18 && rh > 60) return 'Răcoros și umed';
  return 'Neutral';
}

const fetchSensors = async () => {
  const res = await apiFetch('/api/sensors');
  const json = await res.json();
  if (json.success) {
    // Only DHT11 metrics: temperatura + umiditate
    sensors.value = (json.data as Sensor[]).filter(s => s.type === 'temperatura' || s.type === 'umiditate');
  }
  loading.value = false;
};

const fetchHistoricalData = async (sensorId: number) => {
  const res = await apiFetch(`/api/sensors/${sensorId}/readings?hours=2&limit=30`);
  const json = await res.json();
  if (json.success) historicalData.value[sensorId] = json.data.readings as SensorReading[];
};

const fetchStats24h = async () => {
  const res = await apiFetch('/api/sensors/statistics?hours=24');
  const json = await res.json();
  if (json.success) {
    const only = (json.data as any[]).filter(s => s.sensor_type === 'temperatura' || s.sensor_type === 'umiditate');
    stats24h.value = only;
  }
};

const getChartData = (sensorId: number) => {
  const readings = historicalData.value[sensorId] || [];
  return {
    labels: readings.map(r => new Date(r.timestamp).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' })).reverse(),
    datasets: [{
      data: readings.map(r => r.value).reverse(),
      borderColor: getChartColor(sensors.value.find(s => s.id === sensorId)?.type || ''),
      backgroundColor: getChartColor(sensors.value.find(s => s.id === sensorId)?.type || '', 0.1),
      tension: 0.4, fill: true, borderWidth: 2, pointRadius: 0
    }]
  };
};

onMounted(async () => {
  await fetchSensors();
  sensors.value.forEach(s => fetchHistoricalData(s.id));
  await fetchStats24h();
  intervalId = window.setInterval(() => sensors.value.forEach(s => fetchHistoricalData(s.id)), 3000);
});

onUnmounted(() => { if (intervalId) clearInterval(intervalId) });

// --- Chart helpers ---
const alignByNearest = (base: SensorReading[], other: SensorReading[], toleranceSec = 90) => {
  const out: Array<{ t: string; base: number | null; other: number | null }> = [];
  const others = other.map(r => ({ ts: new Date(r.timestamp).getTime(), v: r.value }));
  for (const r of base) {
    const ts = new Date(r.timestamp).getTime();
    let nearest: number | null = null;
    let minDiff = Infinity;
    for (const o of others) {
      const diff = Math.abs(o.ts - ts);
      if (diff < minDiff) { minDiff = diff; nearest = o.v; }
    }
    if (minDiff/1000 <= toleranceSec) {
      out.push({ t: r.timestamp, base: r.value, other: nearest });
    } else {
      out.push({ t: r.timestamp, base: r.value, other: null });
    }
  }
  return out;
};

const combinedSeries = computed(() => {
  const tId = tempSensor.value?.id; const hId = humSensor.value?.id;
  if (!tId || !hId) return { labels: [], temp: [], hum: [] };
  const t = sortByTimestamp(historicalData.value[tId] || []);
  const h = sortByTimestamp(historicalData.value[hId] || []);
  const aligned = alignByNearest(t, h);
  const labels = aligned.map(x => new Date(x.t).toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'}));
  const temp = aligned.map(x => x.base);
  const hum = aligned.map(x => x.other);
  return { labels, temp, hum };
});

const combinedChartData = computed(() => ({
  labels: combinedSeries.value.labels,
  datasets: [
    {
      label: 'Temperatură (°C)',
      data: combinedSeries.value.temp,
      borderColor: getChartColor('temperatura'),
      backgroundColor: getChartColor('temperatura', .1),
      yAxisID: 'yTemp', tension: .4, borderWidth: 2, pointRadius: 0, spanGaps: true,
    },
    {
      label: 'Umiditate (%)',
      data: combinedSeries.value.hum,
      borderColor: getChartColor('umiditate'),
      backgroundColor: getChartColor('umiditate', .1),
      yAxisID: 'yHum', tension: .4, borderWidth: 2, pointRadius: 0, spanGaps: true,
    },
  ]
}));

const smoothedCombinedData = computed(() => {
  const labels = combinedSeries.value.labels;
  const temp = movingAverage(combinedSeries.value.temp, smoothingWindow.value);
  const hum = movingAverage(combinedSeries.value.hum, smoothingWindow.value);
  return {
    labels,
    datasets: [
      {
        label: `Temp medie mobila (${smoothingWindow.value})`,
        data: temp,
        borderColor: 'rgba(239,68,68,0.7)',
        backgroundColor: 'rgba(239,68,68,0.08)',
        yAxisID: 'yTemp',
        borderWidth: 2,
        pointRadius: 0,
        borderDash: [6,4],
        spanGaps: true,
        tension: .25,
      },
      {
        label: `Umid medie mobila (${smoothingWindow.value})`,
        data: hum,
        borderColor: 'rgba(59,130,246,0.7)',
        backgroundColor: 'rgba(59,130,246,0.08)',
        yAxisID: 'yHum',
        borderWidth: 2,
        pointRadius: 0,
        borderDash: [6,4],
        spanGaps: true,
        tension: .25,
      }
    ]
  };
});

const combinedChartWithOverlay = computed(() => ({
  labels: combinedSeries.value.labels,
  datasets: [
    ...combinedChartData.value.datasets,
    ...(showSmoothing.value ? smoothedCombinedData.value.datasets : []),
  ]
}));

const tempStats30 = computed(() => tempSensor.value?.id ? rollingStats(historicalData.value[tempSensor.value.id] || [], 30) : null);
const humStats30 = computed(() => humSensor.value?.id ? rollingStats(historicalData.value[humSensor.value.id] || [], 30) : null);
const tempSlope = computed(() => tempSensor.value?.id ? slopePerMinute(historicalData.value[tempSensor.value.id] || [], 10) : null);
const humSlope = computed(() => humSensor.value?.id ? slopePerMinute(historicalData.value[humSensor.value.id] || [], 10) : null);
const tempZ = computed(() => tempSensor.value?.id ? zScoreForLatest(sortByTimestamp(historicalData.value[tempSensor.value.id] || [])) : null);
const humZ = computed(() => humSensor.value?.id ? zScoreForLatest(sortByTimestamp(historicalData.value[humSensor.value.id] || [])) : null);

const liveAlerts = computed(() => {
  const alerts: string[] = [];
  if (latestTemp.value != null && latestTemp.value >= 30) alerts.push('Temperatură ridicată');
  if (latestTemp.value != null && latestTemp.value <= 5) alerts.push('Temperatură scăzută');
  if (latestHum.value != null && latestHum.value >= 75) alerts.push('Umiditate ridicată');
  if (latestHum.value != null && latestHum.value <= 25) alerts.push('Umiditate scăzută');
  if (tempSlope.value != null && Math.abs(tempSlope.value) >= 0.5) alerts.push('Variație rapidă temperatură');
  if (humSlope.value != null && Math.abs(humSlope.value) >= 1) alerts.push('Variație rapidă umiditate');
  return alerts;
});

const dualAxesOptions = computed((): any => ({
  ...chartOptions.value,
  plugins: { ...chartOptions.value.plugins, legend: { display: true } },
  scales: {
    x: { ...(chartOptions.value as any).scales?.x },
    yTemp: { type: 'linear' as const, position: 'left' as const, grid: { display: false } },
    yHum: { type: 'linear' as const, position: 'right' as const, grid: { display: false } },
  }
}));

const heatIndexSeries = computed(() => {
  const tId = tempSensor.value?.id; const hId = humSensor.value?.id;
  if (!tId || !hId) return { labels: [], hi: [] as (number|null)[] };
  const t = sortByTimestamp(historicalData.value[tId] || []);
  const h = sortByTimestamp(historicalData.value[hId] || []);
  const aligned = alignByNearest(t, h);
  const labels = aligned.map(x => new Date(x.t).toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'}));
  const hi = aligned.map(x => (x.base != null && x.other != null) ? heatIndexC(x.base, x.other) : null);
  return { labels, hi };
});

const heatIndexChartData = computed(() => ({
  labels: heatIndexSeries.value.labels,
  datasets: [{
    label: 'Heat Index (°C)',
    data: heatIndexSeries.value.hi,
    borderColor: 'rgba(245,158,11,1)',
    backgroundColor: 'rgba(245,158,11,0.15)',
    tension: .4, borderWidth: 2, pointRadius: 0, spanGaps: true, fill: true,
  }]
}));

const tempHistData = computed(() => {
  const tId = tempSensor.value?.id; if (!tId) return { labels: [], counts: [] };
  const vals = (historicalData.value[tId] || []).map(r => r.value);
  if (vals.length === 0) return { labels: [], counts: [] };
  const min = Math.floor(Math.min(...vals));
  const max = Math.ceil(Math.max(...vals));
  const labels: string[] = []; const counts: number[] = [];
  for (let b=min; b<=max; b++) { labels.push(`${b}–${b+1}`); counts.push(0); }
  for (const v of vals) { const idx = Math.min(Math.max(Math.floor(v - min), 0), counts.length-1); counts[idx]++; }
  return { labels, counts };
});
</script>

<template>
  <AppLayout>
    <Head title="DHT11 • Temperatură & Umiditate" />
    <div class="min-h-screen bg-white dark:bg-black">
      <div class="container mx-auto px-6 py-8 max-w-7xl">
        <div class="mb-6">
          <h1 class="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <span></span> DHT11 • Temperatură & Umiditate
          </h1>
          <p class="text-sm text-slate-600 dark:text-slate-400">Date live de la senzorul DHT11</p>
        </div>

        <div v-if="loading" class="py-20 text-center text-slate-500">Încărcare...</div>

        <div v-else class="space-y-8">
          <!-- KPIs -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Temperatură</CardTitle></CardHeader>
              <CardContent class="flex items-end justify-between">
                <div class="flex items-baseline gap-2">
                  <span class="text-4xl font-black">{{ latestTemp?.toFixed(1) ?? '--' }}</span>
                  <span class="text-sm opacity-70">°C</span>
                </div>
                <div class="text-xs text-slate-500">
                  <div>Ultima</div>
                  <div class="font-medium">{{ tempSensor?.latest_reading_at ? new Date(tempSensor.latest_reading_at).toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'}) : '--' }}</div>
                </div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Umiditate</CardTitle></CardHeader>
              <CardContent class="flex items-end justify-between">
                <div class="flex items-baseline gap-2">
                  <span class="text-4xl font-black">{{ latestHum?.toFixed(1) ?? '--' }}</span>
                  <span class="text-sm opacity-70">%</span>
                </div>
                <div class="text-xs text-slate-500">
                  <div>Ultima</div>
                  <div class="font-medium">{{ humSensor?.latest_reading_at ? new Date(humSensor.latest_reading_at).toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'}) : '--' }}</div>
                </div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Heat Index</CardTitle></CardHeader>
              <CardContent>
                <div class="text-3xl font-extrabold">{{ (latestTemp!=null && latestHum!=null) ? heatIndexC(latestTemp, latestHum).toFixed(1) : '--' }} <span class="text-sm font-semibold opacity-70">°C</span></div>
                <div class="text-xs text-slate-500">Estimare percepută</div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Punct de rouă</CardTitle></CardHeader>
              <CardContent>
                <div class="text-3xl font-extrabold">{{ (latestTemp!=null && latestHum!=null) ? dewPointC(latestTemp, latestHum).toFixed(1) : '--' }} <span class="text-sm font-semibold opacity-70">°C</span></div>
                <div class="text-xs text-slate-500">Condiție: {{ comfortLevel(latestTemp, latestHum) }}</div>
              </CardContent>
            </Card>
          </div>

          <!-- Smoothing & alerts -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1">
                <CardTitle class="text-lg font-bold">Filtrare / netezire</CardTitle>
                <CardDescription>Medie mobilă + monitorizare outliers</CardDescription>
              </CardHeader>
              <CardContent class="space-y-3">
                <div class="flex flex-wrap gap-3 items-center">
                  <label class="flex items-center gap-2 text-sm">
                    <input v-model="showSmoothing" type="checkbox" class="rounded border-slate-300 dark:border-zinc-800">
                    Afișează serii netezite
                  </label>
                  <label class="flex items-center gap-2 text-sm">
                    Fereastră:
                    <select v-model.number="smoothingWindow" class="rounded-md border border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-2 py-1 text-sm">
                      <option :value="3">3</option>
                      <option :value="5">5</option>
                      <option :value="7">7</option>
                      <option :value="9">9</option>
                    </select>
                    citiri
                  </label>
                  <div class="flex gap-2 items-center text-xs">
                    <Badge :class="Math.abs(tempZ ?? 0) >= 3 ? 'bg-red-500/10 text-red-600 border-red-500/40' : 'bg-green-500/10 text-green-700 border-green-500/30'" variant="outline">
                      Z temp: {{ tempZ?.toFixed(2) ?? '--' }}
                    </Badge>
                    <Badge :class="Math.abs(humZ ?? 0) >= 3 ? 'bg-red-500/10 text-red-600 border-red-500/40' : 'bg-blue-500/10 text-blue-700 border-blue-500/30'" variant="outline">
                      Z umid: {{ humZ?.toFixed(2) ?? '--' }}
                    </Badge>
                  </div>
                </div>
                <div class="text-xs text-slate-500">
                  Medie mobilă aplicată pe ultimele valori, utilă pentru a reduce zgomotul. Z-score indică depărtarea ultimei valori față de media locală.
                </div>
              </CardContent>
            </Card>

            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1">
                <CardTitle class="text-lg font-bold">Tendințe & alerte live</CardTitle>
                <CardDescription>Δ pe 10 min și alerte simple</CardDescription>
              </CardHeader>
              <CardContent class="space-y-3">
                <div class="grid grid-cols-2 gap-3 text-sm">
                  <div class="p-3 rounded-lg bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800">
                    <div class="text-xs text-slate-500">Tendință temperatură (10m)</div>
                    <div class="text-lg font-semibold">{{ tempSlope != null ? tempSlope.toFixed(2) : '--' }} °C/min</div>
                  </div>
                  <div class="p-3 rounded-lg bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800">
                    <div class="text-xs text-slate-500">Tendință umiditate (10m)</div>
                    <div class="text-lg font-semibold">{{ humSlope != null ? humSlope.toFixed(2) : '--' }} %/min</div>
                  </div>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                  <template v-if="liveAlerts.length">
                    <Badge v-for="(a,idx) in liveAlerts" :key="idx" class="bg-amber-500/10 text-amber-700 border-amber-500/30" variant="outline">
                      {{ a }}
                    </Badge>
                  </template>
                  <Badge v-else class="bg-green-500/10 text-green-700 border-green-500/30" variant="outline">Fără alerte</Badge>
                </div>
              </CardContent>
            </Card>
          </div>

          <!-- Combined Temp/Humidity Chart -->
          <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
            <CardHeader class="pb-1">
              <CardTitle class="text-lg font-bold">Tendințe temperatură și umiditate (2h)</CardTitle>
              <CardDescription>Axă dublă, lacunele sunt permise când lipsesc perechi</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="h-56 bg-slate-50 dark:bg-zinc-900 rounded-lg p-3">
                <Line v-if="combinedSeries.labels.length" :data="combinedChartWithOverlay" :options="dualAxesOptions" />
              </div>
            </CardContent>
          </Card>

          <!-- Heat Index Trend -->
          <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
            <CardHeader class="pb-1">
              <CardTitle class="text-lg font-bold">Heat Index (2h)</CardTitle>
              <CardDescription>Valoare calculată din temperatură și umiditate</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="h-48 bg-slate-50 dark:bg-zinc-900 rounded-lg p-3">
                <Line v-if="heatIndexSeries.labels.length" :data="heatIndexChartData" :options="chartOptions" />
              </div>
            </CardContent>
          </Card>

          <!-- Rolling window stats -->
          <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
            <CardHeader class="pb-1">
              <CardTitle class="text-lg font-bold">Agregări ultimele 30 minute</CardTitle>
              <CardDescription>Medie, min, max și variație de capăt</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="p-3 rounded-lg bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800">
                  <div class="text-xs text-slate-500 mb-1">Temperatură</div>
                  <div>Medie: <span class="font-semibold">{{ tempStats30?.avg?.toFixed(1) ?? '--' }}</span> °C</div>
                  <div>Min / Max: <span class="font-semibold">{{ tempStats30?.min?.toFixed(1) ?? '--' }}</span> – <span class="font-semibold">{{ tempStats30?.max?.toFixed(1) ?? '--' }}</span> °C</div>
                  <div>Δ pe fereastră: <span class="font-semibold">{{ tempStats30?.delta?.toFixed(1) ?? '--' }}</span> °C</div>
                  <div class="text-xs text-slate-500">Citiri: {{ tempStats30?.count ?? 0 }}</div>
                </div>
                <div class="p-3 rounded-lg bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800">
                  <div class="text-xs text-slate-500 mb-1">Umiditate</div>
                  <div>Medie: <span class="font-semibold">{{ humStats30?.avg?.toFixed(1) ?? '--' }}</span> %</div>
                  <div>Min / Max: <span class="font-semibold">{{ humStats30?.min?.toFixed(1) ?? '--' }}</span> – <span class="font-semibold">{{ humStats30?.max?.toFixed(1) ?? '--' }}</span> %</div>
                  <div>Δ pe fereastră: <span class="font-semibold">{{ humStats30?.delta?.toFixed(1) ?? '--' }}</span> %</div>
                  <div class="text-xs text-slate-500">Citiri: {{ humStats30?.count ?? 0 }}</div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Reports: 24h Stats & Distribution -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950 lg:col-span-2">
              <CardHeader class="pb-1">
                <CardTitle class="text-lg font-bold">Raport 24h</CardTitle>
                <CardDescription>Medie, minim, maxim</CardDescription>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="p-3 rounded-lg bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800">
                    <div class="text-xs text-slate-500">Temperatură</div>
                    <div class="mt-1 text-sm">
                      <div>Medie: <span class="font-semibold">{{ stats24h.find(s=>s.sensor_type==='temperatura')?.avg?.toFixed(1) ?? '--' }}</span> °C</div>
                      <div>Min: <span class="font-semibold">{{ stats24h.find(s=>s.sensor_type==='temperatura')?.min?.toFixed(1) ?? '--' }}</span> °C</div>
                      <div>Max: <span class="font-semibold">{{ stats24h.find(s=>s.sensor_type==='temperatura')?.max?.toFixed(1) ?? '--' }}</span> °C</div>
                    </div>
                  </div>
                  <div class="p-3 rounded-lg bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800">
                    <div class="text-xs text-slate-500">Umiditate</div>
                    <div class="mt-1 text-sm">
                      <div>Medie: <span class="font-semibold">{{ stats24h.find(s=>s.sensor_type==='umiditate')?.avg?.toFixed(1) ?? '--' }}</span> %</div>
                      <div>Min: <span class="font-semibold">{{ stats24h.find(s=>s.sensor_type==='umiditate')?.min?.toFixed(1) ?? '--' }}</span> %</div>
                      <div>Max: <span class="font-semibold">{{ stats24h.find(s=>s.sensor_type==='umiditate')?.max?.toFixed(1) ?? '--' }}</span> %</div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950 lg:col-span-1">
              <CardHeader class="pb-1">
                <CardTitle class="text-lg font-bold">Distribuție temperatură (2h)</CardTitle>
                <CardDescription>Bucket ~ 1°C</CardDescription>
              </CardHeader>
              <CardContent>
                <div class="text-xs text-slate-500" v-if="!tempHistData.labels.length">Insuficiente date</div>
                <div class="grid grid-cols-1 gap-1" v-else>
                  <div v-for="(label,idx) in tempHistData.labels" :key="'b'+idx" class="flex items-center gap-2">
                    <div class="w-16 text-xs text-slate-600 dark:text-slate-400">{{ label }}</div>
                    <div class="flex-1 h-2 bg-slate-200 dark:bg-zinc-900 rounded">
                      <div class="h-2 rounded bg-red-500" :style="{ width: Math.min((tempHistData.counts[idx] / Math.max(...tempHistData.counts)) * 100, 100) + '%' }"></div>
                    </div>
                    <div class="w-8 text-right text-xs">{{ tempHistData.counts[idx] }}</div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

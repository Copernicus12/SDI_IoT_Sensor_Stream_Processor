<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { apiFetch } from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js';
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

interface SensorReading { value: number; timestamp: string }
interface Sensor { id: number; node_id: string; name: string; type: string; unit: string; latest_value: number | null; latest_reading_at: string | null }

const sensors = ref<Sensor[]>([]);
const historicalData = ref<Record<number, SensorReading[]>>({});
const stats24h = ref<{ sensor_type: string; avg: number; min: number; max: number; unit: string }[]>([]);
const loading = ref(true);
let intervalId: number | null = null;

const isDark = computed(() => document.documentElement.classList.contains('dark'));

const chartOptions = computed(() => ({
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: { enabled: true } },
  scales: { x: { display: true, grid: { display: false } }, y: { display: true, grid: { color: isDark.value ? 'rgba(51,65,85,.3)' : 'rgba(226,232,240,.8)' } } },
  interaction: { intersect: false, mode: 'index' as const },
}));

const getChartColor = (alpha = 1) => `rgba(34,197,94,${alpha})`;

const soilSensor = computed(() => sensors.value.find(s => s.type === 'umiditate_sol'));
const latestSoil = computed(() => soilSensor.value?.latest_value ?? null);

// thresholds (you can tune): dry <30, optimal 30-60, wet >60
function soilStatus(value?: number | null): 'uscat' | 'optim' | 'ud' | 'necunoscut' {
  if (value == null || isNaN(value)) return 'necunoscut';
  if (value < 30) return 'uscat';
  if (value <= 60) return 'optim';
  return 'ud';
}

function wateringHint(status: ReturnType<typeof soilStatus>): string {
  switch (status) {
    case 'uscat': return 'Recomandat udat';
    case 'optim': return 'Bun, nu este necesar';
    case 'ud': return 'Prea umed, evita udarea';
    default: return 'Fără recomandare';
  }
}

const fetchSensors = async () => {
  const res = await apiFetch('/api/sensors');
  const json = await res.json();
  if (json.success) sensors.value = (json.data as Sensor[]).filter(s => s.type === 'umiditate_sol');
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
    const only = (json.data as any[]).filter(s => s.sensor_type === 'umiditate_sol');
    stats24h.value = only;
  }
};

const getChartData = (sensorId: number) => {
  const readings = historicalData.value[sensorId] || [];
  return {
    labels: readings.map(r => new Date(r.timestamp).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' })).reverse(),
    datasets: [{ data: readings.map(r => r.value).reverse(), borderColor: getChartColor(), backgroundColor: getChartColor(.1), tension: .4, fill: true, borderWidth: 2, pointRadius: 0 }]
  };
};

onMounted(async () => {
  await fetchSensors();
  sensors.value.forEach(s => fetchHistoricalData(s.id));
  await fetchStats24h();
  intervalId = window.setInterval(() => sensors.value.forEach(s => fetchHistoricalData(s.id)), 3000);
});

onUnmounted(() => { if (intervalId) clearInterval(intervalId) });

// rolling average 30 min and time-below-threshold from last 2h
const series = computed(() => {
  const id = soilSensor.value?.id; if (!id) return [] as SensorReading[];
  return (historicalData.value[id] || []).slice().reverse(); // ascending time
});

const rollingAvg30 = computed(() => {
  const now = Date.now();
  const cutoff = now - 30 * 60 * 1000;
  const vals = series.value.filter(r => new Date(r.timestamp).getTime() >= cutoff).map(r => r.value);
  if (vals.length === 0) return null;
  return vals.reduce((a,b)=>a+b,0) / vals.length;
});

const pctBelow30 = computed(() => {
  const s = series.value; if (s.length < 2) return 0;
  let underMs = 0, totalMs = 0;
  for (let i=1;i<s.length;i++){
    const t0 = new Date(s[i-1].timestamp).getTime();
    const t1 = new Date(s[i].timestamp).getTime();
    const dt = Math.max(0, t1 - t0);
    totalMs += dt;
    if (s[i-1].value < 30) underMs += dt;
  }
  return totalMs ? Math.round((underMs/totalMs)*100) : 0;
});

const soilHistData = computed(() => {
  const vals = series.value.map(r => r.value);
  if (!vals.length) return { labels: [], counts: [] as number[] };
  const buckets = [0,10,20,30,40,50,60,70,80,90];
  const counts = new Array(buckets.length).fill(0);
  for (const v of vals) {
    const idx = Math.min(Math.floor(v/10), buckets.length-1);
    counts[idx]++;
  }
  const labels = buckets.map(b => `${b}–${b+10}%`);
  return { labels, counts };
});
</script>

<template>
  <AppLayout>
    <Head title="Umiditate Sol" />
    <div class="min-h-screen bg-white dark:bg-black">
      <div class="container mx-auto px-6 py-8 max-w-7xl">
        <div class="mb-6">
          <h1 class="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <span>🌱</span> Umiditate Sol
          </h1>
          <p class="text-sm text-slate-600 dark:text-slate-400">Monitorizare live umiditate sol</p>
        </div>

        <div v-if="loading" class="py-20 text-center text-slate-500">Încărcare...</div>

        <div v-else class="space-y-8">
          <!-- KPIs -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Umiditate sol</CardTitle></CardHeader>
              <CardContent class="flex items-end justify-between">
                <div class="flex items-baseline gap-2">
                  <span class="text-4xl font-black">{{ latestSoil?.toFixed(1) ?? '--' }}</span>
                  <span class="text-sm opacity-70">%</span>
                </div>
                <div class="text-right text-xs text-slate-500">
                  <div>Ultima</div>
                  <div class="font-medium">{{ soilSensor?.latest_reading_at ? new Date(soilSensor.latest_reading_at).toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'}) : '--' }}</div>
                </div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Stare</CardTitle></CardHeader>
              <CardContent>
                <div class="text-3xl font-extrabold capitalize">{{ soilStatus(latestSoil) }}</div>
                <div class="text-xs text-slate-500">{{ wateringHint(soilStatus(latestSoil)) }}</div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Medie 30 min</CardTitle></CardHeader>
              <CardContent>
                <div class="text-3xl font-extrabold">{{ rollingAvg30?.toFixed(1) ?? '--' }} <span class="text-sm font-semibold opacity-70">%</span></div>
                <div class="text-xs text-slate-500">Stabilitate: {{ rollingAvg30==null ? '--' : Math.abs((latestSoil ?? 0) - rollingAvg30).toFixed(1) }}%</div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Sub 30% (2h)</CardTitle></CardHeader>
              <CardContent>
                <div class="text-3xl font-extrabold">{{ pctBelow30 }}%</div>
                <div class="text-xs text-slate-500">Timp din ultimele 2 ore</div>
              </CardContent>
            </Card>
          </div>

          <!-- Trend (2h) -->
          <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
            <CardHeader class="pb-1">
              <CardTitle class="text-lg font-bold">Tendință umiditate sol (2h)</CardTitle>
              <CardDescription>Linie cu umplere</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="h-48 bg-slate-50 dark:bg-zinc-900 rounded-lg p-2">
                <Line v-if="soilSensor?.id && historicalData[soilSensor.id]" :data="getChartData(soilSensor.id)" :options="chartOptions" />
              </div>
            </CardContent>
          </Card>

          <!-- Reports -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950 lg:col-span-2">
              <CardHeader class="pb-1">
                <CardTitle class="text-lg font-bold">Raport 24h</CardTitle>
                <CardDescription>Medie, minim, maxim</CardDescription>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="p-3 rounded-lg bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800">
                    <div class="text-xs text-slate-500">Umiditate sol</div>
                    <div class="mt-1 text-sm">
                      <div>Medie: <span class="font-semibold">{{ stats24h[0]?.avg?.toFixed(1) ?? '--' }}</span> %</div>
                      <div>Min: <span class="font-semibold">{{ stats24h[0]?.min?.toFixed(1) ?? '--' }}</span> %</div>
                      <div>Max: <span class="font-semibold">{{ stats24h[0]?.max?.toFixed(1) ?? '--' }}</span> %</div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950 lg:col-span-1">
              <CardHeader class="pb-1">
                <CardTitle class="text-lg font-bold">Distribuție (2h)</CardTitle>
                <CardDescription>Bucket 10%</CardDescription>
              </CardHeader>
              <CardContent>
                <div class="text-xs text-slate-500" v-if="!soilHistData.labels.length">Insuficiente date</div>
                <div class="grid grid-cols-1 gap-1" v-else>
                  <div v-for="(label,idx) in soilHistData.labels" :key="'b'+idx" class="flex items-center gap-2">
                    <div class="w-16 text-xs text-slate-600 dark:text-slate-400">{{ label }}</div>
                    <div class="flex-1 h-2 bg-slate-200 dark:bg-zinc-900 rounded">
                      <div class="h-2 rounded bg-green-500" :style="{ width: Math.min((soilHistData.counts[idx] / Math.max(...soilHistData.counts)) * 100, 100) + '%' }"></div>
                    </div>
                    <div class="w-8 text-right text-xs">{{ soilHistData.counts[idx] }}</div>
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

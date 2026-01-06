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

const getChartColor = (alpha = 1) => `rgba(234,179,8,${alpha})`;
const VOLTAGE = 230; // AC mains approximation

const fetchSensors = async () => {
  const res = await apiFetch('/api/sensors');
  const json = await res.json();
  if (json.success) sensors.value = (json.data as Sensor[]).filter(s => s.type === 'curent');
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
    const only = (json.data as any[]).filter(s => s.sensor_type === 'curent');
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

const acsSensor = computed(() => sensors.value.find(s => s.type === 'curent'));
const latestI = computed(() => acsSensor.value?.latest_value ?? null);
const watts = computed(() => (latestI.value ?? 0) * VOLTAGE);

// ascending time series for active sensor
const series = computed(() => {
  const id = acsSensor.value?.id; if (!id) return [] as SensorReading[];
  return (historicalData.value[id] || []).slice().reverse();
});

// RMS on last 30 minutes
const rms30 = computed(() => {
  const now = Date.now();
  const cutoff = now - 30 * 60 * 1000;
  const vals = series.value.filter(r => new Date(r.timestamp).getTime() >= cutoff).map(r => r.value);
  if (vals.length === 0) return null;
  const meanSq = vals.reduce((a,b)=>a + b*b, 0) / vals.length;
  return Math.sqrt(meanSq);
});

// Peak over last 2h
const peak2h = computed(() => series.value.length ? Math.max(...series.value.map(r => r.value)) : null);

// Energy over last 2h via trapezoidal integration (Wh), assuming PF≈1
const energyWh2h = computed(() => {
  const s = series.value; if (s.length < 2) return 0;
  let wh = 0;
  for (let i=1;i<s.length;i++){
    const t0 = new Date(s[i-1].timestamp).getTime();
    const t1 = new Date(s[i].timestamp).getTime();
    const dtHours = Math.max(0, (t1 - t0) / 3600000);
    const p0 = VOLTAGE * s[i-1].value; // W
    const p1 = VOLTAGE * s[i].value;
    const pAvg = (p0 + p1) / 2;
    wh += pAvg * dtHours;
  }
  return Math.round(wh);
});

const estKWh24h = computed(() => {
  // approximate: average power from last 2h scaled to 24h
  const avgW = energyWh2h.value / 2; // Wh/2h -> W
  const wh24 = avgW * 24;
  return Math.max(0, wh24) / 1000;
});

// Current distribution (2h)
const currentHistData = computed(() => {
  const vals = series.value.map(r => r.value);
  if (!vals.length) return { labels: [], counts: [] as number[] };
  const edges = [0,0.5,1,2,3,5,8,10];
  const counts = new Array(edges.length).fill(0);
  for (const v of vals){
    let idx = edges.findIndex((e, i) => i < edges.length-1 ? (v >= e && v < edges[i+1]) : v >= e);
    if (idx === -1) idx = edges.length-1;
    counts[idx]++;
  }
  const labels = edges.map((e,i) => i<edges.length-1 ? `${e}–${edges[i+1]} A` : `${e}+ A`);
  return { labels, counts };
});

onMounted(async () => {
  await fetchSensors();
  sensors.value.forEach(s => fetchHistoricalData(s.id));
  await fetchStats24h();
  intervalId = window.setInterval(() => sensors.value.forEach(s => fetchHistoricalData(s.id)), 3000);
});

onUnmounted(() => { if (intervalId) clearInterval(intervalId) });
</script>

<template>
  <AppLayout>
    <Head title="ACS712 • Curent" />
    <div class="min-h-screen bg-white dark:bg-black">
      <div class="container mx-auto px-6 py-8 max-w-7xl">
        <div class="mb-6">
          <h1 class="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <span>⚡</span> ACS712 • Curent & Consum
          </h1>
          <p class="text-sm text-slate-600 dark:text-slate-400">Monitorizare curent AC și consum estimat</p>
        </div>

        <div v-if="loading" class="py-20 text-center text-slate-500">Încărcare...</div>

        <div v-else class="space-y-8">
          <!-- KPIs -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Curent</CardTitle></CardHeader>
              <CardContent class="flex items-end justify-between">
                <div class="flex items-baseline gap-2">
                  <span class="text-4xl font-black">{{ latestI?.toFixed(2) ?? '--' }}</span>
                  <span class="text-sm opacity-70">A</span>
                </div>
                <div class="text-right text-xs text-slate-500">
                  <div>Ultima</div>
                  <div class="font-medium">{{ acsSensor?.latest_reading_at ? new Date(acsSensor.latest_reading_at).toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'}) : '--' }}</div>
                </div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Putere (PF≈1)</CardTitle></CardHeader>
              <CardContent>
                <div class="text-3xl font-extrabold">{{ Math.round(watts) }} <span class="text-sm font-semibold opacity-70">W</span></div>
                <div class="text-xs text-slate-500">La {{ VOLTAGE }}V</div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">RMS (30 min)</CardTitle></CardHeader>
              <CardContent>
                <div class="text-3xl font-extrabold">{{ rms30?.toFixed(2) ?? '--' }} <span class="text-sm font-semibold opacity-70">A</span></div>
                <div class="text-xs text-slate-500">Curent eficace</div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Vârf (2h)</CardTitle></CardHeader>
              <CardContent>
                <div class="text-3xl font-extrabold">{{ peak2h?.toFixed(2) ?? '--' }} <span class="text-sm font-semibold opacity-70">A</span></div>
                <div class="text-xs text-slate-500">Maxim ultimelor 2 ore</div>
              </CardContent>
            </Card>
            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
              <CardHeader class="pb-1"><CardTitle class="text-sm font-semibold">Energie (2h)</CardTitle></CardHeader>
              <CardContent>
                <div class="text-3xl font-extrabold">{{ energyWh2h }} <span class="text-sm font-semibold opacity-70">Wh</span></div>
                <div class="text-xs text-slate-500">Est. 24h: {{ estKWh24h.toFixed(2) }} kWh</div>
              </CardContent>
            </Card>
          </div>

          <!-- Trend (2h) -->
          <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
            <CardHeader class="pb-1">
              <CardTitle class="text-lg font-bold">Tendință curent (2h)</CardTitle>
              <CardDescription>Linie cu umplere</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="h-48 bg-slate-50 dark:bg-zinc-900 rounded-lg p-2">
                <Line v-if="acsSensor?.id && historicalData[acsSensor.id]" :data="getChartData(acsSensor.id)" :options="chartOptions" />
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
                    <div class="text-xs text-slate-500">Curent</div>
                    <div class="mt-1 text-sm">
                      <div>Medie: <span class="font-semibold">{{ stats24h[0]?.avg?.toFixed(2) ?? '--' }}</span> A</div>
                      <div>Min: <span class="font-semibold">{{ stats24h[0]?.min?.toFixed(2) ?? '--' }}</span> A</div>
                      <div>Max: <span class="font-semibold">{{ stats24h[0]?.max?.toFixed(2) ?? '--' }}</span> A</div>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950 lg:col-span-1">
              <CardHeader class="pb-1">
                <CardTitle class="text-lg font-bold">Distribuție (2h)</CardTitle>
                <CardDescription>Benzi curent</CardDescription>
              </CardHeader>
              <CardContent>
                <div class="text-xs text-slate-500" v-if="!currentHistData.labels.length">Insuficiente date</div>
                <div class="grid grid-cols-1 gap-1" v-else>
                  <div v-for="(label,idx) in currentHistData.labels" :key="'c'+idx" class="flex items-center gap-2">
                    <div class="w-24 text-xs text-slate-600 dark:text-slate-400">{{ label }}</div>
                    <div class="flex-1 h-2 bg-slate-200 dark:bg-zinc-900 rounded">
                      <div class="h-2 rounded bg-yellow-500" :style="{ width: Math.min((currentHistData.counts[idx] / Math.max(...currentHistData.counts)) * 100, 100) + '%' }"></div>
                    </div>
                    <div class="w-8 text-right text-xs">{{ currentHistData.counts[idx] }}</div>
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

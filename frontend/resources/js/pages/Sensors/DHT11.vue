<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { apiFetch } from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
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
  TimeScale
} from 'chart.js';
import { 
    Thermometer, 
    Droplets, 
    Sun, 
    CloudFog, 
    Activity,
    ArrowUp,
    ArrowDown,
    Minus,
    Smile,
    Frown,
    Meh,
    Zap,
    Wind,
    Info,
    TrendingUp,
    TrendingDown,
    RefreshCw
} from 'lucide-vue-next';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler, TimeScale);

// --- Interfaces ---
interface SensorReading { value: number; timestamp: string }
interface Sensor { 
    id: number; 
    name: string; 
    type: string; 
    unit: string; 
    latest_value: number | null; 
    latest_reading_at: string | null 
}

// --- State ---
const sensors = ref<Sensor[]>([]);
const historicalData = ref<Record<number, SensorReading[]>>({});
const stats24h = ref<Record<string, { avg: number; min: number; max: number }>>({});
const loading = ref(true);
const chartPeriod = ref(6); // Default 6 hours
let intervalId: number | null = null;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Environment (DHT11)', href: '/dashboard/dht11' },
];

const periods = [
    { label: '1H', value: 1 },
    { label: '6H', value: 6 },
    { label: '12H', value: 12 },
    { label: '24H', value: 24 },
];

const isDark = computed(() => document.documentElement.classList.contains('dark'));

// --- Chart Options ---
const getChartOptions = (color: string) => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            mode: 'index' as const,
            intersect: false,
            backgroundColor: isDark.value ? '#18181b' : '#ffffff',
            titleColor: isDark.value ? '#f4f4f5' : '#18181b',
            bodyColor: isDark.value ? '#a1a1aa' : '#52525b',
            borderColor: isDark.value ? '#27272a' : '#e4e4e7',
            borderWidth: 1,
            padding: 10,
            displayColors: false,
            callbacks: {
                label: (context: any) => `${context.parsed.y.toFixed(1)}`
            }
        }
    },
    scales: {
        x: {
            display: false,
        },
        y: {
            display: true,
            grid: {
                color: isDark.value ? 'rgba(39, 39, 42, 0.5)' : 'rgba(228, 228, 231, 0.5)',
                drawBorder: false,
            },
            ticks: {
                color: isDark.value ? '#71717a' : '#a1a1aa',
                font: { size: 10 }
            }
        }
    },
    interaction: {
        mode: 'nearest' as const,
        axis: 'x' as const,
        intersect: false
    },
    elements: {
        line: {
            tension: 0.4,
            borderWidth: 2,
            borderColor: color,
            backgroundColor: (context: any) => {
                const ctx = context.chart.ctx;
                const gradient = ctx.createLinearGradient(0, 0, 0, 200);
                if (color.startsWith('rgb')) {
                    gradient.addColorStop(0, color.replace('rgb', 'rgba').replace(')', ', 0.2)'));
                    gradient.addColorStop(1, color.replace('rgb', 'rgba').replace(')', ', 0)'));
                } else {
                    gradient.addColorStop(0, color);
                    gradient.addColorStop(1, color);
                }
                return gradient;
            },
            fill: true,
        },
        point: {
            radius: 0,
            hoverRadius: 4,
            backgroundColor: color
        }
    }
});

// --- Computed Metrics ---
const tempSensor = computed(() => sensors.value.find(s => s.type === 'temperatura'));
const humSensor = computed(() => sensors.value.find(s => s.type === 'umiditate'));

const latestTemp = computed(() => tempSensor.value?.latest_value ?? null);
const latestHum = computed(() => humSensor.value?.latest_value ?? null);

const tempReadings = computed(() => tempSensor.value ? (historicalData.value[tempSensor.value.id] || []) : []);
const humReadings = computed(() => humSensor.value ? (historicalData.value[humSensor.value.id] || []) : []);

// --- Environmental Calculations ---
function calculateHeatIndex(temp: number, rh: number): number {
    if (!temp || !rh) return 0;
    const T = temp * 9/5 + 32;
    const R = rh;
    const HI = -42.379 + 2.04901523*T + 10.14333127*R - 0.22475541*T*R - 0.00683783*T*T - 0.05481717*R*R + 0.00122874*T*T*R + 0.00085282*T*R*R - 0.00000199*T*T*R*R;
    return (HI - 32) * 5/9;
}

function calculateDewPoint(temp: number, rh: number): number {
    if (!temp || !rh) return 0;
    const a = 17.27, b = 237.7;
    const gamma = (a * temp) / (b + temp) + Math.log(rh/100);
    return (b * gamma) / (a - gamma);
}

function calculateAbsoluteHumidity(temp: number, rh: number): number {
    // Returns g/m³
    if (!temp || !rh) return 0;
    const e = 6.112 * Math.exp((17.67 * temp) / (temp + 243.5));
    const numerator = 216.7 * (rh * e / 100);
    const denominator = 273.15 + temp;
    return numerator / denominator;
}

const heatIndex = computed(() => (latestTemp.value && latestHum.value) ? calculateHeatIndex(latestTemp.value, latestHum.value) : null);
const dewPoint = computed(() => (latestTemp.value && latestHum.value) ? calculateDewPoint(latestTemp.value, latestHum.value) : null);
const absoluteHum = computed(() => (latestTemp.value && latestHum.value) ? calculateAbsoluteHumidity(latestTemp.value, latestHum.value) : null);

const comfortStatus = computed(() => {
    const t = latestTemp.value;
    const h = latestHum.value;
    if (t === null || h === null) return { label: 'Unknown', color: 'text-gray-500', icon: Minus, bg: 'bg-gray-100 dark:bg-zinc-800' };
    
    if (t >= 28 && h >= 60) return { label: 'Tropical', color: 'text-rose-500', icon: Frown, bg: 'bg-rose-100 dark:bg-rose-900/20' };
    if (t >= 24 && h >= 50) return { label: 'Warm & Humid', color: 'text-orange-500', icon: Meh, bg: 'bg-orange-100 dark:bg-orange-900/20' };
    if (t >= 21 && h <= 40) return { label: 'Dry', color: 'text-yellow-500', icon: Sun, bg: 'bg-yellow-100 dark:bg-yellow-900/20' };
    if (t >= 20 && h >= 40 && h <= 60) return { label: 'Optimal', color: 'text-emerald-500', icon: Smile, bg: 'bg-emerald-100 dark:bg-emerald-900/20' };
    if (t < 18 && h > 60) return { label: 'Cold & Damp', color: 'text-cyan-500', icon: CloudFog, bg: 'bg-cyan-100 dark:bg-cyan-900/20' };
    return { label: 'Neutral', color: 'text-blue-500', icon: Meh, bg: 'bg-blue-100 dark:bg-blue-900/20' };
});

// --- Simple Trend Analysis ---
function calculateTrend(readings: SensorReading[]) {
    if (readings.length < 5) return { direction: 'stable', value: 0 };
    
    // Simple slope of last 5 points
    const recent = readings.slice(-5);
    const latest = recent[recent.length-1].value;
    const first = recent[0].value;
    const diff = latest - first;
    
    // Thresholds per minute (approx, assuming 30s-1m updates this is crude but effective for demo)
    if (Math.abs(diff) < 0.2) return { direction: 'stable', value: diff };
    return { direction: diff > 0 ? 'rising' : 'falling', value: Math.abs(diff) };
}

const tempTrend = computed(() => calculateTrend(tempReadings.value));
const humTrend = computed(() => calculateTrend(humReadings.value));

// --- Recommendations System ---
const recommendations = computed(() => {
    const recs = [];
    const t = latestTemp.value;
    const h = latestHum.value;

    if (t && h) {
        if (h > 65) recs.push({ type: 'warning', text: 'High humidity detected. Mold risk increased. Consider ventilation.' });
        if (h < 30) recs.push({ type: 'warning', text: 'Air is too dry. May cause respiratory discomfort.' });
        if (t > 28) recs.push({ type: 'alert', text: 'High temperature. Ensure cooling is active.' });
        if (dewPoint.value && dewPoint.value > 20) recs.push({ type: 'info', text: 'Dew point is high. It feels oppressive.' });
    }
    return recs;
});

// --- Chart Data Generators ---
const getChartData = (readings: SensorReading[], label: string, color: string) => {
    const sorted = [...readings].sort((a,b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());
    return {
        labels: sorted.map(r => new Date(r.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })),
        datasets: [{
            label,
            data: sorted.map(r => r.value),
            borderColor: color,
            fill: true,
            pointRadius: 0,
            pointHoverRadius: 6,
        }]
    };
};

const tempChartData = computed(() => getChartData(tempReadings.value, 'Temperature', 'rgb(249, 115, 22)'));
const humChartData = computed(() => getChartData(humReadings.value, 'Humidity', 'rgb(59, 130, 246)'));

// --- Advanced Data Processing & Analytics ---

// 1. Pearson Correlation Coefficient (Temperature vs Humidity)
// Returns value between -1 and 1. Close to -1 means strong inverse relationship (normal for weather).
const correlation = computed(() => {
    // We need to pair data points by timestamp approx
    const tReadings = tempReadings.value;
    const hReadings = humReadings.value;
    if (!tReadings.length || !hReadings.length) return 0;

    // Simple pairing by index if lengths match (assuming same sampling rate), 
    // or better, map one to another. For simplicity here, we assume arrays are largely synchronous
    // or just take the min length.
    const len = Math.min(tReadings.length, hReadings.length);
    if (len < 5) return 0;

    let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0, sumY2 = 0;
    for (let i = 0; i < len; i++) {
        const x = tReadings[i].value;
        const y = hReadings[i].value; // Ideally we'd match timestamps here
        sumX += x;
        sumY += y;
        sumXY += x * y;
        sumX2 += x * x;
        sumY2 += y * y;
    }

    const numerator = (len * sumXY) - (sumX * sumY);
    const denominator = Math.sqrt((len * sumX2 - sumX * sumX) * (len * sumY2 - sumY * sumY));
    
    return denominator === 0 ? 0 : numerator / denominator;
});

const correlationDescription = computed(() => {
    const r = correlation.value;
    if (Math.abs(r) < 0.3) return { text: "No significant correlation", color: "text-gray-500" };
    if (r > 0) return { text: "Positive Correlation (Unusual)", color: "text-amber-500" };
    return { text: "Inverse Correlation (Normal)", color: "text-emerald-500" };
});

// 2. Volatility Analysis (Standard Deviation)
const calculateStdDev = (readings: SensorReading[]) => {
    if (readings.length < 2) return 0;
    const values = readings.map(r => r.value);
    const mean = values.reduce((a, b) => a + b, 0) / values.length;
    const variance = values.reduce((a, b) => a + Math.pow(b - mean, 2), 0) / values.length;
    return Math.sqrt(variance);
};

const tempVolatility = computed(() => calculateStdDev(tempReadings.value));
const humVolatility = computed(() => calculateStdDev(humReadings.value));

// 3. Peak Time Analysis
const getPeakInfo = (readings: SensorReading[]) => {
    if (!readings.length) return { min: null, max: null };
    let min = readings[0], max = readings[0];
    for (const r of readings) {
        if (r.value < min.value) min = r;
        if (r.value > max.value) max = r;
    }
    return { min, max };
};

const tempPeaks = computed(() => getPeakInfo(tempReadings.value));


const fetchData = async () => {
    try {
        const res = await apiFetch('/api/sensors');
        const json = await res.json();
        sensors.value = json.data?.filter((s:any) => ['temperatura', 'umiditate'].includes(s.type)) ?? [];
        
        await fetchHistory();

        // Fetch Stats
        const statsRes = await apiFetch('/api/sensors/statistics?hours=24');
        const statsJson = await statsRes.json();
        if (statsJson.success) {
            statsJson.data.forEach((st: any) => {
                stats24h.value[st.sensor_type] = { avg: st.avg, min: st.min, max: st.max };
            });
        }
    } catch (e) {
        console.error("Error fetching DHT11 data", e);
    } finally {
        loading.value = false;
    }
};

const fetchHistory = async () => {
     // Fetch history for charts based on selected period and limit based on density needed
     // 1h -> need ~60 points. 24h -> need ~200 points to not overload chart
     const limit = chartPeriod.value * 20; 
     for (const s of sensors.value) {
        const hRes = await apiFetch(`/api/sensors/${s.id}/readings?hours=${chartPeriod.value}&limit=${limit}`);
        const hJson = await hRes.json();
        if (hJson.success) {
            historicalData.value[s.id] = hJson.data.readings;
        }
    }
}

watch(chartPeriod, () => {
    fetchHistory();
});

onMounted(() => {
    fetchData();
    intervalId = window.setInterval(fetchData, 30000);
});

onUnmounted(() => {
    if (intervalId) clearInterval(intervalId);
});
</script>

<template>
    <Head title="Environment Monitor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Activity class="w-6 h-6 text-indigo-500" />
                        Live Environment Monitor
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
                        Real-time telemetry and advanced atmospheric analysis.
                    </p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1 bg-white dark:bg-zinc-900 p-1 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-sm">
                        <button 
                            v-for="p in periods" 
                            :key="p.value"
                            @click="chartPeriod = p.value"
                            :class="[
                                'px-3 py-1 text-xs font-medium rounded-md transition-colors',
                                chartPeriod === p.value 
                                    ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400' 
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                            ]"
                        >
                            {{ p.label }}
                        </button>
                    </div>

                    <div class="flex items-center gap-2 text-sm text-gray-500 bg-white dark:bg-zinc-900 px-3 py-1.5 rounded-full border border-gray-200 dark:border-zinc-800 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="hidden sm:inline">Live</span>
                    </div>
                </div>
            </div>

            <!-- Smart Recommendations Banner -->
            <div v-if="recommendations.length" class="grid grid-cols-1 gap-2">
                <div 
                    v-for="(rec, idx) in recommendations" 
                    :key="idx"
                    :class="[
                        'rounded-lg px-4 py-3 text-sm flex items-center gap-3 border',
                        rec.type === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200' :
                        rec.type === 'alert' ? 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-200' :
                        rec.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-200' :
                        'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-200'
                    ]"
                >
                    <Info class="w-4 h-4 shrink-0" v-if="rec.type === 'info'" />
                    <Zap class="w-4 h-4 shrink-0" v-if="rec.type === 'warning' || rec.type === 'alert'" />
                    <Smile class="w-4 h-4 shrink-0" v-if="rec.type === 'success'" />
                    <span class="font-medium">{{ rec.text }}</span>
                </div>
            </div>

            <!-- Derived Metrics (Comfort + Advanced) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Comfort Level -->
                <div :class="['rounded-xl border p-4 shadow-sm flex items-center justify-between relative overflow-hidden', comfortStatus.bg, 'border-transparent']">
                    <div class="z-10 relative">
                         <p class="text-xs font-medium opacity-70 uppercase tracking-wider mb-1">Comfort Index</p>
                         <h3 :class="['text-xl font-bold flex items-center gap-2', comfortStatus.color]">
                            {{ comfortStatus.label }}
                         </h3>
                    </div>
                    <component :is="comfortStatus.icon" :class="['w-10 h-10 opacity-20', comfortStatus.color]" />
                </div>

                 <!-- Heat Index -->
                 <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 shadow-sm">
                    <div class="flex justify-between items-start mb-2">
                         <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Heat Index</p>
                         <Sun class="w-5 h-5 text-orange-400" />
                    </div>
                    <div class="flex items-baseline gap-1">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                           {{ heatIndex ? heatIndex.toFixed(1) : '--' }}
                        </h3>
                        <span class="text-sm text-gray-500">°C</span>
                    </div>
                    <div class="text-xs text-orange-500 mt-1" v-if="heatIndex && latestTemp && heatIndex > latestTemp">
                        Feels warmer than actual
                    </div>
                </div>

                <!-- Dew Point -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 shadow-sm">
                    <div class="flex justify-between items-start mb-2">
                         <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dew Point</p>
                         <CloudFog class="w-5 h-5 text-cyan-400" />
                    </div>
                    <div class="flex items-baseline gap-1">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                           {{ dewPoint ? dewPoint.toFixed(1) : '--' }}
                        </h3>
                        <span class="text-sm text-gray-500">°C</span>
                    </div>
                     <div class="text-xs text-gray-400 mt-1">
                        Condensation threshold
                    </div>
                </div>

                <!-- Absolute Humidity -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 shadow-sm">
                    <div class="flex justify-between items-start mb-2">
                         <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Abs. Humidity</p>
                         <Wind class="w-5 h-5 text-blue-400" />
                    </div>
                    <div class="flex items-baseline gap-1">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                           {{ absoluteHum ? absoluteHum.toFixed(1) : '--' }}
                        </h3>
                        <span class="text-sm text-gray-500">g/m³</span>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">
                        Water vapor density
                    </div>
                </div>
            </div>

            <!-- Main Sensor Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Temperature Card -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm overflow-hidden flex flex-col h-[400px]">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-start">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <div class="p-2 rounded-lg bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400">
                                    <Thermometer class="w-5 h-5" />
                                </div>
                                <h3 class="font-semibold text-gray-900 dark:text-white text-lg">Temperature</h3>
                                
                                <div 
                                    v-if="tempTrend.value > 0"
                                    :class="[
                                        'flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border',
                                        tempTrend.direction === 'rising' ? 'bg-red-50 text-red-600 border-red-100 dark:bg-red-900/20 dark:border-red-900' : 'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-900/20 dark:border-blue-900'
                                    ]"
                                >
                                    <TrendingUp v-if="tempTrend.direction === 'rising'" class="w-3 h-3" />
                                    <TrendingDown v-else class="w-3 h-3" />
                                    {{ tempTrend.value.toFixed(1) }}°/last 5m
                                </div>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-4xl font-bold text-gray-900 dark:text-white">
                                    {{ latestTemp?.toFixed(1) ?? '--' }}
                                </span>
                                <span class="text-lg text-gray-500 dark:text-gray-400">°C</span>
                            </div>
                        </div>
                        <div class="text-right space-y-1">
                            <div class="text-xs text-gray-500 uppercase">24h Range</div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ stats24h['temperatura']?.min.toFixed(1) ?? '--' }}° - {{ stats24h['temperatura']?.max.toFixed(1) ?? '--' }}°
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex-1 min-h-0 relative p-4">
                        <Line :data="tempChartData" :options="getChartOptions('rgb(249, 115, 22)')" />
                    </div>
                </div>

                <!-- Humidity Card -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm overflow-hidden flex flex-col h-[400px]">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-start">
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                                    <Droplets class="w-5 h-5" />
                                </div>
                                <h3 class="font-semibold text-gray-900 dark:text-white text-lg">Humidity</h3>

                                <div 
                                    v-if="humTrend.value > 0"
                                    :class="[
                                        'flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border',
                                        humTrend.direction === 'rising' ? 'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-900/20 dark:border-blue-900' : 'bg-gray-50 text-gray-600 border-gray-100 dark:bg-gray-900/20 dark:border-gray-800'
                                    ]"
                                >
                                    <TrendingUp v-if="humTrend.direction === 'rising'" class="w-3 h-3" />
                                    <TrendingDown v-else class="w-3 h-3" />
                                    {{ humTrend.value.toFixed(1) }}%/last 5m
                                </div>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-4xl font-bold text-gray-900 dark:text-white">
                                    {{ latestHum?.toFixed(1) ?? '--' }}
                                </span>
                                <span class="text-lg text-gray-500 dark:text-gray-400">%</span>
                            </div>
                        </div>
                        <div class="text-right space-y-1">
                            <div class="text-xs text-gray-500 uppercase">24h Range</div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ stats24h['umiditate']?.min.toFixed(1) ?? '--' }}% - {{ stats24h['umiditate']?.max.toFixed(1) ?? '--' }}%
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex-1 min-h-0 relative p-4">
                        <Line :data="humChartData" :options="getChartOptions('rgb(59, 130, 246)')" />
                    </div>
                </div>
            </div>

            <!-- Advanced Analytics Section -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Data Correlation Card -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <Activity class="w-5 h-5 text-indigo-500" />
                        Correlation Analysis
                    </h3>
                    
                    <div class="flex flex-col items-center justify-center py-4">
                        <div class="text-4xl font-bold text-gray-900 dark:text-white mb-2">
                            {{ correlation.toFixed(2) }}
                        </div>
                        <div :class="['text-sm font-medium', correlationDescription.color]">
                            {{ correlationDescription.text }}
                        </div>
                        <p class="text-xs text-gray-500 text-center mt-4">
                            Pearson coefficient relating Temperature to Humidity in the selected timeframe.
                        </p>
                    </div>
                </div>

                <!-- Volatility / Stability -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <TrendingUp class="w-5 h-5 text-emerald-500" />
                        Stability Index (SD)
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">Temp Stability</span>
                                <span class="font-medium text-gray-900 dark:text-white">±{{ tempVolatility.toFixed(2) }}°C</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-zinc-800 rounded-full h-2">
                                <div class="bg-orange-500 h-2 rounded-full transition-all duration-500" :style="{ width: Math.min(tempVolatility * 20, 100) + '%' }"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600 dark:text-gray-400">Humidity Stability</span>
                                <span class="font-medium text-gray-900 dark:text-white">±{{ humVolatility.toFixed(2) }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-zinc-800 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" :style="{ width: Math.min(humVolatility * 5, 100) + '%' }"></div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            Standard deviation of readings. Lower values indicate a more stable environment.
                        </p>
                    </div>
                </div>

                <!-- Peak Times -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                         <RefreshCw class="w-5 h-5 text-purple-500" />
                        Peak Events (Selected Period)
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between p-2 rounded bg-orange-50 dark:bg-orange-900/10 border border-orange-100 dark:border-orange-900/20">
                            <span class="text-orange-700 dark:text-orange-400">Max Temp</span>
                            <div class="text-right">
                                <div class="font-bold text-gray-900 dark:text-white">{{ tempPeaks.max?.value.toFixed(1) ?? '--' }}°C</div>
                                <div class="text-xs text-gray-500">{{ tempPeaks.max ? new Date(tempPeaks.max.timestamp).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '--:--' }}</div>
                            </div>
                        </div>

                         <div class="flex items-center justify-between p-2 rounded bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/20">
                            <span class="text-blue-700 dark:text-blue-400">Min Temp</span>
                            <div class="text-right">
                                <div class="font-bold text-gray-900 dark:text-white">{{ tempPeaks.min?.value.toFixed(1) ?? '--' }}°C</div>
                                <div class="text-xs text-gray-500">{{ tempPeaks.min ? new Date(tempPeaks.min.timestamp).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '--:--' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

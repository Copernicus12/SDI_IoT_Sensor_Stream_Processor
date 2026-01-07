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
    Activity, 
    Droplets, 
    Sprout, 
    AlertTriangle,
    CheckCircle,
    Info,
    TrendingUp,
    TrendingDown,
    Zap
} from 'lucide-vue-next';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler, TimeScale);

interface SensorReading { value: number; timestamp: string }
interface Sensor { 
    id: number; 
    name: string; 
    type: string; 
    unit: string; 
    latest_value: number | null; 
    latest_reading_at: string | null 
}

const sensors = ref<Sensor[]>([]);
const historicalData = ref<Record<number, SensorReading[]>>({});
const stats24h = ref<Record<string, { avg: number; min: number; max: number }>>({});
const loading = ref(true);
const chartPeriod = ref(6);
let intervalId: number | null = null;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Soil Monitor', href: '/dashboard/soil' },
];

const periods = [
    { label: '1H', value: 1 },
    { label: '6H', value: 6 },
    { label: '12H', value: 12 },
    { label: '24H', value: 24 },
];

const isDark = computed(() => document.documentElement.classList.contains('dark'));
const soilSensor = computed(() => sensors.value.find(s => s.type === 'umiditate_sol' || s.name.toLowerCase().includes('soil') || s.type.toLowerCase().includes('soil')));
const latestValue = computed(() => soilSensor.value?.latest_value ?? null);
const readings = computed(() => soilSensor.value ? (historicalData.value[soilSensor.value.id] || []) : []);

const moistureStatus = computed(() => {
    const val = latestValue.value;
    if (val === null) return { label: 'Unknown', color: 'text-gray-500', icon: Info, bg: 'bg-gray-50 dark:bg-zinc-800' };
    
    // Assuming 0-4095 or 0-100 logic. If analog input, likely inverse or needs calibration. 
    // Usually capacitive soil sensors: High Value = Dry (Air), Low Value = Wet (Water) or vice versa depending on wiring.
    // Let's assume calibrated percentage (0-100%). If raw, we might need normalization.
    // Based on typical backend normalization, let's assume it's mapped to % or raw value.
    // If > 100, it's likely raw analog (0-4095).
    
    let pct = val; 
    if (val > 100) { 
        // Simple heuristic map if raw: 4095=Dry, 1500=Wet? Or 0=Dry? 
        // Without calibration info, we'll display raw but status might be guessed.
        // Let's assume the backend normalizes it, if not, we display raw.
        // For UI purposes, let's assume it's Percentage for status logic:
        // If raw, we can't easily guess status without calibration points.
        return { label: 'Raw Analog', color: 'text-blue-500', icon: Activity, bg: 'bg-blue-50 dark:bg-blue-900/20' }; 
    }

    if (pct < 20) return { label: 'Very Dry', color: 'text-red-500', icon: AlertTriangle, bg: 'bg-red-50 dark:bg-red-900/20' };
    if (pct < 40) return { label: 'Dry', color: 'text-orange-500', icon: Sprout, bg: 'bg-orange-50 dark:bg-orange-900/20' };
    if (pct < 70) return { label: 'Optimal', color: 'text-emerald-500', icon: CheckCircle, bg: 'bg-emerald-50 dark:bg-emerald-900/20' };
    return { label: 'Saturated', color: 'text-blue-500', icon: Droplets, bg: 'bg-blue-50 dark:bg-blue-900/20' };
});

const chartOptions = computed(() => ({
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
            callbacks: { label: (c:any) => `${c.parsed.y.toFixed(1)}` }
        }
    },
    scales: {
        x: { display: false },
        y: { 
            display: true, 
            grid: { color: isDark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)' } 
        }
    },
    elements: {
        line: {
            tension: 0.4,
            borderWidth: 2,
            borderColor: 'rgb(16, 185, 129)', // Emerald-500
            fill: true,
            backgroundColor: (ctx: any) => {
                const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
                gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                return gradient;
            }
        },
        point: { radius: 0, hoverRadius: 6 }
    }
}));

const chartData = computed(() => {
    const sorted = [...readings.value].sort((a,b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());
    return {
        labels: sorted.map(r => new Date(r.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})),
        datasets: [{
            label: 'Soil Moisture',
            data: sorted.map(r => r.value)
        }]
    };
});

const irrigationNeeded = computed(() => {
    if (latestValue.value === null) return false;
    // Assuming % mapping: < 30% needs water
    return latestValue.value < 30;
});

// Volatility/Stability
const volatility = computed(() => {
    if (readings.value.length < 2) return 0;
    const vals = readings.value.map(r => r.value);
    const mean = vals.reduce((a,b)=>a+b,0)/vals.length;
    const sqDiff = vals.map(v => Math.pow(v-mean,2));
    const avgSqDiff = sqDiff.reduce((a,b)=>a+b,0)/vals.length;
    return Math.sqrt(avgSqDiff);
});

const fetchData = async () => {
    try {
        const res = await apiFetch('/api/sensors');
        const json = await res.json();
        sensors.value = json.data ?? [];
        await fetchHistory();
        
        const sRes = await apiFetch('/api/sensors/statistics?hours=24');
        const sJson = await sRes.json();
        if (sJson.success) {
             sJson.data.forEach((st: any) => {
                stats24h.value[st.sensor_type] = { avg: st.avg, min: st.min, max: st.max };
            });
        }
    } catch (e) {
        console.error("Soil fetch error", e);
    } finally {
        loading.value = false;
    }
};

const fetchHistory = async () => {
    const limit = chartPeriod.value * 20;
    if (soilSensor.value) {
        const res = await apiFetch(`/api/sensors/${soilSensor.value.id}/readings?hours=${chartPeriod.value}&limit=${limit}`);
        const json = await res.json();
        if (json.success) historicalData.value[soilSensor.value.id] = json.data.readings;
    }
};

watch(chartPeriod, fetchHistory);

onMounted(() => {
    fetchData();
    intervalId = window.setInterval(fetchData, 30000);
});
onUnmounted(() => { if (intervalId) clearInterval(intervalId); });
</script>

<template>
    <Head title="Soil Moisture Monitor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Sprout class="w-6 h-6 text-emerald-500" />
                        Soil Moisture Analysis
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
                        Real-time agricultural telemetry and irrigation status.
                    </p>
                </div>
                 <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1 bg-white dark:bg-zinc-900 p-1 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-sm">
                        <button v-for="p in periods" :key="p.value" @click="chartPeriod = p.value"
                            :class="['px-3 py-1 text-xs font-medium rounded-md transition-colors', chartPeriod === p.value ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400']">
                            {{ p.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Status Card -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Status -->
                <div :class="['rounded-xl border p-6 shadow-sm flex flex-col justify-between', moistureStatus.bg, 'border-transparent']">
                    <div>
                         <p class="text-sm font-medium opacity-70 uppercase tracking-wider mb-2">Soil Status</p>
                         <h3 :class="['text-3xl font-bold flex items-center gap-2', moistureStatus.color]">
                            {{ moistureStatus.label }}
                         </h3>
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <component :is="moistureStatus.icon" :class="['w-8 h-8', moistureStatus.color]" />
                        <span class="text-sm opacity-80" v-if="irrigationNeeded">Irrigation Recommended</span>
                        <span class="text-sm opacity-80" v-else>Levels are good</span>
                    </div>
                </div>

                <!-- Live Metric -->
                <div class="md:col-span-2 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm flex flex-col">
                    <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-start">
                        <div>
                             <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Live Moisture Level</p>
                             <div class="flex items-baseline gap-2">
                                <h3 class="text-4xl font-bold text-gray-900 dark:text-white">
                                    {{ latestValue?.toFixed(1) ?? '--' }}
                                </h3>
                                <span class="text-xl text-gray-500">%</span>
                             </div>
                        </div>
                        <div class="text-right">
                             <div class="text-xs text-gray-500 uppercase mb-1">24h Avg</div>
                             <div class="font-semibold text-gray-900 dark:text-white">
                                {{ stats24h[soilSensor?.type]?.avg.toFixed(1) ?? '--' }}%
                             </div>
                        </div>
                    </div>
                    <div class="flex-1 min-h-[250px] relative p-4">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>
            </div>

            <!-- Advanced Analytics -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <Activity class="w-5 h-5 text-indigo-500" />
                        Soil Stability (Volatility)
                    </h3>
                    <div class="flex items-center gap-4">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">±{{ volatility.toFixed(2) }}%</div>
                        <p class="text-sm text-gray-500">Standard deviation over selected period. High volatility may indicate active irrigation or drainage issues.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-6">
                     <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <Zap class="w-5 h-5 text-amber-500" />
                        Recommendations
                    </h3>
                    <div v-if="irrigationNeeded" class="flex gap-3 items-start p-3 rounded bg-red-50 dark:bg-red-900/10 text-red-700 dark:text-red-400 text-sm">
                        <AlertTriangle class="w-5 h-5 shrink-0" />
                        <div>
                            <span class="font-bold">Watering Required!</span>
                            <p class="mt-1">Moisture levels have dropped below optimal threshold (30%). Active irrigation system or manual watering suggested.</p>
                        </div>
                    </div>
                    <div v-else class="flex gap-3 items-start p-3 rounded bg-emerald-50 dark:bg-emerald-900/10 text-emerald-700 dark:text-emerald-400 text-sm">
                        <CheckCircle class="w-5 h-5 shrink-0" />
                        <div>
                            <span class="font-bold">Optimal Conditions</span>
                            <p class="mt-1">Soil moisture is within healthy range for standard crops. No action needed.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

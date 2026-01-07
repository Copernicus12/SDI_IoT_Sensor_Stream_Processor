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
    Zap, 
    Activity, 
    AlertTriangle,
    CheckCircle,
    Info,
    Cpu,
    BatteryCharging,
    TrendingUp
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
const VOLTAGE = 230; // Assuming 230V standard EU/RO voltage for power calc

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Power Monitor', href: '/dashboard/acs' },
];

const periods = [
    { label: '1H', value: 1 },
    { label: '6H', value: 6 },
    { label: '12H', value: 12 },
    { label: '24H', value: 24 },
];

const isDark = computed(() => document.documentElement.classList.contains('dark'));
const acsSensor = computed(() => sensors.value.find(s => s.type === 'curent' || s.name.toLowerCase().includes('acs') || s.type.toLowerCase().includes('acs')));
const latestValue = computed(() => acsSensor.value?.latest_value ?? null);
const readings = computed(() => acsSensor.value ? (historicalData.value[acsSensor.value.id] || []) : []);

// Assuming the sensor sends Amperes directly. If invalid raw data, this might need adjustment.
// Some ACS712 implementations send 512 + (Amps * Sens). We assume backend normalized to Amps.
const currentAmps = computed(() => latestValue.value ?? 0);
const currentPowerWatts = computed(() => currentAmps.value * VOLTAGE);

// Determine Load Status
const loadStatus = computed(() => {
    const amps = currentAmps.value;
    if (amps === 0) return { label: 'Idle / Off', color: 'text-gray-500', icon: Info, bg: 'bg-gray-50 dark:bg-zinc-800' };
    
    // Thresholds: Adjust based on expected load
    if (amps < 0.5) return { label: 'Low Load', color: 'text-emerald-500', icon: CheckCircle, bg: 'bg-emerald-50 dark:bg-emerald-900/20' };
    if (amps < 5) return { label: 'Normal Load', color: 'text-blue-500', icon: Zap, bg: 'bg-blue-50 dark:bg-blue-900/20' };
    if (amps < 10) return { label: 'High Load', color: 'text-orange-500', icon: Activity, bg: 'bg-orange-50 dark:bg-orange-900/20' };
    return { label: 'Overload Risk', color: 'text-red-500', icon: AlertTriangle, bg: 'bg-red-50 dark:bg-red-900/20' };
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
            callbacks: { 
                label: (c:any) => `${c.parsed.y.toFixed(2)} A` 
            }
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
            tension: 0.3,
            borderWidth: 2,
            borderColor: 'rgb(249, 115, 22)', // Orange-500
            fill: true,
            backgroundColor: (ctx: any) => {
                const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(249, 115, 22, 0.2)');
                gradient.addColorStop(1, 'rgba(249, 115, 22, 0)');
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
            label: 'Current (Amps)',
            data: sorted.map(r => r.value)
        }]
    };
});

// Power Spike Detection: Max value in period vs average
const spikeFactor = computed(() => {
    if (readings.value.length < 2) return 1;
    const vals = readings.value.map(r => r.value);
    const avg = vals.reduce((a,b)=>a+b,0)/vals.length;
    const max = Math.max(...vals);
    return avg > 0 ? (max / avg) : 1;
});

const estimatedCost = computed(() => {
    // Determine average amps over chart period, convert to kWh cost
    // Formula: Avg Amps * 230V / 1000 = kW * Hours * Price
    if (readings.value.length === 0) return 0;
    const vals = readings.value.map(r => r.value);
    const avgAmps = vals.reduce((a,b)=>a+b,0)/vals.length;
    const avgKW = (avgAmps * VOLTAGE) / 1000;
    const hours = chartPeriod.value;
    const kwh = avgKW * hours;
    const PRICE_PER_KWH = 1.3; // RON typical
    return kwh * PRICE_PER_KWH;
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
        console.error("ACS fetch error", e);
    } finally {
        loading.value = false;
    }
};

const fetchHistory = async () => {
    const limit = chartPeriod.value * 60; // Higher resolution for power
    if (acsSensor.value) {
        const res = await apiFetch(`/api/sensors/${acsSensor.value.id}/readings?hours=${chartPeriod.value}&limit=${limit}`);
        const json = await res.json();
        if (json.success) historicalData.value[acsSensor.value.id] = json.data.readings;
    }
};

watch(chartPeriod, fetchHistory);

onMounted(() => {
    fetchData();
    intervalId = window.setInterval(fetchData, 10000); // Faster polling for electricity
});
onUnmounted(() => { if (intervalId) clearInterval(intervalId); });
</script>

<template>
    <Head title="Power Monitor" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <Zap class="w-6 h-6 text-orange-500" />
                        Power Consumption Analysis
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
                        Real-time electrical current monitoring (ACS712)
                    </p>
                </div>
                 <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1 bg-white dark:bg-zinc-900 p-1 rounded-lg border border-gray-200 dark:border-zinc-800 shadow-sm">
                        <button v-for="p in periods" :key="p.value" @click="chartPeriod = p.value"
                            :class="['px-3 py-1 text-xs font-medium rounded-md transition-colors', chartPeriod === p.value ? 'bg-orange-50 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400' : 'text-gray-500 dark:text-gray-400']">
                            {{ p.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Overview Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Status Card -->
                <div :class="['md:col-span-1 rounded-xl border p-6 shadow-sm flex flex-col justify-between', loadStatus.bg, 'border-transparent']">
                    <div>
                         <p class="text-sm font-medium opacity-70 uppercase tracking-wider mb-2">Load Status</p>
                         <h3 :class="['text-2xl font-bold flex items-center gap-2', loadStatus.color]">
                            {{ loadStatus.label }}
                         </h3>
                    </div>
                    <div class="mt-4">
                        <component :is="loadStatus.icon" :class="['w-10 h-10', loadStatus.color]" />
                    </div>
                </div>

                <!-- Metrics -->
                <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4">
                     <!-- Current -->
                    <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-4 flex flex-col justify-center">
                        <div class="text-gray-500 text-xs uppercase font-medium mb-1">Current (I)</div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ currentAmps.toFixed(2) }} <span class="text-lg text-gray-400 font-normal">A</span>
                        </div>
                    </div>
                    <!-- Est Power -->
                    <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-4 flex flex-col justify-center">
                        <div class="text-gray-500 text-xs uppercase font-medium mb-1">Power (P)</div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ currentPowerWatts.toFixed(0) }} <span class="text-lg text-gray-400 font-normal">W</span>
                        </div>
                         <div class="text-xs text-gray-400 mt-1">@ {{VOLTAGE}}V est.</div>
                    </div>
                     <!-- Est Cost -->
                    <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-4 flex flex-col justify-center">
                        <div class="text-gray-500 text-xs uppercase font-medium mb-1">Est. Cost ({{chartPeriod}}h)</div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                            {{ estimatedCost.toFixed(2) }} <span class="text-lg text-gray-400 font-normal">RON</span>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">~1.3 RON/kWh</div>
                    </div>
                </div>
            </div>

            <!-- Main Chart -->
             <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm flex flex-col">
                <div class="p-6 border-b border-gray-100 dark:border-zinc-800">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Current Usage Trend</h3>
                </div>
                <div class="flex-1 min-h-[300px] relative p-4">
                    <Line :data="chartData" :options="chartOptions" />
                </div>
            </div>

            <!-- Analytics Footer -->
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <Activity class="w-5 h-5 text-indigo-500" />
                        Peak & Spike Analysis
                    </h3>
                    <div class="flex items-center gap-4">
                        <div class="text-slate-900 dark:text-white">
                            <span class="text-2xl font-bold">{{ stats24h[acsSensor?.type]?.max.toFixed(2) ?? 0 }} A</span>
                            <span class="text-sm text-gray-500 ml-2">24h Peak</span>
                        </div>
                        <div class="h-8 w-px bg-gray-200 dark:bg-zinc-700 mx-2"></div>
                        <div class="text-slate-900 dark:text-white">
                            <span class="text-2xl font-bold">x{{ spikeFactor.toFixed(1) }}</span>
                            <span class="text-sm text-gray-500 ml-2">Spike Factor</span>
                        </div>
                    </div>
                     <p class="text-sm text-gray-500 mt-3">High spike factor (>3.0) indicates inductive loads (motors, compressors) starting up.</p>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-6">
                     <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <BatteryCharging class="w-5 h-5 text-green-500" />
                        Efficiency Insight
                    </h3>
                    <div v-if="currentAmps < 0.1 && currentAmps > 0.01" class="flex gap-3 items-start p-3 rounded bg-amber-50 dark:bg-amber-900/10 text-amber-700 dark:text-amber-400 text-sm">
                        <Info class="w-5 h-5 shrink-0" />
                        <div>
                            <span class="font-bold">Phantom Load Detected?</span>
                            <p class="mt-1">Very low current detected. Check for devices in standby mode consuming power unnecessarily.</p>
                        </div>
                    </div>
                    <div v-else-if="currentAmps > 10" class="flex gap-3 items-start p-3 rounded bg-red-50 dark:bg-red-900/10 text-red-700 dark:text-red-400 text-sm">
                        <AlertTriangle class="w-5 h-5 shrink-0" />
                        <div>
                            <span class="font-bold">High Consumption!</span>
                            <p class="mt-1">System is running near capacity. Ensure cabling is rated for >10A to prevent overheating.</p>
                        </div>
                    </div>
                     <div v-else class="flex gap-3 items-start p-3 rounded bg-blue-50 dark:bg-blue-900/10 text-blue-700 dark:text-blue-400 text-sm">
                        <CheckCircle class="w-5 h-5 shrink-0" />
                        <div>
                            <span class="font-bold">Normal Operation</span>
                            <p class="mt-1">Current consumption is within expected parameters for standard household appliances.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { apiFetch } from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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
    Filler
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

interface SensorReading {
    value: number;
    timestamp: string;
}

interface Sensor {
    id: number;
    node_id: string;
    name: string;
    type: string;
    unit: string;
    latest_value: number | null;
    latest_reading_at: string | null;
}

interface Statistics {
    sensor_id: number;
    sensor_name: string;
    sensor_type: string;
    unit: string;
    avg: number;
    min: number;
    max: number;
    total_readings: number;
}

const sensors = ref<Sensor[]>([]);
const statistics = ref<Statistics[]>([]);
const historicalData = ref<Record<number, SensorReading[]>>({});
const loading = ref(true);
const error = ref<string | null>(null);
let refreshInterval: number | null = null;
const recentStream = ref<any[]>([]);

const totalConsumption = computed(() => {
    const currentSensor = sensors.value.find(s => s.type === 'curent');
    return currentSensor?.latest_value || 0;
});

const fetchSensors = async () => {
    try {
        const response = await apiFetch('/api/sensors');
        const data = await response.json();
        
        if (data.success) {
            sensors.value = data.data;
            error.value = null;
        }
    } catch (err) {
        error.value = 'Nu s-au putut încărca datele senzorilor';
        console.error('Error fetching sensors:', err);
    } finally {
        loading.value = false;
    }
};

const fetchStatistics = async () => {
    try {
        const response = await apiFetch('/api/sensors/statistics?hours=24');
        const data = await response.json();
        
        if (data.success) {
            statistics.value = data.data;
        }
    } catch (err) {
        console.error('Error fetching statistics:', err);
    }
};

const fetchHistoricalData = async (sensorId: number) => {
    try {
        const response = await apiFetch(`/api/sensors/${sensorId}/readings?hours=2&limit=20`);
        const data = await response.json();
        
        if (data.success) {
            historicalData.value[sensorId] = data.data.readings;
        }
    } catch (err) {
        console.error('Error fetching historical data:', err);
    }
};

const fetchStream = async () => {
    try {
        const response = await apiFetch(`/api/sensors/stream?limit=10`);
        const data = await response.json();
        if (data.success) {
            // accept either { readings: [...] } or direct array
            recentStream.value = data.data?.readings ?? data.data ?? [];
        }
    } catch (err) {
        console.error('Error fetching stream:', err);
    }
};

const getChartData = (sensorId: number) => {
    const readings = historicalData.value[sensorId] || [];
    return {
        labels: readings.map(r => {
            const date = new Date(r.timestamp);
            return date.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
        }).reverse(),
        datasets: [{
            data: readings.map(r => r.value).reverse(),
            borderColor: getChartColor(sensors.value.find(s => s.id === sensorId)?.type || ''),
            backgroundColor: getChartColor(sensors.value.find(s => s.id === sensorId)?.type || '', 0.1),
            tension: 0.4,
            fill: true,
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4,
        }]
    };
};

const getCombinedChartData = () => {
    if (sensors.value.length === 0) return { labels: [], datasets: [] };
    
    // Use the first sensor's timestamps as base labels
    const firstSensorReadings = historicalData.value[sensors.value[0]?.id] || [];
    const labels = firstSensorReadings.map(r => {
        const date = new Date(r.timestamp);
        return date.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
    }).reverse();
    
    // Create a dataset for each sensor
    const datasets = sensors.value.map(sensor => {
        const readings = historicalData.value[sensor.id] || [];
        return {
            label: sensor.type.replace('_', ' '),
            data: readings.map(r => r.value).reverse(),
            borderColor: getChartColor(sensor.type),
            backgroundColor: getChartColor(sensor.type, 0.1),
            tension: 0.4,
            fill: false,
            borderWidth: 3,
            pointRadius: 0,
            pointHoverRadius: 6,
        };
    });
    
    return { labels, datasets };
};

const isDark = computed(() => {
    return document.documentElement.classList.contains('dark');
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            enabled: true,
            mode: 'index' as const,
            intersect: false,
            backgroundColor: isDark.value ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.9)',
            titleColor: isDark.value ? '#f1f5f9' : '#0f172a',
            bodyColor: isDark.value ? '#cbd5e1' : '#475569',
            borderColor: isDark.value ? '#334155' : '#e2e8f0',
            borderWidth: 1,
        }
    },
    scales: {
        x: {
            display: true,
            grid: {
                display: false
            },
            ticks: {
                color: isDark.value ? '#64748b' : '#94a3b8',
                font: {
                    size: 10
                }
            }
        },
        y: {
            display: true,
            grid: {
                color: isDark.value ? 'rgba(51, 65, 85, 0.3)' : 'rgba(226, 232, 240, 0.8)'
            },
            ticks: {
                color: isDark.value ? '#64748b' : '#94a3b8',
                font: {
                    size: 10
                }
            }
        }
    },
    interaction: {
        intersect: false,
        mode: 'index' as const,
    }
}));

const getChartColor = (type: string, alpha: number = 1): string => {
    const colors: Record<string, string> = {
        temperatura: `rgba(239, 68, 68, ${alpha})`,
        umiditate: `rgba(59, 130, 246, ${alpha})`,
        umiditate_sol: `rgba(34, 197, 94, ${alpha})`,
        curent: `rgba(234, 179, 8, ${alpha})`,
    };
    return colors[type] || `rgba(107, 114, 128, ${alpha})`;
};

interface SensorSymbol {
    label: string;
    classes: string;
}

const sensorSymbolMap: Record<string, SensorSymbol> = {
    temperatura: {
        label: 'T°',
        classes: 'bg-gradient-to-br from-red-500/15 to-red-500/5 border border-red-500/30 text-red-600 dark:text-red-300',
    },
    umiditate: {
        label: 'H₂O',
        classes: 'bg-gradient-to-br from-blue-500/15 to-blue-500/5 border border-blue-500/30 text-blue-600 dark:text-blue-300',
    },
    umiditate_sol: {
        label: 'SOIL',
        classes: 'bg-gradient-to-br from-emerald-500/15 to-emerald-500/5 border border-emerald-500/30 text-emerald-600 dark:text-emerald-300',
    },
    curent: {
        label: 'AMP',
        classes: 'bg-gradient-to-br from-amber-500/15 to-amber-500/5 border border-amber-500/30 text-amber-600 dark:text-amber-300',
    },
};

const defaultSensorSymbol: SensorSymbol = {
    label: 'DATA',
    classes: 'bg-slate-200/80 dark:bg-slate-900 border border-slate-300/60 dark:border-slate-700 text-slate-700 dark:text-slate-200',
};

const getSensorSymbol = (type: string): SensorSymbol => {
    return sensorSymbolMap[type] ?? defaultSensorSymbol;
};

const getSensorColor = (type: string): string => {
    const colors: Record<string, string> = {
        temperatura: 'bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800/50 shadow-red-100 dark:shadow-red-950/50',
        umiditate: 'bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800/50 shadow-blue-100 dark:shadow-blue-950/50',
        umiditate_sol: 'bg-green-50 dark:bg-green-950/30 text-green-700 dark:text-green-400 border-green-200 dark:border-green-800/50 shadow-green-100 dark:shadow-green-950/50',
        curent: 'bg-yellow-50 dark:bg-yellow-950/30 text-yellow-700 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800/50 shadow-yellow-100 dark:shadow-yellow-950/50',
    };
    return colors[type] || 'bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200';
};

const getStatForSensor = (sensorId: number) => {
    return statistics.value.find(s => s.sensor_id === sensorId);
};

const sensorByType = (type: string) => sensors.value.find(s => s.type === type);
const latestTimestampForSensor = (s?: Sensor): number | null => {
    if (!s) return null;
    const candidates = [
        s.latest_reading_at,
        (s as any).latest_at,
        (s as any).last_seen_at,
        (s as any).updated_at,
    ].filter(Boolean);
    for (const ts of candidates) {
        const t = new Date(ts as string).getTime();
        if (!Number.isNaN(t)) return t;
    }
    const hist = historicalData.value[s.id] || [];
    if (hist.length) {
        const maxTs = Math.max(...hist.map(r => new Date(r.timestamp).getTime()).filter(t => !Number.isNaN(t)));
        return Number.isFinite(maxTs) ? maxTs : null;
    }
    return null;
};
const isOnline = (s?: Sensor) => {
    const ts = latestTimestampForSensor(s);
    if (!ts) return false;
    const diff = Date.now() - ts;
    return diff < 5_000; // consider online dacă are citiri în ultimele ~90s
};
const onlineCount = computed(() => sensors.value.filter(s => isOnline(s)).length);

onMounted(() => {
    fetchSensors();
    fetchStatistics();
    fetchStream();
    
    // Fetch historical data for all sensors
    setTimeout(() => {
        sensors.value.forEach(sensor => {
            fetchHistoricalData(sensor.id);
        });
    }, 500);
    
    // Auto-refresh
    refreshInterval = window.setInterval(() => {
        fetchSensors();
        fetchStatistics();
        sensors.value.forEach(sensor => {
            fetchHistoricalData(sensor.id);
        });
        fetchStream();
    }, 3000);
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<template>
    <AppLayout>
        <Head title="IoT Sensor Monitor" />

    <div class="min-h-screen">
            <!-- Header -->
            <div class="border-b border-slate-200 dark:border-slate-900 bg-white/80 dark:bg-black/80 backdrop-blur-sm sticky top-0 z-10">
                <div class="container mx-auto px-6 py-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-cyan-500 dark:from-blue-400 dark:to-cyan-400 bg-clip-text text-transparent">
                                IoT Sensor Monitor
                            </h1>
                            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Monitorizare în timp real • Dashboard</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <Badge class="bg-green-500/10 dark:bg-green-500/20 text-green-600 dark:text-green-400 border border-green-500/20 dark:border-green-500/30 px-4 py-2">
                                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></span>
                                Live
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center min-h-[80vh]">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-500 dark:border-blue-400 mx-auto"></div>
                    <p class="mt-4 text-slate-600 dark:text-slate-400">Se încarcă datele senzorilor...</p>
                </div>
            </div>

            <!-- Main Content -->
            <div v-else class="container mx-auto px-6 py-10 max-w-7xl">
                <!-- Quick Navigation -->
                <div class="flex flex-wrap items-center gap-3 mb-8">
                    <Link href="/dashboard/dht11" class="px-4 py-2 rounded-lg border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 hover:bg-slate-50 dark:hover:bg-zinc-900 transition text-sm font-medium">
                        <span class="flex items-center gap-3">
                            <span
                                class="inline-flex items-center justify-center w-9 h-9 rounded-full text-[0.65rem] font-semibold tracking-widest"
                                :class="getSensorSymbol('temperatura').classes"
                            >
                                {{ getSensorSymbol('temperatura').label }}
                            </span>
                            Temperatură & Umiditate (DHT11)
                        </span>
                    </Link>
                    <Link href="/dashboard/soil" class="px-4 py-2 rounded-lg border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 hover:bg-slate-50 dark:hover:bg-zinc-900 transition text-sm font-medium">
                        <span class="flex items-center gap-3">
                            <span
                                class="inline-flex items-center justify-center w-9 h-9 rounded-full text-[0.65rem] font-semibold tracking-widest"
                                :class="getSensorSymbol('umiditate_sol').classes"
                            >
                                {{ getSensorSymbol('umiditate_sol').label }}
                            </span>
                            Umiditate Sol
                        </span>
                    </Link>
                    <Link href="/dashboard/acs" class="px-4 py-2 rounded-lg border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 hover:bg-slate-50 dark:hover:bg-zinc-900 transition text-sm font-medium">
                        <span class="flex items-center gap-3">
                            <span
                                class="inline-flex items-center justify-center w-9 h-9 rounded-full text-[0.65rem] font-semibold tracking-widest"
                                :class="getSensorSymbol('curent').classes"
                            >
                                {{ getSensorSymbol('curent').label }}
                            </span>
                            Curent (ACS712)
                        </span>
                    </Link>
                    <div class="ml-auto flex items-center gap-2 text-xs">
                        <span class="w-2 h-2 rounded-full" :class="onlineCount > 0 ? 'bg-green-500' : 'bg-slate-400'"></span>
                        <span class="text-slate-600 dark:text-slate-400">{{ onlineCount }}/{{ sensors.length }} online</span>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                    <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs">Temperatură</CardDescription>
                            <CardTitle class="text-3xl font-extrabold flex items-baseline gap-2">
                                <span>{{ sensorByType('temperatura')?.latest_value?.toFixed(1) ?? '--' }}</span>
                                <span class="text-sm font-semibold opacity-60">°C</span>
                            </CardTitle>
                        </CardHeader>
                            <CardContent class="text-xs flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-[0.65rem] font-semibold tracking-widest"
                                    :class="getSensorSymbol('temperatura').classes"
                                >
                                    {{ getSensorSymbol('temperatura').label }}
                                </span>
                                <span class="capitalize">{{ sensorByType('temperatura')?.node_id ?? '—' }}</span>
                            </span>
                            <span :class="isOnline(sensorByType('temperatura')) ? 'text-green-500' : 'text-slate-500'">{{ isOnline(sensorByType('temperatura')) ? 'online' : 'offline' }}</span>
                        </CardContent>
                    </Card>
                    <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs">Umiditate Aer</CardDescription>
                            <CardTitle class="text-3xl font-extrabold flex items-baseline gap-2">
                                <span>{{ sensorByType('umiditate')?.latest_value?.toFixed(1) ?? '--' }}</span>
                                <span class="text-sm font-semibold opacity-60">%</span>
                            </CardTitle>
                        </CardHeader>
                            <CardContent class="text-xs flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-[0.65rem] font-semibold tracking-widest"
                                    :class="getSensorSymbol('umiditate').classes"
                                >
                                    {{ getSensorSymbol('umiditate').label }}
                                </span>
                                <span class="capitalize">{{ sensorByType('umiditate')?.node_id ?? '—' }}</span>
                            </span>
                            <span :class="isOnline(sensorByType('umiditate')) ? 'text-green-500' : 'text-slate-500'">{{ isOnline(sensorByType('umiditate')) ? 'online' : 'offline' }}</span>
                        </CardContent>
                    </Card>
                    <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs">Umiditate Sol</CardDescription>
                            <CardTitle class="text-3xl font-extrabold flex items-baseline gap-2">
                                <span>{{ sensorByType('umiditate_sol')?.latest_value?.toFixed(1) ?? '--' }}</span>
                                <span class="text-sm font-semibold opacity-60">%</span>
                            </CardTitle>
                        </CardHeader>
                            <CardContent class="text-xs flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-[0.65rem] font-semibold tracking-widest"
                                    :class="getSensorSymbol('umiditate_sol').classes"
                                >
                                    {{ getSensorSymbol('umiditate_sol').label }}
                                </span>
                                <span class="capitalize">{{ sensorByType('umiditate_sol')?.node_id ?? '—' }}</span>
                            </span>
                            <span :class="isOnline(sensorByType('umiditate_sol')) ? 'text-green-500' : 'text-slate-500'">{{ isOnline(sensorByType('umiditate_sol')) ? 'online' : 'offline' }}</span>
                        </CardContent>
                    </Card>
                    <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs">Curent</CardDescription>
                            <CardTitle class="text-3xl font-extrabold flex items-baseline gap-2">
                                <span>{{ (sensorByType('curent')?.latest_value ?? 0).toFixed(2) }}</span>
                                <span class="text-sm font-semibold opacity-60">A</span>
                            </CardTitle>
                        </CardHeader>
                            <CardContent class="text-xs flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-[0.65rem] font-semibold tracking-widest"
                                    :class="getSensorSymbol('curent').classes"
                                >
                                    {{ getSensorSymbol('curent').label }}
                                </span>
                                <span class="capitalize">{{ sensorByType('curent')?.node_id ?? '—' }}</span>
                            </span>
                            <span :class="isOnline(sensorByType('curent')) ? 'text-green-500' : 'text-slate-500'">{{ isOnline(sensorByType('curent')) ? 'online' : 'offline' }}</span>
                        </CardContent>
                    </Card>
                </div>
                <!-- Individual Sensor Cards Grid (exclude temperature big red card) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <Card 
                        v-for="sensor in sensors.filter(s => s.type !== 'temperatura')" 
                        :key="sensor.id" 
                        :class="[
                            'border-2 transition-all hover:shadow-2xl cursor-pointer group overflow-hidden relative',
                            getSensorColor(sensor.type)
                        ]"
                    >
                        <!-- Background Gradient Effect -->
                        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                             :style="{ background: `linear-gradient(135deg, ${getChartColor(sensor.type, 0.08)} 0%, transparent 100%)` }">
                        </div>
                        
                        <CardHeader class="pb-4 relative z-10">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div
                                        class="group-hover:scale-110 transition-transform inline-flex items-center justify-center w-20 h-20 rounded-2xl border text-sm font-semibold tracking-[0.2em]"
                                        :class="getSensorSymbol(sensor.type).classes"
                                    >
                                        {{ getSensorSymbol(sensor.type).label }}
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-2xl capitalize leading-tight text-slate-900 dark:text-white">
                                            {{ sensor.type.replace('_', ' ') }}
                                        </h3>
                                        <div class="flex items-center space-x-2 mt-2">
                                            <Badge variant="outline" class="text-xs font-semibold border-current">
                                                {{ sensor.node_id }}
                                            </Badge>
                                            <Badge class="bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/30 text-xs">
                                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse mr-1.5"></span>
                                                Live
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardHeader>
                        
                        <CardContent class="relative z-10 space-y-4">
                            <!-- Main Value Display -->
                            <div class="bg-gradient-to-br from-white/50 to-transparent dark:from-white/5 dark:to-transparent rounded-xl p-4 backdrop-blur-sm">
                                <div class="flex items-baseline justify-between">
                                    <div class="flex items-baseline space-x-2">
                                        <span class="text-5xl font-black tracking-tight">
                                            {{ sensor.latest_value !== null ? sensor.latest_value.toFixed(1) : '--' }}
                                        </span>
                                        <span class="text-lg font-bold opacity-70">{{ sensor.unit }}</span>
                                    </div>
                                    <div v-if="getStatForSensor(sensor.id)" class="text-right">
                                        <div class="text-xs font-semibold opacity-60 uppercase tracking-wide">24h Avg</div>
                                        <div class="text-xl font-bold">
                                            {{ getStatForSensor(sensor.id)?.avg.toFixed(1) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics Bar -->
                            <div v-if="getStatForSensor(sensor.id)" class="grid grid-cols-3 gap-2 text-center">
                                <div class="bg-white/30 dark:bg-white/5 rounded-lg p-2">
                                    <div class="text-xs font-semibold opacity-60 uppercase">Min</div>
                                    <div class="text-sm font-bold">{{ getStatForSensor(sensor.id)?.min.toFixed(1) }}</div>
                                </div>
                                <div class="bg-white/30 dark:bg-white/5 rounded-lg p-2">
                                    <div class="text-xs font-semibold opacity-60 uppercase">Max</div>
                                    <div class="text-sm font-bold">{{ getStatForSensor(sensor.id)?.max.toFixed(1) }}</div>
                                </div>
                                <div class="bg-white/30 dark:bg-white/5 rounded-lg p-2">
                                    <div class="text-xs font-semibold opacity-60 uppercase">Citiri</div>
                                    <div class="text-sm font-bold">{{ getStatForSensor(sensor.id)?.total_readings }}</div>
                                </div>
                            </div>
                            
                            <!-- Trend Chart -->
                            <div>
                                <div class="text-xs font-semibold opacity-60 uppercase mb-2 flex items-center justify-between">
                                    <span>Tendință (2h)</span>
                                    <span v-if="sensor.latest_reading_at" class="normal-case font-normal">
                                        {{ new Date(sensor.latest_reading_at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }) }}
                                    </span>
                                </div>
                                <div class="h-20 bg-white/20 dark:bg-white/5 rounded-lg p-2">
                                    <Line 
                                        v-if="historicalData[sensor.id]" 
                                        :data="getChartData(sensor.id)" 
                                        :options="{...chartOptions, scales: { x: { display: false }, y: { display: false } }, plugins: { tooltip: { enabled: false } }}"
                                    />
                                </div>
                            </div>

                            <!-- Status Indicator -->
                            <div class="flex items-center justify-between text-xs pt-2 border-t border-current/20">
                                <div class="flex items-center space-x-2">
                                    <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                                    <span class="font-semibold opacity-70">Live</span>
                                </div>
                                <span class="opacity-60">{{ sensor.name }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Combined Trends Chart removed as requested -->

                <!-- System Status & Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
                    <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900 lg:col-span-1">
                        <CardHeader>
                            <CardTitle class="text-lg font-bold">Stare Sistem</CardTitle>
                            <CardDescription>Rezumat dispozitive</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 dark:text-slate-400">Senzori online</span>
                                <span class="font-semibold">{{ onlineCount }}/{{ sensors.length }}</span>
                            </div>
                            <div class="space-y-2">
                                <div v-for="s in sensors" :key="'status-'+s.id" class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[0.6rem] font-semibold tracking-widest border"
                                            :class="getSensorSymbol(s.type).classes"
                                        >
                                            {{ getSensorSymbol(s.type).label }}
                                        </span>
                                        <span class="capitalize">{{ s.type.replace('_',' ') }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full" :class="isOnline(s) ? 'bg-green-500' : 'bg-slate-500'"></span>
                                        <span class="tabular-nums opacity-70">{{ s.latest_reading_at ? new Date(s.latest_reading_at).toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'}) : '--' }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900 lg:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-lg font-bold">Activitate Recentă</CardTitle>
                            <CardDescription>Ultimele citiri</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="recentStream.length === 0" class="text-sm text-slate-500">Nu există activitate recentă.</div>
                            <div v-else class="divide-y divide-slate-200 dark:divide-zinc-800">
                                <div v-for="(r,idx) in recentStream" :key="'evt-'+idx" class="py-2 flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[0.6rem] font-semibold tracking-widest border"
                                            :class="getSensorSymbol(r.type || r.sensor_type || '').classes"
                                        >
                                            {{ getSensorSymbol(r.type || r.sensor_type || '').label }}
                                        </span>
                                        <span class="capitalize">{{ (r.type || r.sensor_type || '').toString().replace('_',' ') }}</span>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <span class="font-semibold">{{ r.value?.toFixed ? r.value.toFixed(1) : r.value }} {{ r.unit || '' }}</span>
                                        <span class="text-xs text-slate-500">{{ r.timestamp ? new Date(r.timestamp).toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit',second:'2-digit'}) : '' }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
// @ts-ignore
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { 
    Activity, 
    AlertTriangle, 
    ArrowDownRight, 
    ArrowUpRight, 
    Minus,
    Wifi,
    WifiOff,
    Database,
    Thermometer,
    Droplets,
    Sprout,
    Radio
} from 'lucide-vue-next';

const props = defineProps<{
    sensors: Array<{
        id: number;
        name: string;
        type: string;
        unit: string;
        is_online: boolean;
        value: number | null;
        last_update: string;
        stats: {
            min: number | string;
            max: number | string;
            avg: number | string;
        };
        trend: 'up' | 'down' | 'stable';
    }>;
    recentReadings: Array<{
        id: number;
        sensor: string;
        value: number;
        unit: string;
        time: string;
    }>;
    recentAlerts: Array<{
        id: number;
        message: string;
        threshold: number;
        type: 'high' | 'low';
        time: string;
    }>;
    systemStats: {
        total_readings_today: number;
        active_alerts: number;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

let interval: ReturnType<typeof setInterval>;

onMounted(() => {
    interval = setInterval(() => {
        router.reload({ only: ['sensors', 'recentReadings', 'recentAlerts', 'systemStats'] });
    }, 5000);
});

onUnmounted(() => {
    clearInterval(interval);
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            
            <!-- Top System Stats -->
            <div class="grid gap-4 grid-cols-1 md:grid-cols-3">
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Readings Today</p>
                        <h4 class="text-2xl font-bold text-gray-900 dark:text-white">{{ systemStats.total_readings_today }}</h4>
                    </div>
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <Database class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Alerts</p>
                        <h4 class="text-2xl font-bold text-gray-900 dark:text-white">{{ systemStats.active_alerts }}</h4>
                    </div>
                    <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                        <AlertTriangle class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                    </div>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">System Status</p>
                        <div class="flex items-center gap-2 mt-1">
                             <div class="h-2.5 w-2.5 rounded-full bg-green-500 animate-pulse"></div>
                             <span class="font-bold text-green-600">OPERATIONAL</span>
                        </div>
                    </div>
                     <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <Activity class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </div>

            <!-- Sensors Grid -->
            <div class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
                <div v-for="sensor in sensors" :key="sensor.id" 
                     class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-6 flex flex-col justify-between shadow-sm transition-all hover:shadow-md"
                >
                    <div class="flex flex-row items-center justify-between pb-2">
                        <h3 class="font-medium tracking-tight text-sm text-gray-500 dark:text-gray-400 flex items-center gap-2">
                            <Thermometer v-if="sensor.type === 'temperature'" class="w-4 h-4" />
                            <Droplets v-else-if="sensor.type === 'humidity'" class="w-4 h-4" />
                            <Sprout v-else-if="sensor.type === 'soil'" class="w-4 h-4" />
                            <Radio v-else class="w-4 h-4" />
                            {{ sensor.name }}
                        </h3>
                        <div v-if="sensor.is_online" class="text-green-500" title="Online">
                            <Wifi class="w-4 h-4" />
                        </div>
                        <div v-else class="text-red-500" title="Offline">
                            <WifiOff class="w-4 h-4" />
                        </div>
                    </div>

                    <div class="py-2">
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ sensor.value !== null ? sensor.value : '--' }}
                            </span>
                            <span class="text-lg font-medium text-gray-400 dark:text-gray-500">{{ sensor.unit }}</span>
                            
                            <!-- Trend Arrow -->
                             <span v-if="sensor.trend === 'up'" class="ml-auto text-red-500 flex items-center text-xs font-medium bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded-full">
                                <ArrowUpRight class="w-3 h-3 mr-1" /> Rising
                            </span>
                             <span v-else-if="sensor.trend === 'down'" class="ml-auto text-blue-500 flex items-center text-xs font-medium bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded-full">
                                <ArrowDownRight class="w-3 h-3 mr-1" /> Falling
                            </span>
                             <span v-else class="ml-auto text-gray-400 flex items-center text-xs font-medium bg-gray-50 dark:bg-zinc-800 px-2 py-1 rounded-full">
                                <Minus class="w-3 h-3 mr-1" /> Stable
                            </span>
                        </div>
                    </div>

                    <!-- Daily Stats Mini-Grid -->
                    <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-gray-100 dark:border-zinc-800">
                        <div class="text-center">
                            <span class="text-[10px] text-gray-400 uppercase tracking-wider block">Min</span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ sensor.stats.min }}</span>
                        </div>
                        <div class="text-center border-l border-r border-gray-100 dark:border-zinc-800">
                            <span class="text-[10px] text-gray-400 uppercase tracking-wider block">Avg</span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ sensor.stats.avg }}</span>
                        </div>
                        <div class="text-center">
                            <span class="text-[10px] text-gray-400 uppercase tracking-wider block">Max</span>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ sensor.stats.max }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 grid-cols-1 lg:grid-cols-3">
                <!-- Recent Readings Table -->
                <div class="lg:col-span-2 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm overflow-hidden flex flex-col h-[400px]">
                    <div class="p-6 border-b border-sidebar-border/70 dark:border-sidebar-border flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Live Data Feed</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Incoming stream from connected nodes.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
                            <span class="text-xs font-medium text-green-600">LIVE</span>
                        </div>
                    </div>
                    
                    <div class="overflow-auto flex-1">
                        <table class="w-full text-sm text-left">
                            <thead class="sticky top-0 bg-gray-50 dark:bg-zinc-800/90 text-gray-500 dark:text-gray-400 font-medium border-b border-sidebar-border/70 dark:border-sidebar-border backdrop-blur-sm z-10">
                                <tr>
                                    <th class="py-3 px-6">Sensor Node</th>
                                    <th class="py-3 px-6">Value</th>
                                    <th class="py-3 px-6 text-right">Received</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                <tr v-for="reading in recentReadings" :key="reading.id" class="hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="py-3 px-6 font-medium text-gray-900 dark:text-white">{{ reading.sensor }}</td>
                                    <td class="py-3 px-6 text-gray-700 dark:text-gray-300">
                                        <span class="font-semibold">{{ reading.value }}</span> {{ reading.unit }}
                                    </td>
                                    <td class="py-3 px-6 text-right text-gray-500 dark:text-gray-400 font-mono text-xs">
                                        {{ reading.time }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Alerts -->
                <div class="lg:col-span-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm overflow-hidden flex flex-col h-[400px]">
                     <div class="p-6 border-b border-sidebar-border/70 dark:border-sidebar-border">
                        <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Recent Alerts</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Threshold violations & warnings.</p>
                    </div>
                    <div class="overflow-auto flex-1 p-4 space-y-3">
                        <div v-for="alert in recentAlerts" :key="alert.id" class="flex gap-3 p-3 rounded-lg border border-gray-100 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-800/30">
                            <div class="shrink-0 pt-1">
                                <AlertTriangle class="w-4 h-4 text-orange-500" />
                            </div>
                            <div>
                                <h5 class="text-sm font-semibold text-gray-900 dark:text-white">{{ alert.message }}</h5>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Threshold: {{ alert.threshold }} • {{ alert.time }}
                                </p>
                            </div>
                        </div>
                        
                        <div v-if="recentAlerts.length === 0" class="flex flex-col items-center justify-center h-full text-center text-gray-400">
                            <div class="p-4 rounded-full bg-gray-50 dark:bg-zinc-800/50 mb-3">
                                <Activity class="w-6 h-6" />
                            </div>
                            <p class="text-sm">No recent alerts</p>
                            <p class="text-xs opacity-70">Everything is running normally</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

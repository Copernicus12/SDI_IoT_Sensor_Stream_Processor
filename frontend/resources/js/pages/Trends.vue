<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'
import { apiFetch } from '@/lib/api'
import { type BreadcrumbItem } from '@/types';
import { Line } from 'vue-chartjs'
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
} from 'chart.js'
import { 
    Activity, 
    BarChart3, 
    Calendar,
    Thermometer,
    Droplets,
    Sprout,
    Radio,
    RefreshCw
} from 'lucide-vue-next';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

interface Sensor { id:number; name:string; type:string; unit:string }
interface Bucket { bucket:string | null; avg:number|null; min:number|null; max:number|null; count:number|null }

type PeriodKey = 'hour'|'day'|'week'
const periodConfig:Record<PeriodKey, { hours:number; bucket:'hour'|'day', label: string }> = {
  hour: { hours: 48, bucket: 'hour', label: 'Last 48 Hours' },
  day: { hours: 24 * 14, bucket: 'day', label: 'Last 14 Days' },
  week: { hours: 24 * 7, bucket: 'day', label: 'Last 7 Days' },
}

const sensors = ref<Sensor[]>([])
const selectedPeriod = ref<PeriodKey>('hour')
const series = ref<Record<number, Bucket[]>>({})
const loading = ref(false)
const refreshing = ref(false)
const error = ref<string | null>(null)

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Trends',
        href: '/dashboard/trends',
    },
];

const normalizeBuckets = (payload:any[]):Bucket[] => {
  return payload.map((item:any) => ({
    bucket: item.bucket ?? item.bucket_start ?? item.bucketStart ?? null,
    avg: item.avg ?? null,
    min: item.min ?? null,
    max: item.max ?? null,
    count: item.count ?? item.cnt ?? null,
  }))
}

const fetchSensors = async () => {
  const res = await apiFetch('/api/sensors')
  if (!res.ok) throw new Error(`Failed to fetch sensors (HTTP ${res.status})`)
  const json = await res.json()
  if (!json?.success) throw new Error(json?.error ?? 'Invalid API response')
  sensors.value = json.data?.map((s:any) => ({
    id: s.id,
    name: s.sensor_name || s.name,
    type: s.sensor_type || s.type,
    unit: s.unit,
  })) ?? []
}

const fetchAgg = async (sensorId:number) => {
  const cfg = periodConfig[selectedPeriod.value]
  const search = new URLSearchParams({
    period: cfg.bucket,
    hours: String(cfg.hours),
  })
  const res = await apiFetch(`/api/sensors/${sensorId}/aggregates?${search.toString()}`)
  if (!res.ok) throw new Error(`Failed to fetch aggregates for sensor ${sensorId}`)
  const json = await res.json()
  if (!json?.success) throw new Error(json?.error ?? 'Invalid API response')
  series.value = {
    ...series.value,
    [sensorId]: normalizeBuckets(json.data ?? []),
  }
}

const fetchAllAggregates = async () => {
  if (!sensors.value.length) return
  const results = await Promise.allSettled(
    sensors.value.map(sensor =>
      fetchAgg(sensor.id).catch(err => {
        console.error(`Aggregation error for sensor ${sensor.id}`, err)
        throw err
      })
    )
  )
}

const loadTrends = async (refreshOnly = false) => {
  if (refreshOnly) {
    refreshing.value = true
  } else {
    loading.value = true
  }
  error.value = null
  try {
    if (!refreshOnly) {
      await fetchSensors()
    }
    await fetchAllAggregates()
  } catch (err:any) {
    error.value = err?.message ?? 'An error occurred while loading data'
    console.error('Trends load failed', err)
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

const fallbackLabel = (bucket:string|null) => {
  if (!bucket) return 'N/A'
  const date = new Date(bucket)
  if (Number.isNaN(date.valueOf())) return 'N/A'
  
  if (selectedPeriod.value === 'hour') {
      return date.toLocaleTimeString('ro-RO', { hour:'2-digit', minute:'2-digit' })
  }
  return date.toLocaleDateString('ro-RO', { day:'2-digit', month:'short' })
}

const colors:Record<string,string> = { 
    temperature: '#ef4444', 
    humidity: '#3b82f6', 
    soil: '#22c55e', 
    default: '#eab308' 
}

const chartData = (sensor:Sensor) => {
  const data = series.value[sensor.id] || []
  const color = colors[sensor.type] || colors.default
  
  return {
    labels: data.map(b => fallbackLabel(b.bucket)),
    datasets: [{
      label: `${sensor.name}`,
      data: data.map(b => b.avg ?? 0),
      borderColor: color,
      backgroundColor: color + '20', // transparent fill
      borderWidth: 2,
      pointRadius: 3,
      pointHoverRadius: 5,
      fill: true,
      tension: 0.4,
    }]
  }
}

const chartOptions = {
    responsive: true, 
    maintainAspectRatio: false, 
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(0,0,0,0.8)',
            padding: 12,
            titleFont: { size: 13 },
            bodyFont: { size: 13 },
            cornerRadius: 8,
            displayColors: false,
        }
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 6, font: { size: 10 } }
        },
        y: {
            grid: { color: 'rgba(200,200,200,0.1)' },
            border: { dash: [4, 4] },
            ticks: { font: { size: 10 } }
        }
    }
}

watch(selectedPeriod, () => {
  if (!sensors.value.length) return
  loadTrends(true)
})

onMounted(() => {
  loadTrends()
})
</script>

<template>
    <Head title="Trends" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            
            <!-- Header & Controls -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-6 shadow-sm">
                <div>
                     <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <BarChart3 class="w-6 h-6 text-indigo-500" />
                        Historical Trends
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Visualize sensor data aggregation over time.</p>
                </div>

                <div class="flex items-center gap-4">
                     <!-- Refresh Button -->
                    <button 
                        @click="loadTrends(true)" 
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-zinc-800 transition-colors text-gray-500 dark:text-gray-400"
                        :class="{ 'animate-spin': refreshing }"
                        title="Refresh Data"
                    >
                        <RefreshCw class="w-5 h-5" />
                    </button>

                    <!-- Period Selector -->
                    <div class="inline-flex rounded-lg border border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-900/50 p-1">
                        <button 
                            v-for="(config, key) in periodConfig" 
                            :key="key"
                            @click="selectedPeriod = key"
                            class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
                            :class="selectedPeriod === key 
                                ? 'bg-white dark:bg-zinc-800 text-indigo-600 dark:text-indigo-400 shadow-sm' 
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'"
                        >
                            {{ key.charAt(0).toUpperCase() + key.slice(1) }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Error State -->
            <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-600 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-400 flex items-center gap-2">
                <Activity class="w-5 h-5" />
                {{ error }}
            </div>

            <!-- Loading Skeleton -->
            <div v-if="loading && !sensors.length" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                 <div v-for="i in 4" :key="i" class="h-64 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 animate-pulse" />
            </div>

            <!-- Empty State -->
            <div v-else-if="!loading && !sensors.length" class="flex flex-col items-center justify-center py-12 text-center">
                 <div class="p-4 rounded-full bg-gray-100 dark:bg-zinc-900 mb-4">
                    <Radio class="w-8 h-8 text-gray-400" />
                 </div>
                 <h3 class="text-lg font-medium text-gray-900 dark:text-white">No Sensors Found</h3>
                 <p class="text-sm text-gray-500 dark:text-gray-400">Configure sensors to see their trends here.</p>
            </div>

            <!-- Charts Grid -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-for="s in sensors" :key="s.id" 
                     class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-6 shadow-sm flex flex-col h-[320px]"
                >
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                             <div class="p-2 rounded-lg bg-gray-50 dark:bg-zinc-800 text-gray-500 dark:text-gray-400">
                                <Thermometer v-if="s.type === 'temperature'" class="w-5 h-5" />
                                <Droplets v-else-if="s.type === 'humidity'" class="w-5 h-5" />
                                <Sprout v-else-if="s.type === 'soil'" class="w-5 h-5" />
                                <Radio v-else class="w-5 h-5" />
                             </div>
                             <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ s.name }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 capitalize">{{ s.type.replace('_',' ') }}</p>
                             </div>
                        </div>
                        <div class="text-xs font-mono text-gray-400 bg-gray-50 dark:bg-zinc-800 px-2 py-1 rounded">
                            {{ s.unit }}
                        </div>
                    </div>

                    <div class="flex-1 w-full relative min-h-0">
                        <Line
                            v-if="series[s.id]?.length"
                            :data="chartData(s)"
                            :options="chartOptions"
                        />
                        <div v-else class="absolute inset-0 flex items-center justify-center text-sm text-gray-400 flex-col gap-2">
                            <Activity class="w-8 h-8 opacity-20" />
                            <span v-if="refreshing">Updating data...</span>
                            <span v-else>No aggregated data available</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, onMounted, watch, computed } from 'vue'
import { apiFetch } from '@/lib/api'
import { type BreadcrumbItem } from '@/types';
import { 
    Activity, 
    AlertTriangle, 
    AlertOctagon,
    ArrowRight,
    Search,
    Settings2,
    Zap,
    Thermometer,
    Droplets,
    Sprout,
    Radio,
    Clock
} from 'lucide-vue-next';

interface Sensor { id:number; name:string; type:string; unit:string }
interface Anom { timestamp:string; value:number; z:number }

const sensors = ref<Sensor[]>([])
const selectedSensorId = ref<number|null>(null)
const anomalies = ref<Anom[]>([])
const loading = ref(false)
const savingSettings = ref(false)
const z = ref(3.0)
const windowSize = ref(30)
const hours = ref(6)

const presets = [
  { label: 'Relaxed', z: 3.5, window: 40, hours: 12, desc: 'Major deviations only' },
  { label: 'Balanced', z: 3.0, window: 30, hours: 6, desc: 'Standard monitoring' },
  { label: 'Strict', z: 2.5, window: 25, hours: 4, desc: 'High sensitivity' },
]

const loadSettings = async () => {
  try {
    const res = await apiFetch('/api/settings/anomaly-detection')
    const json = await res.json()
    if (json.success && json.data) {
      z.value = json.data.z ?? 3.0
      windowSize.value = json.data.window ?? 30
      hours.value = json.data.hours ?? 6
    }
  } catch (e) {
    console.error('Failed to load anomaly settings:', e)
  }
}

const saveSettings = async () => {
  savingSettings.value = true
  try {
    await apiFetch('/api/settings/anomaly-detection', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ z: z.value, window: windowSize.value, hours: hours.value })
    })
  } catch (e) {
    console.error('Failed to save anomaly settings:', e)
  } finally {
    savingSettings.value = false
  }
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Anomalies',
        href: '/dashboard/anomalies',
    },
];

const fetchSensors = async () => {
  try {
      const res = await apiFetch('/api/sensors')
      const json = await res.json()
      sensors.value = json.data?.map((s:any) => ({ id:s.id, name:s.name ?? s.sensor_name, type:s.type ?? s.sensor_type, unit:s.unit })) ?? []
      if (sensors.value.length && selectedSensorId.value === null) selectedSensorId.value = sensors.value[0].id
  } catch (e) {
      console.error(e)
  }
}

const fetchAnomalies = async () => {
  if (!selectedSensorId.value) return
  loading.value = true
  try {
      const res = await apiFetch(`/api/sensors/${selectedSensorId.value}/anomalies?window=${windowSize.value}&hours=${hours.value}&z=${z.value}`)
      const json = await res.json()
      const raw = json.data ?? []
      anomalies.value = (raw as any[]).map(a => {
        const zScore = Number(a.zscore ?? a.z ?? a.z_score ?? a.score)
        const val = Number(a.value ?? a.reading ?? a.v)
        const ts = a.timestamp ?? a.time ?? a.created_at
        return {
          timestamp: ts ?? new Date().toISOString(),
          value: isFinite(val) ? val : 0,
          z: isFinite(zScore) ? zScore : 0,
        }
      })
  } catch (e) {
      console.error(e);
  } finally {
      loading.value = false
  }
}

onMounted(async () => {
  await loadSettings()
  await fetchSensors()
  if (selectedSensorId.value) await fetchAnomalies()
})

watch([selectedSensorId], fetchAnomalies)
watch([z, windowSize, hours], () => {
  saveSettings()
  fetchAnomalies()
})

const sortedAnomalies = computed(() => anomalies.value.slice().sort((a,b)=> new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime()))
const highestZ = computed(() => anomalies.value.reduce((acc,a)=> Math.max(acc, Math.abs(a.z ?? 0)), 0))
const anomalyRate = computed(() => (anomalies.value.length / Math.max(hours.value, 1)).toFixed(2))

const activeDetectionMode = computed(() => {
  const preset = presets.find(p => p.z === z.value && p.window === windowSize.value && p.hours === hours.value)
  return preset ? preset.label : 'Custom'
})

const severityBuckets = computed(() => {
  const buckets = { mild:0, high:0, extreme:0 }
  const threshold = z.value
  anomalies.value.forEach(a => {
    const abs = Math.abs(a.z ?? 0)
    // Clasificare relativă la threshold-ul setat
    const ratio = abs / threshold
    if (ratio >= 1.5) buckets.extreme++      // >150% peste threshold
    else if (ratio >= 1.2) buckets.high++    // 120-150% peste threshold  
    else buckets.mild++                       // 100-120% peste threshold
  })
  return buckets
})

const formatTime = (ts: string) => {
    return new Date(ts).toLocaleString('ro-RO', { 
        day: '2-digit', month: '2-digit', 
        hour: '2-digit', minute: '2-digit', second: '2-digit' 
    });
}
</script>

<template>
    <Head title="Anomalies" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            
            <!-- Controls Header -->
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-6 shadow-sm">
                <div class="flex flex-col md:flex-row justify-between gap-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                             <AlertOctagon class="w-6 h-6 text-orange-500" />
                             Anomaly Detection
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Analyze historical data using Z-Score statistical deviation.
                        </p>
                    </div>

                    <!-- Sensor Selector -->
                    <div class="min-w-[200px]">
                        <label class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5 block">Target Sensor</label>
                        <div class="relative">
                            <select 
                                v-model="selectedSensorId" 
                                class="w-full appearance-none rounded-lg border border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-900/50 py-2.5 pl-4 pr-10 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                            >
                                <option v-for="s in sensors" :key="s.id" :value="s.id">
                                    {{ s.name }}
                                </option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-gray-500">
                                <Search class="w-4 h-4" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parameters Grid -->
                <div class="mt-6 pt-6 border-t border-gray-100 dark:border-zinc-800 grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <!-- Presets -->
                    <div>
                        <label class="flex items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                            <Settings2 class="w-3.5 h-3.5" />
                            Detection Mode
                        </label>
                        <div class="grid grid-cols-3 gap-3">
                            <button
                                v-for="p in presets"
                                :key="p.label"
                                @click="() => { z = p.z; windowSize = p.window; hours = p.hours; }"
                                :disabled="savingSettings"
                                class="flex flex-col items-center justify-center p-3 rounded-lg border transition-all hover:bg-gray-50 dark:hover:bg-zinc-800 disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="z === p.z && windowSize === p.window && hours === p.hours 
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/10 ring-1 ring-indigo-500 text-indigo-700 dark:text-indigo-400' 
                                    : 'border-gray-200 dark:border-zinc-800 text-gray-600 dark:text-gray-400'"
                            >
                                <span class="text-sm font-semibold">{{ p.label }}</span>
                                <span class="text-[10px] opacity-70 mt-1">{{ p.desc }}</span>
                            </button>
                        </div>
                        <p v-if="savingSettings" class="text-[10px] text-indigo-500 mt-2 flex items-center gap-1">
                            <span class="inline-block w-3 h-3 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></span>
                            Saving...
                        </p>
                    </div>

                    <!-- Fine Tuning -->
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                             <label class="text-xs text-gray-500 mb-1.5 block">Threshold (Z-Score)</label>
                             <input type="number" step="0.1" v-model="z" class="w-full rounded-lg border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-900/50 text-sm py-2 px-3" />
                             <p class="text-[10px] text-gray-400 mt-1">Sensitivity level</p>
                        </div>
                        <div>
                             <label class="text-xs text-gray-500 mb-1.5 block">Window Size</label>
                             <input type="number" step="1" v-model="windowSize" class="w-full rounded-lg border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-900/50 text-sm py-2 px-3" />
                             <p class="text-[10px] text-gray-400 mt-1">Sample points</p>
                        </div>
                        <div>
                             <label class="text-xs text-gray-500 mb-1.5 block">Lookback (Hours)</label>
                             <input type="number" step="1" v-model="hours" class="w-full rounded-lg border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-900/50 text-sm py-2 px-3" />
                             <p class="text-[10px] text-gray-400 mt-1">Time range</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
             <div class="grid gap-4 grid-cols-1 md:grid-cols-4">
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 transition-all">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Anomalies</p>
                    <h4 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ anomalies.length }}</h4>
                </div>
                 <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 transition-all">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Max Severity (Z)</p>
                    <h4 class="text-2xl font-bold text-orange-600 mt-1">{{ highestZ.toFixed(2) }}</h4>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 transition-all">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Rate (Anom/Hr)</p>
                    <h4 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ anomalyRate }}</h4>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-4 flex gap-2 items-center">
                    <div class="flex-1 space-y-1">
                        <div class="flex justify-between text-xs text-gray-500"><span>Mild</span> <span>{{ severityBuckets.mild }}</span></div>
                         <div class="w-full bg-gray-100 rounded-full h-1.5 dark:bg-zinc-800"><div class="bg-yellow-400 h-1.5 rounded-full" :style="{width: (severityBuckets.mild/anomalies.length*100)+'%'}" ></div></div>
                         
                         <div class="flex justify-between text-xs text-gray-500"><span>High</span> <span>{{ severityBuckets.high }}</span></div>
                         <div class="w-full bg-gray-100 rounded-full h-1.5 dark:bg-zinc-800"><div class="bg-orange-500 h-1.5 rounded-full" :style="{width: (severityBuckets.high/anomalies.length*100)+'%'}" ></div></div>

                         <div class="flex justify-between text-xs text-gray-500"><span>Extreme</span> <span>{{ severityBuckets.extreme }}</span></div>
                         <div class="w-full bg-gray-100 rounded-full h-1.5 dark:bg-zinc-800"><div class="bg-red-600 h-1.5 rounded-full" :style="{width: (severityBuckets.extreme/anomalies.length*100)+'%'}" ></div></div>
                    </div>
                </div>
            </div>

            <!-- Anomalies List -->
             <div class="flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm overflow-hidden flex flex-col">
                <div class="p-6 border-b border-sidebar-border/70 dark:border-sidebar-border flex justify-between items-center">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Detected Anomalies</h3>
                            <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full" 
                                  :class="{
                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': activeDetectionMode === 'Relaxed',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': activeDetectionMode === 'Balanced',
                                    'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400': activeDetectionMode === 'Strict',
                                    'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400': activeDetectionMode === 'Custom'
                                  }">
                                {{ activeDetectionMode }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                           Points exceeding {{ z }}σ deviation from the moving average.
                        </p>
                    </div>
                    <div v-if="loading" class="flex items-center gap-2 text-indigo-500">
                         <Activity class="w-4 h-4 animate-spin" />
                         <span class="text-sm font-medium">Analyzing...</span>
                    </div>
                </div>

                <div class="overflow-auto flex-1">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 dark:bg-zinc-800/50 text-gray-500 dark:text-gray-400 font-medium border-b border-sidebar-border/70 dark:border-sidebar-border">
                             <tr>
                                <th class="py-3 px-6">Timestamp</th>
                                <th class="py-3 px-6">Reading Value</th>
                                <th class="py-3 px-6">Z-Score</th>
                                <th class="py-3 px-6 text-right">Severity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            <tr v-for="(anom, idx) in sortedAnomalies" :key="idx" class="hover:bg-gray-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="py-3 px-6 font-mono text-gray-600 dark:text-gray-400">
                                   {{ formatTime(anom.timestamp) }}
                                </td>
                                <td class="py-3 px-6 font-semibold text-gray-900 dark:text-white">
                                    {{ anom.value.toFixed(2) }}
                                </td>
                                <td class="py-3 px-6 text-gray-600 dark:text-gray-400">
                                    {{ anom.z.toFixed(2) }}
                                </td>
                                <td class="py-3 px-6 text-right">
                                    <span v-if="Math.abs(anom.z) >= 4" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                        Extreme
                                    </span>
                                    <span v-else-if="Math.abs(anom.z) >= 3" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400">
                                        High
                                    </span>
                                    <span v-else class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        Mild
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!loading && anomalies.length === 0">
                                <td colspan="4" class="py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <div class="p-3 bg-gray-50 dark:bg-zinc-800/50 rounded-full mb-3">
                                            <Zap class="w-6 h-6" />
                                        </div>
                                        <p class="font-medium">No anomalies detected</p>
                                        <p class="text-xs opacity-70 mt-1">Try adjusting the sensitivity thresholds.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

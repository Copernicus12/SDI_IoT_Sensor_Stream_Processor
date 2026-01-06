<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, onMounted, watch } from 'vue'
import { apiFetch } from '@/lib/api'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend)

interface Sensor { id:number; name:string; type:string; unit:string }
interface Bucket { bucket:string | null; avg:number|null; min:number|null; max:number|null; count:number|null }

type PeriodKey = 'hour'|'day'|'week'
const periodConfig:Record<PeriodKey, { hours:number; bucket:'hour'|'day' }> = {
  hour: { hours: 48, bucket: 'hour' },
  day: { hours: 24 * 14, bucket: 'day' },
  week: { hours: 24 * 7, bucket: 'day' },
}

const sensors = ref<Sensor[]>([])
const selectedPeriod = ref<PeriodKey>('hour')
const series = ref<Record<number, Bucket[]>>({})
const loading = ref(false)
const refreshing = ref(false)
const error = ref<string | null>(null)

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
  if (!res.ok) throw new Error(`Nu s-au putut descărca senzorii (HTTP ${res.status})`)
  const json = await res.json()
  if (!json?.success) throw new Error(json?.error ?? 'Răspuns invalid de la API')
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
  if (!res.ok) throw new Error(`Nu s-au putut descărca agregările pentru senzorul ${sensorId} (HTTP ${res.status})`)
  const json = await res.json()
  if (!json?.success) throw new Error(json?.error ?? 'Răspuns invalid de la API')
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
        console.error(`Eroare agregare pentru senzorul ${sensor.id}`, err)
        throw err
      })
    )
  )
  const failure = results.find(r => r.status === 'rejected')
  if (failure && failure.status === 'rejected') {
    throw failure.reason ?? new Error('Nu s-au putut încărca toate agregările')
  }
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
    error.value = err?.message ?? 'A apărut o eroare la încărcarea datelor'
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
  return date.toLocaleString('ro-RO', { hour:'2-digit', day:'2-digit', month:'2-digit' })
}

const colors:Record<string,string> = { temperatura:'#ef4444', umiditate:'#3b82f6', umiditate_sol:'#22c55e', curent:'#eab308' }

const chartData = (sensor:Sensor) => {
  const data = series.value[sensor.id] || []
  return {
    labels: data.map(b => fallbackLabel(b.bucket)),
    datasets: [{
      label: `${sensor.name}`,
      data: data.map(b => b.avg ?? 0),
      borderColor: colors[sensor.type] || '#64748b',
      backgroundColor: 'transparent',
      tension: 0.3,
    }]
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
  <AppLayout>
    <Head title="Trends" />
    <div class="container mx-auto px-6 py-8 max-w-7xl">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Trends</h1>
        <select v-model="selectedPeriod" class="rounded-md border border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-3 py-1 text-sm">
          <option value="hour">Hourly</option>
          <option value="day">Daily</option>
          <option value="week">Weekly</option>
        </select>
      </div>

      <div v-if="error" class="mb-6 rounded-md border border-red-200 bg-red-50 text-red-700 dark:border-red-900/50 dark:bg-red-950/40 px-4 py-3 text-sm">
        {{ error }}
      </div>

      <template v-if="loading">
        <div class="text-sm text-slate-500 dark:text-slate-400">Se încarcă datele de trend...</div>
      </template>
      <template v-else-if="!sensors.length">
        <div class="text-sm text-slate-500 dark:text-slate-400">Nu există senzori configurați.</div>
      </template>
      <template v-else>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card v-for="s in sensors" :key="s.id" class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
            <CardHeader>
              <CardTitle>{{ s.name }}</CardTitle>
              <CardDescription class="capitalize">{{ s.type.replace('_',' ') }}</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="h-48">
                <Line
                  v-if="series[s.id]?.length"
                  :data="chartData(s)"
                  :options="{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }"
                />
                <div v-else class="text-sm text-slate-500 flex items-center gap-2">
                  <span v-if="refreshing">Actualizăm datele...</span>
                  <span v-else>Nu există suficiente date pentru perioada selectată.</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

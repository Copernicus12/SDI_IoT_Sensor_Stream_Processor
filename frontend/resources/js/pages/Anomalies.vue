<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, onMounted, watch, computed } from 'vue'
import { apiFetch } from '@/lib/api'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

interface Sensor { id:number; name:string; type:string; unit:string }
interface Anom { timestamp:string; value:number; z:number }

const sensors = ref<Sensor[]>([])
const selectedSensorId = ref<number|null>(null)
const anomalies = ref<Anom[]>([])
const loading = ref(false)
const z = ref(3.0)
const windowSize = ref(30)
const hours = ref(6)
const presets = [
  { label: 'Relaxat', z: 3.5, window: 40, hours: 12 },
  { label: 'Echilibrat', z: 3.0, window: 30, hours: 6 },
  { label: 'Strict', z: 2.5, window: 25, hours: 4 },
]

const fetchSensors = async () => {
  const res = await apiFetch('/api/sensors')
  const json = await res.json()
  sensors.value = json.data?.map((s:any) => ({ id:s.id, name:s.name ?? s.sensor_name, type:s.type ?? s.sensor_type, unit:s.unit })) ?? []
  if (sensors.value.length && selectedSensorId.value === null) selectedSensorId.value = sensors.value[0].id
}

const fetchAnomalies = async () => {
  if (!selectedSensorId.value) return
  loading.value = true
  const res = await apiFetch(`/api/sensors/${selectedSensorId.value}/anomalies?window=${windowSize.value}&hours=${hours.value}&z=${z.value}`)
  const json = await res.json()
  const raw = json.data ?? []
  anomalies.value = (raw as any[]).map(a => {
    const zScore = Number(a.z ?? a.z_score ?? a.score)
    const val = Number(a.value ?? a.reading ?? a.v)
    const ts = a.timestamp ?? a.time ?? a.created_at
    return {
      timestamp: ts ?? new Date().toISOString(),
      value: isFinite(val) ? val : 0,
      z: isFinite(zScore) ? zScore : 0,
    }
  })
  loading.value = false
}

onMounted(async () => {
  await fetchSensors()
  await fetchAnomalies()
})

watch([selectedSensorId, z, windowSize, hours], fetchAnomalies)

const sortedAnomalies = computed(() => anomalies.value.slice().sort((a,b)=> new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime()))
const highestZ = computed(() => anomalies.value.reduce((acc,a)=> Math.max(acc, Math.abs(a.z ?? 0)), 0))
const anomalyRate = computed(() => anomalies.value.length / Math.max(hours.value, 1))
const severityBuckets = computed(() => {
  const buckets = { mild:0, high:0, extreme:0 }
  anomalies.value.forEach(a => {
    const abs = Math.abs(a.z ?? 0)
    if (abs >= 4) buckets.extreme++
    else if (abs >= 3) buckets.high++
    else buckets.mild++
  })
  return buckets
})
const latestAnomaly = computed(() => sortedAnomalies.value[0] ?? null)
</script>

<template>
  <AppLayout>
    <Head title="Anomalii" />
    <div class="container mx-auto px-6 py-8 max-w-7xl">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Anomalii</h1>
        <div class="flex flex-wrap gap-2 items-center text-sm justify-end">
          <select v-model.number="selectedSensorId" class="rounded-md border border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-3 py-1">
            <option v-for="s in sensors" :key="s.id" :value="s.id">{{ s.name }} ({{ s.type }})</option>
          </select>
          <label class="flex items-center gap-1">Z
            <input v-model.number="z" type="number" step="0.1" class="w-16 rounded-md border border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-2 py-1" />
          </label>
          <label class="flex items-center gap-1">Window
            <input v-model.number="windowSize" type="number" class="w-20 rounded-md border border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-2 py-1" />
          </label>
          <label class="flex items-center gap-1">Hours
            <input v-model.number="hours" type="number" class="w-16 rounded-md border border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-2 py-1" />
          </label>
          <div class="flex gap-1">
            <button
              v-for="p in presets"
              :key="p.label"
              type="button"
              class="px-3 py-1 rounded-md border text-xs"
              :class="z===p.z && windowSize===p.window && hours===p.hours ? 'border-blue-500 text-blue-600' : 'border-slate-300 dark:border-zinc-800 text-slate-600 dark:text-slate-300'"
              @click="() => { z = p.z; windowSize = p.window; hours = p.hours; fetchAnomalies(); }"
            >
              {{ p.label }}
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 text-sm">
        <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
          <CardHeader class="pb-2">
            <CardTitle>Total anomalii</CardTitle>
            <CardDescription>În fereastra selectată</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold">{{ anomalies.length }}</div>
            <div class="text-xs text-slate-500">Rată: {{ anomalyRate.toFixed(2) }} / h</div>
          </CardContent>
        </Card>
        <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
          <CardHeader class="pb-2">
            <CardTitle>Severitate maximă</CardTitle>
            <CardDescription>Cel mai mare |Z| detectat</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="text-3xl font-bold">{{ highestZ.toFixed(2) }}</div>
            <div class="text-xs text-slate-500">Ultima: {{ latestAnomaly ? new Date(latestAnomaly.timestamp).toLocaleString('ro-RO') : 'n/a' }}</div>
          </CardContent>
        </Card>
        <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
          <CardHeader class="pb-2">
            <CardTitle>Distribuție severitate</CardTitle>
            <CardDescription>Mild / High / Extreme</CardDescription>
          </CardHeader>
          <CardContent class="space-y-2">
            <div class="flex items-center gap-2">
              <span class="w-16 text-xs text-slate-500">Mild</span>
              <div class="flex-1 h-2 bg-slate-200 dark:bg-zinc-900 rounded">
                <div class="h-2 rounded bg-emerald-500" :style="{ width: anomalies.length ? (severityBuckets.mild / anomalies.length) * 100 + '%' : '0%' }"></div>
              </div>
              <span class="w-6 text-right">{{ severityBuckets.mild }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-16 text-xs text-slate-500">High</span>
              <div class="flex-1 h-2 bg-slate-200 dark:bg-zinc-900 rounded">
                <div class="h-2 rounded bg-amber-500" :style="{ width: anomalies.length ? (severityBuckets.high / anomalies.length) * 100 + '%' : '0%' }"></div>
              </div>
              <span class="w-6 text-right">{{ severityBuckets.high }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-16 text-xs text-slate-500">Extreme</span>
              <div class="flex-1 h-2 bg-slate-200 dark:bg-zinc-900 rounded">
                <div class="h-2 rounded bg-red-500" :style="{ width: anomalies.length ? (severityBuckets.extreme / anomalies.length) * 100 + '%' : '0%' }"></div>
              </div>
              <span class="w-6 text-right">{{ severityBuckets.extreme }}</span>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
        <CardHeader>
          <CardTitle>Outliers detectate</CardTitle>
          <CardDescription>Pe baza scorului Z cu fereastră rulantă</CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="loading" class="text-sm text-slate-500">Se încarcă...</div>
          <div v-else>
            <div v-if="anomalies.length === 0" class="text-sm text-slate-500">Nu s-au găsit anomalii pentru setările curente.</div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="text-left border-b border-slate-200 dark:border-zinc-800">
                    <th class="py-2 pr-4">Timp</th>
                    <th class="py-2 pr-4">Valoare</th>
                    <th class="py-2 pr-4">Z-score</th>
                    <th class="py-2 pr-4">Severitate</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="a in sortedAnomalies" :key="a.timestamp" class="border-b border-slate-100 dark:border-zinc-900">
                    <td class="py-2 pr-4">{{ new Date(a.timestamp).toLocaleString('ro-RO') }}</td>
                    <td class="py-2 pr-4 font-semibold">{{ Number.isFinite(a.value) ? a.value.toFixed(2) : '--' }}</td>
                    <td class="py-2 pr-4" :class="Math.abs(a.z ?? 0) >= 3 ? 'text-red-500' : 'text-amber-500'">{{ Number.isFinite(a.z) ? a.z.toFixed(2) : '--' }}</td>
                    <td class="py-2 pr-4">
                      <span
                        class="px-2 py-1 rounded-full text-xs font-semibold"
                        :class="Math.abs(a.z ?? 0) >= 4 ? 'bg-red-500/10 text-red-600 border border-red-500/30' : Math.abs(a.z ?? 0) >= 3 ? 'bg-amber-500/10 text-amber-700 border border-amber-500/30' : 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/30'"
                      >
                        {{ Math.abs(a.z ?? 0) >= 4 ? 'Extreme' : Math.abs(a.z ?? 0) >= 3 ? 'High' : 'Mild' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

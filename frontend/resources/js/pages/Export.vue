<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { apiFetch, apiUrl } from '@/lib/api'

interface Sensor { id:number; name:string; type:string; unit:string }

const sensors = ref<Sensor[]>([])
const selectedSensorId = ref<number|null>(null)
const hours = ref(24)
const token = ref('')

const fetchSensors = async () => {
  const res = await apiFetch('/api/sensors')
  const json = await res.json()
  sensors.value = json.data?.map((s:any) => ({ id:s.id, name:s.name ?? s.sensor_name, type:s.type ?? s.sensor_type, unit:s.unit })) ?? []
  if (sensors.value.length && selectedSensorId.value === null) selectedSensorId.value = sensors.value[0].id
}

const csvUrl = computed(() => selectedSensorId.value ? apiUrl(`/api/export/sensors/${selectedSensorId.value}.csv?hours=${hours.value}${token.value?`&api_token=${encodeURIComponent(token.value)}`:''}`) : '#')
const jsonUrl = computed(() => selectedSensorId.value ? apiUrl(`/api/export/sensors/${selectedSensorId.value}.json?hours=${hours.value}${token.value?`&api_token=${encodeURIComponent(token.value)}`:''}`) : '#')

onMounted(fetchSensors)
</script>

<template>
  <AppLayout>
    <Head title="Export" />
    <div class="container mx-auto px-6 py-8 max-w-3xl">
      <Card class="bg-white dark:bg-zinc-950 border-slate-200 dark:border-zinc-900">
        <CardHeader>
          <CardTitle>Export Date</CardTitle>
          <CardDescription>CSV / JSON, protejat cu API token</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex flex-col gap-4 text-sm">
            <label class="flex items-center justify-between gap-4">
              <span>Senzor</span>
              <select v-model.number="selectedSensorId" class="rounded-md border border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-3 py-1">
                <option v-for="s in sensors" :key="s.id" :value="s.id">{{ s.name }} ({{ s.type }})</option>
              </select>
            </label>

            <label class="flex items-center justify-between gap-4">
              <span>Orizont (ore)</span>
              <input v-model.number="hours" type="number" min="1" max="720" class="w-24 rounded-md border border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-2 py-1" />
            </label>

            <label class="flex items-center justify-between gap-4">
              <span>API Token</span>
              <input v-model="token" type="text" placeholder="lipeste tokenul" class="flex-1 rounded-md border border-slate-300 dark:border-zinc-800 bg-white dark:bg-zinc-950 px-2 py-1" />
            </label>

            <div class="flex items-center gap-3 pt-2">
              <a :href="csvUrl" class="rounded-md bg-emerald-500 px-4 py-2 text-white font-semibold hover:bg-emerald-600" download>Descarcă CSV</a>
              <a :href="jsonUrl" class="rounded-md border border-slate-300 dark:border-zinc-800 px-4 py-2 hover:bg-slate-50 dark:hover:bg-zinc-900">Descarcă JSON</a>
            </div>

            <p class="text-xs text-slate-500">Link-urile folosesc parametru ?api_token= pentru autentificare, alternativ poți folosi header-ul X-API-Token.</p>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

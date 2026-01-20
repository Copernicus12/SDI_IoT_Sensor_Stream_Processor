<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'
import { apiFetch, apiUrl } from '@/lib/api'
import { type BreadcrumbItem } from '@/types';
import { 
    Download, 
    FileJson, 
    FileText, 
    Key, 
    Search,
    Clock,
    Shield
} from 'lucide-vue-next';

interface Sensor { id:number; name:string; type:string; unit:string }

const sensors = ref<Sensor[]>([])
const selectedSensorId = ref<number|null>(null)
const hours = ref(24)
const token = ref('')

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Export',
        href: '/dashboard/export',
    },
];

const fetchSensors = async () => {
  try {
      const res = await apiFetch('/api/sensors')
      const json = await res.json()
      sensors.value = json.data?.map((s:any) => ({ id:s.id, name:s.name ?? s.sensor_name, type:s.type ?? s.sensor_type, unit:s.unit })) ?? []
      if (sensors.value.length && selectedSensorId.value === null) selectedSensorId.value = sensors.value[0].id
  } catch (e) {
      console.error("Failed to fetch sensors", e)
  }
}

const csvUrl = computed(() => selectedSensorId.value ? apiUrl(`/api/export/sensors/${selectedSensorId.value}.csv?hours=${hours.value}${token.value?`&api_token=${encodeURIComponent(token.value)}`:''}`) : '#')
const jsonUrl = computed(() => selectedSensorId.value ? apiUrl(`/api/export/sensors/${selectedSensorId.value}.json?hours=${hours.value}${token.value?`&api_token=${encodeURIComponent(token.value)}`:''}`) : '#')

onMounted(fetchSensors)
</script>

<template>
    <Head title="Export Data" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4">
            
            <div class="max-w-4xl mx-auto w-full">
                <!-- Header -->
                <div class="mb-8 text-center md:text-left">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2 justify-center md:justify-start">
                        <Download class="w-7 h-7 text-indigo-500" />
                        Data Export Center
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 mt-2">
                        Download historical sensor data in standard formats for external analysis.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Main Configuration Card -->
                    <div class="md:col-span-2 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 shadow-sm p-6">
                        <h3 class="font-semibold text-lg text-gray-900 dark:text-white mb-6 border-b border-gray-100 dark:border-zinc-800 pb-4">
                            Export Configuration
                        </h3>

                        <div class="space-y-6">
                            <!-- Sensor Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Sensor</label>
                                <div class="relative">
                                    <select 
                                        v-model="selectedSensorId" 
                                        class="w-full appearance-none rounded-lg border border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-900/50 py-3 pl-4 pr-10 text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                                    >
                                        <option v-for="s in sensors" :key="s.id" :value="s.id">
                                            {{ s.name }} ({{ s.type }} - {{ s.type === 'umiditate_sol' ? 'ADC' : s.unit }})
                                        </option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                        <Search class="w-4 h-4" />
                                    </div>
                                </div>
                            </div>

                            <!-- Time Range -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Time Horizon (Hours)</label>
                                <div class="relative">
                                    <input 
                                        v-model.number="hours" 
                                        type="number" 
                                        min="1" 
                                        max="720"
                                        class="w-full rounded-lg border border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-900/50 py-3 pl-4 pr-10 text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                                    />
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                        <Clock class="w-4 h-4" />
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Max limit: 720 hours (30 days)</p>
                            </div>

                            <!-- API Token (Required) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    API Token <span class="text-red-500 font-normal">*</span>
                                </label>
                                <div class="relative">
                                    <input 
                                        v-model="token" 
                                        type="text" 
                                        placeholder="dev-12345" 
                                        class="w-full rounded-lg border border-gray-200 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-900/50 py-3 pl-4 pr-10 text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all font-mono"
                                    />
                                    <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                        <Key class="w-4 h-4" />
                                    </div>
                                </div>
                                <div class="flex items-start gap-2 mt-2 text-xs text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-900/10 p-2 rounded-md">
                                    <Shield class="w-3.5 h-3.5 mt-0.5 shrink-0" />
                                    <p>Security enforced. Use token <b>dev-12345</b> for testing.</p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4">
                                <a 
                                    :href="csvUrl" 
                                    download 
                                    class="flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-white font-medium hover:bg-emerald-700 active:transform active:scale-95 transition-all shadow-sm hover:shadow-md"
                                >
                                    <FileText class="w-5 h-5" />
                                    Download CSV
                                </a>
                                <a 
                                    :href="jsonUrl" 
                                    target="_blank"
                                    class="flex items-center justify-center gap-2 rounded-lg border border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-4 py-3 text-gray-700 dark:text-gray-200 font-medium hover:bg-gray-50 dark:hover:bg-zinc-700 active:transform active:scale-95 transition-all shadow-sm"
                                >
                                    <FileJson class="w-5 h-5 text-yellow-600 dark:text-yellow-500" />
                                    Export JSON
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Info/API Help Card -->
                    <div class="md:col-span-1 space-y-6">
                         <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-6 shadow-sm">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                API Access
                            </h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                                You can automate data retrieval using  or external scripts.
                            </p>
                            
                            <div class="bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-3 overflow-x-auto">
                                <code class="text-xs text-gray-800 dark:text-gray-300 font-mono whitespace-nowrap">
                                    curl -H "X-API-Token: YOUR_TOKEN"<br/>
                                    {{ apiUrl('/api/export/...') }}
                                </code>
                            </div>
                        </div>

                        <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border bg-white dark:bg-zinc-900 p-6 shadow-sm">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Format Details</h4>
                            <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                                <li class="flex items-start gap-2">
                                    <span class="font-bold text-gray-900 dark:text-white min-w-[40px]">CSV:</span>
                                    <span>Standard comma-separated values, timestamp in ISO-8601. Best for Excel.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="font-bold text-gray-900 dark:text-white min-w-[40px]">JSON:</span>
                                    <span>Structured data array. Best for programmatic integration.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

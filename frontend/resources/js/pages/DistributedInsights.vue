<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import type { BreadcrumbItem } from '@/types'
import { computed } from 'vue'
import { Cpu, Database, Timer, AlertTriangle, Network, Gauge, Sigma } from 'lucide-vue-next'

type CorrelationRow = {
  a: string
  b: string
  r: number | null
  n: number
}

type MetricRow = {
  sensor_type: string
  sensor_name: string
  unit: string
  latest: number | null
  mean: number | null
  std: number | null
  z: number | null
  count: number
}

type NodeSummary = {
  node_id: string
  label: string
  last_update: string
  staleness_seconds: number | null
  throughput_rpm: number
  availability: number
  missing_minutes: number
  metrics: MetricRow[]
}

type NodeOffset = {
  node_id: string
  label: string
  offset_from_freshest_seconds: number | null
  staleness_seconds: number | null
  availability: number
  missing_minutes: number
}

type Insights = {
  computedAt: string
  windowMinutes: number
  rawReadingsCount: number
  bucketCount: number
  nodeSummaries: NodeSummary[]
  nodeDiagnostics: {
    freshest_node_timestamp: string | null
    node_offsets: NodeOffset[]
  }
  correlations: CorrelationRow[]
  distributedHealth: {
    score: number
    completeness: number
    skew_seconds: number
    notes: string[]
  }
}

const props = defineProps<{ insights: Insights }>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Distributed Insights', href: '/dashboard/distributed-insights' },
]

const compressionRatio = computed(() => {
  const raw = props.insights.rawReadingsCount || 0
  const bucket = props.insights.bucketCount || 0
  if (!raw || !bucket) return null
  return Math.round((raw / bucket) * 10) / 10
})

const zLevel = (z: number | null) => {
  if (z === null || !Number.isFinite(z)) return 'ok'
  const a = Math.abs(z)
  if (a >= 3) return 'critical'
  if (a >= 2) return 'warn'
  return 'ok'
}

const levelClasses = (level: string) => {
  if (level === 'critical') return 'text-red-600 dark:text-red-400'
  if (level === 'warn') return 'text-amber-600 dark:text-amber-400'
  return 'text-emerald-600 dark:text-emerald-400'
}

const fmt = (v: number | null, digits = 2) => {
  if (v === null || !Number.isFinite(v)) return '--'
  return v.toFixed(digits)
}

const pct = (v: number | null) => {
  if (v === null || !Number.isFinite(v)) return '--'
  return `${Math.round(v * 100)}%`
}
</script>

<template>
  <Head title="Distributed Insights" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h1 class="text-2xl font-semibold tracking-tight">Distributed Insights</h1>
            <p class="text-sm text-muted-foreground">
              Window: {{ insights.windowMinutes }} min · Computed at: {{ insights.computedAt }}
            </p>
          </div>
          <div class="hidden items-center gap-2 text-sm text-muted-foreground md:flex">
            <Network class="h-4 w-4" />
            <span>Cross-node processing</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div
          class="rounded-xl border border-sidebar-border/70 bg-white p-6 shadow-sm dark:border-sidebar-border dark:bg-zinc-900"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Gauge class="h-4 w-4" />
                Distributed Health
              </div>
              <div class="mt-2 text-3xl font-semibold">{{ insights.distributedHealth.score }}/100</div>
              <div class="mt-1 text-sm text-muted-foreground">
                Completeness: {{ fmt(insights.distributedHealth.completeness, 3) }} · Skew: {{ insights.distributedHealth.skew_seconds }}s
              </div>
            </div>
            <div class="rounded-lg border border-sidebar-border/70 p-2 dark:border-sidebar-border">
              <Cpu class="h-5 w-5 text-muted-foreground" />
            </div>
          </div>
          <div v-if="insights.distributedHealth.notes?.length" class="mt-4 space-y-1 text-sm text-muted-foreground">
            <div v-for="n in insights.distributedHealth.notes" :key="n" class="flex items-start gap-2">
              <AlertTriangle class="mt-0.5 h-4 w-4" />
              <span>{{ n }}</span>
            </div>
          </div>
        </div>

        <div
          class="rounded-xl border border-sidebar-border/70 bg-white p-6 shadow-sm dark:border-sidebar-border dark:bg-zinc-900"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Database class="h-4 w-4" />
                Edge Aggregation Benefit
              </div>
              <div class="mt-2 text-3xl font-semibold">
                <span v-if="compressionRatio">{{ compressionRatio }}×</span>
                <span v-else>--</span>
              </div>
              <div class="mt-1 text-sm text-muted-foreground">
                Raw readings: {{ insights.rawReadingsCount }} · Minute buckets: {{ insights.bucketCount }}
              </div>
            </div>
            <div class="rounded-lg border border-sidebar-border/70 p-2 dark:border-sidebar-border">
              <Sigma class="h-5 w-5 text-muted-foreground" />
            </div>
          </div>
          <div class="mt-4 text-sm text-muted-foreground">
            Same signal, fewer messages — “edge” sends aggregates, “cloud” stores full stream.
          </div>
        </div>

        <div
          class="rounded-xl border border-sidebar-border/70 bg-white p-6 shadow-sm dark:border-sidebar-border dark:bg-zinc-900"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Timer class="h-4 w-4" />
                Staleness
              </div>
              <div class="mt-2 space-y-2">
                <div v-for="n in insights.nodeSummaries" :key="n.node_id" class="flex items-center justify-between gap-3">
                  <div class="text-sm font-medium">{{ n.label }}</div>
                  <div class="text-sm text-muted-foreground">
                    <span v-if="n.staleness_seconds !== null">{{ fmt(n.staleness_seconds, 2) }}s</span>
                    <span v-else>--</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="rounded-lg border border-sidebar-border/70 p-2 dark:border-sidebar-border">
              <Timer class="h-5 w-5 text-muted-foreground" />
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-sidebar-border/70 bg-white shadow-sm dark:border-sidebar-border dark:bg-zinc-900">
          <div class="border-b border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <h2 class="text-lg font-semibold">Node Summary (rolling stats)</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Mean/std computed on per-minute aggregates over the selected window.
            </p>
          </div>
          <div class="overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 dark:bg-zinc-800/90 dark:text-gray-400">
                <tr>
                  <th class="px-6 py-3 text-left font-medium">Node</th>
                  <th class="px-6 py-3 text-left font-medium">Metric</th>
                  <th class="px-6 py-3 text-right font-medium">Latest</th>
                  <th class="px-6 py-3 text-right font-medium">z-score</th>
                  <th class="px-6 py-3 text-right font-medium">rpm</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                <template v-for="node in insights.nodeSummaries" :key="node.node_id">
                  <tr v-for="(m, idx) in node.metrics" :key="node.node_id + '-' + m.sensor_type" class="hover:bg-gray-50/60 dark:hover:bg-zinc-800/40">
                    <td v-if="idx === 0" class="px-6 py-3 align-top" :rowspan="node.metrics.length">
                      <div class="font-medium">{{ node.label }}</div>
                      <div class="mt-1 text-xs text-muted-foreground">Last: {{ node.last_update }}</div>
                    </td>
                    <td class="px-6 py-3">
                      <div class="font-medium">{{ m.sensor_name }}</div>
                      <div class="text-xs text-muted-foreground">{{ m.unit }}</div>
                    </td>
                    <td class="px-6 py-3 text-right tabular-nums">
                      {{ fmt(m.latest, 2) }}
                    </td>
                    <td class="px-6 py-3 text-right tabular-nums" :class="levelClasses(zLevel(m.z))">
                      <span v-if="m.z !== null">{{ fmt(m.z, 2) }}</span>
                      <span v-else>--</span>
                    </td>
                    <td class="px-6 py-3 text-right tabular-nums">
                      <span v-if="idx === 0">{{ fmt(node.throughput_rpm, 2) }}</span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 bg-white shadow-sm dark:border-sidebar-border dark:bg-zinc-900">
          <div class="border-b border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <h2 class="text-lg font-semibold">Cross-Sensor Correlations</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Pearson $r$ on aligned minute buckets (only overlaps).
            </p>
          </div>
          <div class="overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 dark:bg-zinc-800/90 dark:text-gray-400">
                <tr>
                  <th class="px-6 py-3 text-left font-medium">A</th>
                  <th class="px-6 py-3 text-left font-medium">B</th>
                  <th class="px-6 py-3 text-right font-medium">r</th>
                  <th class="px-6 py-3 text-right font-medium">n</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                <tr v-for="row in insights.correlations" :key="row.a + '-' + row.b" class="hover:bg-gray-50/60 dark:hover:bg-zinc-800/40">
                  <td class="px-6 py-3 font-medium">{{ row.a }}</td>
                  <td class="px-6 py-3">{{ row.b }}</td>
                  <td class="px-6 py-3 text-right tabular-nums">
                    <span v-if="row.r !== null">{{ fmt(row.r, 4) }}</span>
                    <span v-else>--</span>
                  </td>
                  <td class="px-6 py-3 text-right tabular-nums">{{ row.n }}</td>
                </tr>
                <tr v-if="!insights.correlations?.length">
                  <td class="px-6 py-6 text-sm text-muted-foreground" colspan="4">
                    Not enough overlapping data in the window yet.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="p-6 text-sm text-muted-foreground">
            Tip: if sensors publish out-of-order or with gaps, $n$ drops and $r$ becomes less stable — this is a nice “distributed systems” talking point.
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-sidebar-border/70 bg-white shadow-sm dark:border-sidebar-border dark:bg-zinc-900">
          <div class="border-b border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <h2 class="text-lg font-semibold">Clock Skew / Ingestion Lag</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Offset is measured vs the freshest node’s latest timestamp.
            </p>
          </div>
          <div class="overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 dark:bg-zinc-800/90 dark:text-gray-400">
                <tr>
                  <th class="px-6 py-3 text-left font-medium">Node</th>
                  <th class="px-6 py-3 text-right font-medium">Offset (s)</th>
                  <th class="px-6 py-3 text-right font-medium">Staleness (s)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                <tr
                  v-for="n in insights.nodeDiagnostics.node_offsets"
                  :key="n.node_id"
                  class="hover:bg-gray-50/60 dark:hover:bg-zinc-800/40"
                >
                  <td class="px-6 py-3 font-medium">{{ n.label }}</td>
                  <td class="px-6 py-3 text-right tabular-nums">
                    <span v-if="n.offset_from_freshest_seconds !== null">{{ n.offset_from_freshest_seconds }}</span>
                    <span v-else>--</span>
                  </td>
                  <td class="px-6 py-3 text-right tabular-nums">
                    <span v-if="n.staleness_seconds !== null">{{ fmt(n.staleness_seconds, 2) }}</span>
                    <span v-else>--</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="p-6 text-sm text-muted-foreground">
            Freshest node at: {{ insights.nodeDiagnostics.freshest_node_timestamp ?? '--' }}.
          </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 bg-white shadow-sm dark:border-sidebar-border dark:bg-zinc-900">
          <div class="border-b border-sidebar-border/70 p-6 dark:border-sidebar-border">
            <h2 class="text-lg font-semibold">Data Availability / Gap Rate</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Availability is the fraction of minutes with at least one reading per node.
            </p>
          </div>
          <div class="overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 dark:bg-zinc-800/90 dark:text-gray-400">
                <tr>
                  <th class="px-6 py-3 text-left font-medium">Node</th>
                  <th class="px-6 py-3 text-right font-medium">Availability</th>
                  <th class="px-6 py-3 text-right font-medium">Missing min</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                <tr
                  v-for="n in insights.nodeDiagnostics.node_offsets"
                  :key="n.node_id + '-avail'"
                  class="hover:bg-gray-50/60 dark:hover:bg-zinc-800/40"
                >
                  <td class="px-6 py-3 font-medium">{{ n.label }}</td>
                  <td class="px-6 py-3 text-right tabular-nums">{{ pct(n.availability) }}</td>
                  <td class="px-6 py-3 text-right tabular-nums">{{ n.missing_minutes }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="p-6 text-sm text-muted-foreground">
            Use this in your presentation as an “effective loss/gap rate” over the last {{ insights.windowMinutes }} minutes.
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { apiFetch } from '@/lib/api';

withDefaults(
  defineProps<{ canRegister: boolean }>(),
  { canRegister: true },
);

type Sensor = { id:number; type:string; unit:string; latest_value:number|null };
const sensors = ref<Sensor[]>([]);
const loading = ref(true);

const sensorCounts = computed(() => ({
  total: sensors.value.length,
  dht11: sensors.value.filter(s=> s.type==='temperatura' || s.type==='umiditate').length,
  soil: sensors.value.filter(s=> s.type==='umiditate_sol').length,
  acs: sensors.value.filter(s=> s.type==='curent').length,
}));

const onlineText = computed(() => `${sensorCounts.value.total} senzori · DHT11 ${sensorCounts.value.dht11} · Soil ${sensorCounts.value.soil} · ACS ${sensorCounts.value.acs}`);

onMounted(async () => {
  try {
  const res = await apiFetch('/api/sensors');
    const json = await res.json();
    if (json?.success) sensors.value = json.data as Sensor[];
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <Head title="Welcome" />
  <div class="relative min-h-screen overflow-hidden bg-white text-slate-900 dark:bg-black dark:text-white">
    <!-- Subtle animated background -->
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute -top-1/3 left-1/2 h-[600px] w-[900px] -translate-x-1/2 rounded-full bg-gradient-to-r from-emerald-500/15 via-cyan-500/10 to-sky-500/15 blur-3xl"></div>
      <PlaceholderPattern />
    </div>

    <!-- Top bar -->
    <header class="relative z-10">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
        <Link :href="dashboard()" class="flex items-center gap-2">
          <AppLogoIcon class="size-8" />
          <span class="text-sm font-semibold tracking-wide text-slate-600 dark:text-slate-300">IoT Distributed Processor</span>
        </Link>
        <nav class="flex items-center gap-2 text-sm">
          <Link v-if="$page.props.auth.user" :href="dashboard()" class="rounded-md border border-slate-300/60 px-4 py-1.5 hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-900">Dashboard</Link>
          <template v-else>
            <Link :href="login()" class="rounded-md px-4 py-1.5 hover:bg-slate-50 dark:hover:bg-zinc-900">Log in</Link>
            <Link v-if="canRegister" :href="register()" class="rounded-md border border-slate-300/60 px-4 py-1.5 hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-900">Register</Link>
          </template>
        </nav>
      </div>
    </header>

    <!-- Hero -->
    <section class="relative z-10">
      <div class="mx-auto max-w-7xl px-6 pt-10 pb-8 lg:pt-16">
        <div class="mx-auto max-w-3xl text-center">
          <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/5 px-3 py-1 text-xs text-emerald-600 dark:text-emerald-400">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            Realtime MQTT · Analytics · Vue + Laravel
          </div>
          <h1 class="text-balance text-4xl font-extrabold sm:text-5xl lg:text-6xl">
            Monitorizează, analizează și acționează pe datele senzorilor
          </h1>
          <p class="mt-4 text-pretty text-base text-slate-600 dark:text-slate-400 sm:text-lg">
            Un dashboard modern pentru ESP32: DHT11, umiditate sol și curent ACS712. Live charts, rapoarte pe 24h și insights utile pentru decizii rapide.
          </p>
          <div class="mt-6 flex items-center justify-center gap-3">
            <Link
              v-if="$page.props.auth?.user"
              :href="dashboard()"
              class="rounded-md bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 hover:bg-emerald-600"
            >
              Intră în Dashboard
            </Link>
            <Link
              v-else
              :href="login()"
              class="rounded-md bg-emerald-500 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 hover:bg-emerald-600"
            >
              Intră în Dashboard
            </Link>
            <a href="#sensors" class="rounded-md border border-slate-300/60 px-5 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
              Explorează senzorii
            </a>
          </div>

          <div class="mt-6 text-xs text-slate-500 dark:text-slate-400">
            <span v-if="!loading">{{ onlineText }}</span>
            <span v-else>Se încarcă statusul…</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Sensors preview cards -->
    <section id="sensors" class="relative z-10">
      <div class="mx-auto max-w-7xl px-6 pb-16">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
          <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
            <CardHeader class="pb-2">
              <CardTitle class="flex items-center gap-2 text-xl font-bold"><span>🌡️</span> DHT11</CardTitle>
              <CardDescription>Temperatură & Umiditate aer</CardDescription>
            </CardHeader>
            <CardContent>
              <p class="text-sm text-slate-600 dark:text-slate-400">Grafice combinate, Heat Index, punct de rouă și distribuții. Ideal pentru climat interior.</p>
              <div class="mt-4">
                <Link :href="dashboardRoutes.dht11()" class="text-sm font-semibold text-emerald-600 hover:underline dark:text-emerald-400">Deschide pagina DHT11 →</Link>
              </div>
            </CardContent>
          </Card>

          <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
            <CardHeader class="pb-2">
              <CardTitle class="flex items-center gap-2 text-xl font-bold"><span>🌱</span> Soil</CardTitle>
              <CardDescription>Umiditate sol & recomandări</CardDescription>
            </CardHeader>
            <CardContent>
              <p class="text-sm text-slate-600 dark:text-slate-400">Praguri uscat/optim/ud, medii rulante, timp sub 30% și distribuții pe 2 ore.</p>
              <div class="mt-4">
                <Link :href="dashboardRoutes.soil()" class="text-sm font-semibold text-emerald-600 hover:underline dark:text-emerald-400">Deschide pagina Soil →</Link>
              </div>
            </CardContent>
          </Card>

          <Card class="border-2 dark:border-zinc-900 bg-white dark:bg-zinc-950">
            <CardHeader class="pb-2">
              <CardTitle class="flex items-center gap-2 text-xl font-bold"><span>⚡</span> ACS712</CardTitle>
              <CardDescription>Curent, putere & energie</CardDescription>
            </CardHeader>
            <CardContent>
              <p class="text-sm text-slate-600 dark:text-slate-400">Curent RMS, vârfuri, integrare energie (Wh) și estimări zilnice.</p>
              <div class="mt-4">
                <Link :href="dashboardRoutes.acs()" class="text-sm font-semibold text-emerald-600 hover:underline dark:text-emerald-400">Deschide pagina ACS712 →</Link>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Small status strip -->
        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-zinc-900 dark:bg-zinc-950">
            <div class="text-slate-500">Senzori activi</div>
            <div class="mt-1 text-2xl font-extrabold">{{ sensorCounts.total }}</div>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-zinc-900 dark:bg-zinc-950">
            <div class="text-slate-500">Evenimente live</div>
            <div class="mt-1 text-2xl font-extrabold">MQTT</div>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-zinc-900 dark:bg-zinc-950">
            <div class="text-slate-500">Temă</div>
            <div class="mt-1 text-2xl font-extrabold">Dark Black</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer CTA -->
    <footer class="relative z-10 border-t border-slate-200/60 bg-white/70 backdrop-blur-md dark:border-zinc-900 dark:bg-black/50">
      <div class="mx-auto flex max-w-7xl flex-col items-center gap-3 px-6 py-6 sm:flex-row sm:justify-between">
        <div class="text-sm text-slate-500 dark:text-slate-400">Ready to explore datele în timp real?</div>
        <div class="flex gap-3">
          <Link
            v-if="$page.props.auth?.user"
            :href="dashboard()"
            class="rounded-md bg-emerald-500 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-600"
          >
            Deschide Dashboard
          </Link>
          <Link
            v-else
            :href="login()"
            class="rounded-md bg-emerald-500 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-600"
          >
            Deschide Dashboard
          </Link>
          <Link :href="dashboardRoutes.dht11()" class="rounded-md border border-slate-300/60 px-4 py-1.5 text-sm font-semibold hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-900">DHT11</Link>
          <Link :href="dashboardRoutes.soil()" class="rounded-md border border-slate-300/60 px-4 py-1.5 text-sm font-semibold hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-900">Soil</Link>
          <Link :href="dashboardRoutes.acs()" class="rounded-md border border-slate-300/60 px-4 py-1.5 text-sm font-semibold hover:bg-slate-50 dark:border-zinc-800 dark:hover:bg-zinc-900">ACS712</Link>
        </div>
      </div>
    </footer>
  </div>
</template>

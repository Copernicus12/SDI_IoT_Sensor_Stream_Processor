<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/api';
import { Activity, BarChart3, Network, Radio, Sprout, Thermometer, Zap, ArrowRight, Droplets } from 'lucide-vue-next';

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
  <div class="flex min-h-screen flex-col bg-gradient-to-br from-background via-background to-muted/20">
    <!-- Header -->
    <header class="sticky top-0 z-50 border-b border-sidebar-border/50 bg-background/80 backdrop-blur-xl supports-[backdrop-filter]:bg-background/80">
      <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Link :href="dashboard()" class="flex items-center gap-2 transition-opacity hover:opacity-80">
          <AppLogo />
        </Link>
        <nav class="flex items-center gap-2">
          <Button variant="outline" as-child size="sm" class="h-8">
            <Link :href="login()">Log in</Link>
          </Button>
          <Button v-if="canRegister" as-child size="sm" class="h-8">
            <Link :href="register()">Sign up</Link>
          </Button>
        </nav>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1">
      <!-- Hero Section - Compact -->
      <section class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="relative overflow-hidden rounded-2xl border border-sidebar-border/50 bg-gradient-to-br from-blue-500/10 via-purple-500/10 to-pink-500/10 p-8 sm:p-12 backdrop-blur-sm">
          <div class="absolute inset-0 bg-grid-white/5 [mask-image:radial-gradient(white,transparent_70%)]"></div>
          
          <div class="relative text-center space-y-4">
            <div class="inline-flex items-center gap-2 rounded-full border border-green-500/30 bg-green-500/10 px-3 py-1 text-xs font-medium backdrop-blur-sm">
              <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
              </span>
              <span class="text-green-700 dark:text-green-300">System Online · {{ loading ? '--' : sensorCounts.total }} sensors connected</span>
            </div>
            
            <div class="space-y-3">
              <h1 class="text-4xl font-bold tracking-tight sm:text-5xl bg-gradient-to-r from-foreground via-foreground to-foreground/70 bg-clip-text text-transparent">
                IoT Sensor Hub
              </h1>
              <p class="text-base sm:text-lg text-muted-foreground max-w-3xl mx-auto leading-relaxed">
                Platforma completa de monitorizare IoT cu procesare distribuita. Colecteaza, analizeaza si interpreteaza date de la senzori DHT11, umiditate sol si curent electric in timp real. Detectie automata de anomalii, alerte instant si rapoarte detaliate pentru o gestionare eficienta a infrastructurii tale IoT.
              </p>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
              <Button v-if="$page.props.auth?.user" as-child size="default" class="gap-2 shadow-lg shadow-primary/20">
                <Link :href="dashboard()">
                  <Activity class="h-4 w-4" />
                  Acceseaza Dashboard
                  <ArrowRight class="h-4 w-4" />
                </Link>
              </Button>
              <Button v-else as-child size="default" class="gap-2 shadow-lg shadow-primary/20">
                <Link :href="login()">
                  <Activity class="h-4 w-4" />
                  Incepe acum
                  <ArrowRight class="h-4 w-4" />
                </Link>
              </Button>
              <Button variant="outline" as-child size="default">
                <a href="#features">Explorează functionalitati</a>
              </Button>
            </div>

            <!-- Compact Stats Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-8 max-w-4xl mx-auto">
              <div class="rounded-lg border border-sidebar-border/50 bg-card/50 backdrop-blur-sm p-3 space-y-1">
                <div class="flex items-center justify-center">
                  <Activity class="h-4 w-4 text-green-500" />
                </div>
                <div class="text-2xl font-bold">{{ loading ? '--' : sensorCounts.total }}</div>
                <div class="text-xs text-muted-foreground">Senzori activi</div>
              </div>
              
              <div class="rounded-lg border border-sidebar-border/50 bg-card/50 backdrop-blur-sm p-3 space-y-1">
                <div class="flex items-center justify-center">
                  <Radio class="h-4 w-4 text-blue-500" />
                </div>
                <div class="text-2xl font-bold">Live</div>
                <div class="text-xs text-muted-foreground">Real-time stream</div>
              </div>
              
              <div class="rounded-lg border border-sidebar-border/50 bg-card/50 backdrop-blur-sm p-3 space-y-1">
                <div class="flex items-center justify-center">
                  <BarChart3 class="h-4 w-4 text-amber-500" />
                </div>
                <div class="text-2xl font-bold">24/7</div>
                <div class="text-xs text-muted-foreground">Monitoring non-stop</div>
              </div>

              <div class="rounded-lg border border-sidebar-border/50 bg-card/50 backdrop-blur-sm p-3 space-y-1">
                <div class="flex items-center justify-center">
                  <Network class="h-4 w-4 text-purple-500" />
                </div>
                <div class="text-2xl font-bold">Java</div>
                <div class="text-xs text-muted-foreground">Distributed backend</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Features Section - Compact & Modern -->
      <section id="features" class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center mb-8 space-y-2">
          <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">Functionalitati avansate</h2>
          <p class="text-sm sm:text-base text-muted-foreground max-w-2xl mx-auto">
            Ecosistem complet pentru monitorizare, analiza si optimizare. Platforma integreaza tehnologii moderne de procesare distribuita, machine learning pentru detectie de anomalii si API-uri RESTful pentru integrari custom.
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <!-- Trends -->
          <Link 
            href="/dashboard/trends"
            class="group relative overflow-hidden rounded-xl border border-sidebar-border/50 bg-gradient-to-br from-card to-card/50 backdrop-blur-sm p-5 transition-all hover:-translate-y-1 hover:shadow-xl hover:border-amber-500/50"
          >
            <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative space-y-3">
              <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/10 ring-1 ring-amber-500/20 group-hover:ring-amber-500/40 transition-all">
                  <BarChart3 class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                </div>
                <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:translate-x-1 group-hover:text-amber-500 transition-all" />
              </div>
              <div class="space-y-1">
                <h3 class="text-base font-semibold">Trends & Analytics</h3>
                <p class="text-xs text-muted-foreground leading-relaxed">
                  Vizualizari temporale avansate cu agregari pe 48 ore, 7 zile sau 14 zile. Grafice interactive Line Chart cu zoom, export CSV/JSON si comparatii multi-senzor pentru analiza comprehensiva.
                </p>
              </div>
              <div class="flex flex-wrap gap-1.5 pt-1">
                <span class="inline-flex items-center rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">48h</span>
                <span class="inline-flex items-center rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">7 days</span>
                <span class="inline-flex items-center rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">14 days</span>
              </div>
            </div>
          </Link>

          <!-- Distributed Insights -->
          <Link 
            href="/dashboard/distributed-insights"
            class="group relative overflow-hidden rounded-xl border border-sidebar-border/50 bg-gradient-to-br from-card to-card/50 backdrop-blur-sm p-5 transition-all hover:-translate-y-1 hover:shadow-xl hover:border-indigo-500/50"
          >
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative space-y-3">
              <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500/10 ring-1 ring-indigo-500/20 group-hover:ring-indigo-500/40 transition-all">
                  <Network class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                </div>
                <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:translate-x-1 group-hover:text-indigo-500 transition-all" />
              </div>
              <div class="space-y-1">
                <h3 class="text-base font-semibold">Distributed Insights</h3>
                <p class="text-xs text-muted-foreground leading-relaxed">
                  Procesare distribuita cu Java backend. Calculeaza corelatii intre senzori, genereaza health scores si optimizeaza performanta sistemului prin load balancing si cache distributed pentru scalabilitate maxima.
                </p>
              </div>
              <div class="flex flex-wrap gap-1.5 pt-1">
                <span class="inline-flex items-center rounded-md bg-indigo-500/10 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">Correlations</span>
                <span class="inline-flex items-center rounded-md bg-indigo-500/10 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">Health Score</span>
              </div>
            </div>
          </Link>

          <!-- Anomalies -->
          <Link 
            href="/dashboard/anomalies"
            class="group relative overflow-hidden rounded-xl border border-sidebar-border/50 bg-gradient-to-br from-card to-card/50 backdrop-blur-sm p-5 transition-all hover:-translate-y-1 hover:shadow-xl hover:border-red-500/50"
          >
            <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative space-y-3">
              <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-500/10 ring-1 ring-red-500/20 group-hover:ring-red-500/40 transition-all">
                  <Activity class="h-5 w-5 text-red-600 dark:text-red-400" />
                </div>
                <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:translate-x-1 group-hover:text-red-500 transition-all" />
              </div>
              <div class="space-y-1">
                <h3 class="text-base font-semibold">Detectie Anomalii</h3>
                <p class="text-xs text-muted-foreground leading-relaxed">
                  Algoritmi ML pentru identificare automata de pattern-uri anormale. Alerte instant prin WebSocket, notificari push si integrare email/SMS pentru interventie rapida in caz de deviatii critice.
                </p>
              </div>
              <div class="flex flex-wrap gap-1.5 pt-1">
                <span class="inline-flex items-center rounded-md bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-300">ML Detection</span>
                <span class="inline-flex items-center rounded-md bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-300">Real-time Alerts</span>
              </div>
            </div>
          </Link>
        </div>
      </section>

      <!-- Sensors Section - Compact & Modern -->
      <section id="sensors" class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center mb-8 space-y-2">
          <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">Senzori suportati</h2>
          <p class="text-sm sm:text-base text-muted-foreground max-w-2xl mx-auto">
            Monitorizare specializata pentru fiecare tip de senzor. Dashboard-uri dedicate cu metrici specifice, praguri configurabile si rapoarte personalizate pentru fiecare categorie de device IoT conectat.
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <!-- DHT11 -->
          <Link 
            :href="dashboardRoutes.dht11()"
            class="group relative overflow-hidden rounded-xl border border-sidebar-border/50 bg-gradient-to-br from-card to-card/50 backdrop-blur-sm p-5 transition-all hover:-translate-y-1 hover:shadow-xl hover:border-red-500/50"
          >
            <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative space-y-3">
              <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-500/10 ring-1 ring-red-500/20 group-hover:ring-red-500/40 transition-all">
                  <Thermometer class="h-5 w-5 text-red-600 dark:text-red-400" />
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-medium text-muted-foreground">{{ loading ? '--' : sensorCounts.dht11 }}</span>
                  <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:translate-x-1 group-hover:text-red-500 transition-all" />
                </div>
              </div>
              <div class="space-y-1">
                <h3 class="text-base font-semibold">DHT11 Sensor</h3>
                <p class="text-xs text-muted-foreground leading-relaxed">
                  Monitorizare temperatura si umiditate ambientala. Calibrare automata, istoric detaliat si grafice comparative pentru analiza conditiilor climatice. Ideal pentru sera, birouri sau spatii de productie.
                </p>
              </div>
              <div class="flex items-center gap-3 pt-1 text-xs">
                <div class="flex items-center gap-1 text-muted-foreground">
                  <Thermometer class="h-3 w-3" />
                  <span>-40°C to 80°C</span>
                </div>
                <span class="text-muted-foreground/50">•</span>
                <div class="flex items-center gap-1 text-muted-foreground">
                  <Droplets class="h-3 w-3" />
                  <span>0-100% RH</span>
                </div>
              </div>
            </div>
          </Link>

          <!-- Soil Sensor -->
          <Link 
            :href="dashboardRoutes.soil()"
            class="group relative overflow-hidden rounded-xl border border-sidebar-border/50 bg-gradient-to-br from-card to-card/50 backdrop-blur-sm p-5 transition-all hover:-translate-y-1 hover:shadow-xl hover:border-emerald-500/50"
          >
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative space-y-3">
              <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 ring-1 ring-emerald-500/20 group-hover:ring-emerald-500/40 transition-all">
                  <Sprout class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-medium text-muted-foreground">{{ loading ? '--' : sensorCounts.soil }}</span>
                  <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:translate-x-1 group-hover:text-emerald-500 transition-all" />
                </div>
              </div>
              <div class="space-y-1">
                <h3 class="text-base font-semibold">Soil Moisture Sensor</h3>
                <p class="text-xs text-muted-foreground leading-relaxed">
                  Senzor capacitiv pentru masurarea umiditatii solului. Rezistent la coroziune, recomandat pentru agricultura inteligenta, gradini urbane sau sisteme automate de irigatie cu control precis al resurselor.
                </p>
              </div>
              <div class="flex items-center gap-3 pt-1 text-xs">
                <div class="flex items-center gap-1 text-muted-foreground">
                  <Droplets class="h-3 w-3" />
                  <span>Moisture 0-100%</span>
                </div>
                <span class="text-muted-foreground/50">•</span>
                <span class="text-muted-foreground">Capacitive</span>
              </div>
            </div>
          </Link>

          <!-- ACS712 -->
          <Link 
            :href="dashboardRoutes.acs()"
            class="group relative overflow-hidden rounded-xl border border-sidebar-border/50 bg-gradient-to-br from-card to-card/50 backdrop-blur-sm p-5 transition-all hover:-translate-y-1 hover:shadow-xl hover:border-amber-500/50"
          >
            <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative space-y-3">
              <div class="flex items-start justify-between">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/10 ring-1 ring-amber-500/20 group-hover:ring-amber-500/40 transition-all">
                  <Zap class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-medium text-muted-foreground">{{ loading ? '--' : sensorCounts.acs }}</span>
                  <ArrowRight class="h-4 w-4 text-muted-foreground group-hover:translate-x-1 group-hover:text-amber-500 transition-all" />
                </div>
              </div>
              <div class="space-y-1">
                <h3 class="text-base font-semibold">ACS712 Current Sensor</h3>
                <p class="text-xs text-muted-foreground leading-relaxed">
                  Senzor de curent Hall-effect pentru masurarea consumului electric. Calcul RMS, putere activa, factor de putere si energie totala consumata. Essential pentru energy management si optimizarea costurilor.
                </p>
              </div>
              <div class="flex items-center gap-3 pt-1 text-xs">
                <div class="flex items-center gap-1 text-muted-foreground">
                  <Zap class="h-3 w-3" />
                  <span>±5A / ±20A / ±30A</span>
                </div>
                <span class="text-muted-foreground/50">•</span>
                <span class="text-muted-foreground">Hall-effect</span>
              </div>
            </div>
          </Link>
        </div>
      </section>

      <!-- Architecture Section - NEW -->
      <section class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="rounded-2xl border border-sidebar-border/50 bg-gradient-to-br from-muted/30 to-muted/10 backdrop-blur-sm p-8 space-y-6">
          <div class="text-center space-y-2">
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">Arhitectura tehnologica</h2>
            <p class="text-sm text-muted-foreground max-w-3xl mx-auto">
              Sistem distribuit modern cu separare clara intre frontend Laravel + Vue.js si backend Java pentru procesare avansata.
            </p>
          </div>

          <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-sidebar-border/50 bg-card/50 p-4 space-y-2">
              <div class="text-sm font-semibold">Frontend Stack</div>
              <div class="space-y-1 text-xs text-muted-foreground">
                <div>• Laravel 11 + Inertia.js</div>
                <div>• Vue 3 + TypeScript</div>
                <div>• Tailwind CSS + shadcn/ui</div>
                <div>• Chart.js pentru vizualizari</div>
              </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/50 bg-card/50 p-4 space-y-2">
              <div class="text-sm font-semibold">Backend Java</div>
              <div class="space-y-1 text-xs text-muted-foreground">
                <div>• Spring Boot microservices</div>
                <div>• Procesare distribuita</div>
                <div>• REST API endpoints</div>
                <div>• Load balancing</div>
              </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/50 bg-card/50 p-4 space-y-2">
              <div class="text-sm font-semibold">Database & Cache</div>
              <div class="space-y-1 text-xs text-muted-foreground">
                <div>• MySQL pentru persistenta</div>
                <div>• Redis pentru cache</div>
                <div>• Time-series optimization</div>
                <div>• Backup automatizat</div>
              </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/50 bg-card/50 p-4 space-y-2">
              <div class="text-sm font-semibold">IoT Protocol</div>
              <div class="space-y-1 text-xs text-muted-foreground">
                <div>• MQTT pentru senzori ESP32</div>
                <div>• WebSocket pentru live updates</div>
                <div>• RESTful API</div>
                <div>• JSON data format</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- CTA Section - Compact & Modern -->
      <section class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="relative overflow-hidden rounded-2xl border border-sidebar-border/50 bg-gradient-to-br from-blue-500/10 via-purple-500/10 to-pink-500/10 p-8 sm:p-12 text-center backdrop-blur-sm">
          <div class="absolute inset-0 bg-grid-white/5 [mask-image:radial-gradient(white,transparent_70%)]"></div>
          <div class="relative space-y-4">
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">Pregatit sa incepi?</h2>
            <p class="mx-auto max-w-2xl text-sm sm:text-base text-muted-foreground">
              Acceseaza dashboard-ul acum si incepe sa monitorizezi senzorii tai IoT in timp real. Configurare rapida, interfata intuitiva si suport complet pentru toate tipurile de device-uri conectate.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
              <Button v-if="$page.props.auth?.user" as-child size="lg" class="gap-2 shadow-lg">
                <Link :href="dashboard()">
                  <Activity class="h-4 w-4" />
                  Dashboard
                  <ArrowRight class="h-4 w-4" />
                </Link>
              </Button>
              <Button v-else as-child size="lg" class="gap-2 shadow-lg">
                <Link :href="login()">
                  <Activity class="h-4 w-4" />
                  Conecteaza-te
                  <ArrowRight class="h-4 w-4" />
                </Link>
              </Button>
              <Button variant="outline" as-child size="lg">
                <a href="#sensors">Exploreaza senzorii</a>
              </Button>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer - Compact -->
    <footer class="border-t border-sidebar-border/50 bg-muted/20 backdrop-blur-sm">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
          <div class="flex items-center gap-3">
            <AppLogo />
            <div class="h-4 w-px bg-sidebar-border/50"></div>
            <span class="text-xs text-muted-foreground">IoT Sensor Hub © 2026</span>
          </div>
          <div class="flex flex-wrap items-center gap-1.5">
            <Button variant="ghost" size="sm" as-child class="h-8 text-xs">
              <Link :href="dashboardRoutes.dht11()">DHT11</Link>
            </Button>
            <Button variant="ghost" size="sm" as-child class="h-8 text-xs">
              <Link :href="dashboardRoutes.soil()">Soil</Link>
            </Button>
            <Button variant="ghost" size="sm" as-child class="h-8 text-xs">
              <Link :href="dashboardRoutes.acs()">ACS712</Link>
            </Button>
            <div class="h-4 w-px bg-sidebar-border/50 mx-1"></div>
            <Button variant="ghost" size="sm" as-child class="h-8 text-xs">
              <Link href="/dashboard/trends">Trends</Link>
            </Button>
            <Button variant="ghost" size="sm" as-child class="h-8 text-xs">
              <Link href="/dashboard/anomalies">Anomalii</Link>
            </Button>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

type SearchResult = {
    kind: 'sensor' | 'page' | string;
    title: string;
    subtitle?: string;
    href: string;
};

const wrapperRef = ref<HTMLElement | null>(null);
const query = ref('');
const results = ref<SearchResult[]>([]);
const open = ref(false);
const loading = ref(false);

let debounceTimer: number | null = null;

const fetchResults = async () => {
    const q = query.value.trim();
    if (q.length < 2) {
        results.value = [];
        loading.value = false;
        return;
    }

    loading.value = true;
    try {
        const res = await fetch(`/search?q=${encodeURIComponent(q)}`, {
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        results.value = (json?.results ?? []) as SearchResult[];
        open.value = true;
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
};

watch(query, () => {
    if (debounceTimer) {
        window.clearTimeout(debounceTimer);
    }

    debounceTimer = window.setTimeout(() => {
        fetchResults();
    }, 200);
});

const onDocumentMouseDown = (e: MouseEvent) => {
    const el = wrapperRef.value;
    if (!el) return;
    if (!el.contains(e.target as Node)) {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', onDocumentMouseDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentMouseDown);
});
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div ref="wrapperRef" class="relative ml-auto hidden w-[360px] items-center md:flex">
            <Search class="pointer-events-none absolute left-3 h-4 w-4 text-muted-foreground" />
            <Input
                v-model="query"
                class="pl-9"
                placeholder="Search…"
                @focus="open = query.trim().length >= 2"
                @keydown.escape.prevent="open = false"
            />

            <div
                v-if="open && (loading || results.length > 0 || query.trim().length >= 2)"
                class="absolute top-full z-50 mt-2 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <div v-if="loading" class="px-3 py-2 text-sm text-muted-foreground">
                    Searching…
                </div>

                <div v-else-if="results.length === 0" class="px-3 py-2 text-sm text-muted-foreground">
                    No results.
                </div>

                <div v-else class="max-h-80 overflow-y-auto py-1">
                    <Link
                        v-for="(r, idx) in results"
                        :key="idx"
                        :href="r.href"
                        class="block px-3 py-2 text-sm hover:bg-accent"
                        @click="open = false"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ r.title }}</div>
                                <div v-if="r.subtitle" class="truncate text-xs text-muted-foreground">
                                    {{ r.subtitle }}
                                </div>
                            </div>
                            <div class="shrink-0 rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                {{ r.kind }}
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </header>
</template>

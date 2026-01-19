<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, Thermometer, Droplet, Zap, TrendingUp, Activity, Download, Cpu, Network } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Sensors',
        href: '#',
        icon: Cpu,
        items: [
            {
                title: 'DHT11',
                href: dashboardRoutes.dht11(),
                icon: Thermometer,
            },
            {
                title: 'Soil',
                href: dashboardRoutes.soil(),
                icon: Droplet,
            },
            {
                title: 'ACS712',
                href: dashboardRoutes.acs(),
                icon: Zap,
            },
        ],
    },
    {
        title: 'Trends',
        href: '/dashboard/trends',
        icon: TrendingUp,
    },
    {
        title: 'Anomalies',
        href: '/dashboard/anomalies',
        icon: Activity,
    },
    {
        title: 'Export',
        href: '/dashboard/export',
        icon: Download,
    },
    {
        title: 'Distributed Insights',
        href: '/dashboard/distributed-insights',
        icon: Network,
    },
];

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

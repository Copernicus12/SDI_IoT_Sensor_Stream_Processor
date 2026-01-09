<script setup lang="ts">
import AppSettingsController from '@/actions/App/Http/Controllers/Settings/AppSettingsController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/app-settings';
import { type BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/vue3';

type Currency = 'RON' | 'EUR' | 'USD';

interface Props {
    settings: {
        energy: {
            price_per_kwh: number;
            currency: Currency;
            mains_voltage_v: number;
            power_factor: number;
        };
        distributed: {
            window_minutes: number;
            z_warn: number;
            z_critical: number;
            staleness_threshold_s: number;
        };
    };
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'App settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="App settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Energy pricing"
                    description="Used for ACS power cost calculations"
                />

                <Form
                    v-bind="AppSettingsController.update.form()"
                    class="space-y-8"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-6">
                        <div class="grid gap-2">
                            <Label for="energy_price_per_kwh">Price per kWh</Label>
                            <Input
                                id="energy_price_per_kwh"
                                name="energy[price_per_kwh]"
                                type="number"
                                step="0.0001"
                                min="0"
                                :default-value="settings.energy.price_per_kwh"
                                required
                            />
                            <InputError class="mt-2" :message="errors['energy.price_per_kwh']" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="energy_currency">Currency</Label>
                            <select
                                id="energy_currency"
                                name="energy[currency]"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                :defaultValue="settings.energy.currency"
                                required
                            >
                                <option value="RON">RON</option>
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                            </select>
                            <InputError class="mt-2" :message="errors['energy.currency']" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="energy_mains_voltage">Mains voltage (V)</Label>
                            <Input
                                id="energy_mains_voltage"
                                name="energy[mains_voltage_v]"
                                type="number"
                                step="0.1"
                                min="50"
                                max="400"
                                :default-value="settings.energy.mains_voltage_v"
                                required
                            />
                            <InputError class="mt-2" :message="errors['energy.mains_voltage_v']" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="energy_power_factor">Power factor</Label>
                            <Input
                                id="energy_power_factor"
                                name="energy[power_factor]"
                                type="number"
                                step="0.01"
                                min="0.1"
                                max="1"
                                :default-value="settings.energy.power_factor"
                                required
                            />
                            <InputError class="mt-2" :message="errors['energy.power_factor']" />
                        </div>
                    </div>

                    <div class="pt-2">
                        <HeadingSmall
                            title="Distributed insights"
                            description="Controls the analysis window and alert thresholds"
                        />
                    </div>

                    <div class="grid gap-6">
                        <div class="grid gap-2">
                            <Label for="dist_window_minutes">Window (minutes)</Label>
                            <Input
                                id="dist_window_minutes"
                                name="distributed[window_minutes]"
                                type="number"
                                step="1"
                                min="10"
                                max="360"
                                :default-value="settings.distributed.window_minutes"
                                required
                            />
                            <InputError class="mt-2" :message="errors['distributed.window_minutes']" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="dist_z_warn">Z-score warning threshold</Label>
                            <Input
                                id="dist_z_warn"
                                name="distributed[z_warn]"
                                type="number"
                                step="0.1"
                                min="0.5"
                                max="10"
                                :default-value="settings.distributed.z_warn"
                                required
                            />
                            <InputError class="mt-2" :message="errors['distributed.z_warn']" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="dist_z_critical">Z-score critical threshold</Label>
                            <Input
                                id="dist_z_critical"
                                name="distributed[z_critical]"
                                type="number"
                                step="0.1"
                                min="0.5"
                                max="10"
                                :default-value="settings.distributed.z_critical"
                                required
                            />
                            <InputError class="mt-2" :message="errors['distributed.z_critical']" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="dist_stale">Staleness threshold (s)</Label>
                            <Input
                                id="dist_stale"
                                name="distributed[staleness_threshold_s]"
                                type="number"
                                step="1"
                                min="10"
                                max="3600"
                                :default-value="settings.distributed.staleness_threshold_s"
                                required
                            />
                            <InputError class="mt-2" :message="errors['distributed.staleness_threshold_s']" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="processing">Save</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="recentlySuccessful" class="text-sm text-neutral-600">
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

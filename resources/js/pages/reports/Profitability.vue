<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

interface Totals {
    appointments: number;
    revenue: number;
    supply_cost: number;
    drug_cost: number;
    mileage: number;
    mileage_cost: number;
    profit: number;
}

interface Appointment {
    booking_id: number;
    client_name: string | null;
    service_name: string | null;
    service_type: string | null;
    revenue: number;
    supply_cost: number;
    drug_cost: number;
    mileage: number;
    mileage_cost: number;
    cogs: number;
    profit: number;
    margin: number;
    date: string | null;
}

interface ServiceRow {
    service_name: string | null;
    service_type: string | null;
    appointments: number;
    revenue: number;
    profit: number;
    margin: number;
}

interface ClientRow {
    team_id: number;
    client_name: string | null;
    appointments: number;
    revenue: number;
    profit: number;
    margin: number;
}

interface MileageRow {
    period: string;
    miles: number;
    deduction: number;
    visits: number;
}

interface Props {
    isStaff: boolean;
    filters: { from: string | null; to: string | null; period: 'month' | 'year' };
    totals: Totals;
    appointments: Appointment[];
    byServiceType: ServiceRow[];
    byClient: ClientRow[];
    mileageByPeriod: MileageRow[];
    mileageRate: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Profit & mileage', href: '/reports/profitability' }];

const usd = (n: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(n ?? 0);
const pct = (n: number) => `${Math.round((n ?? 0) * 100)}%`;

const setPeriod = (period: 'month' | 'year') =>
    router.get('/reports/profitability', { ...props.filters, period }, { preserveScroll: true, preserveState: true });
</script>

<template>
    <Head title="Profit & mileage" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <p class="text-sm text-muted-foreground">
                {{ isStaff ? 'Every client — what each service and trip actually earns.' : 'Your appointments — what they cost and earned.' }}
            </p>

            <!-- Headline numbers — the "few clear numbers" (§5.6c). -->
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="text-xs text-muted-foreground">Revenue</div>
                    <div class="text-xl font-semibold">{{ usd(totals.revenue) }}</div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="text-xs text-muted-foreground">Costs (supplies + drugs + miles)</div>
                    <div class="text-xl font-semibold">{{ usd(totals.supply_cost + totals.drug_cost + totals.mileage_cost) }}</div>
                </div>
                <div
                    class="rounded-xl border p-4"
                    :class="
                        totals.profit >= 0
                            ? 'border-green-300 bg-green-50 dark:border-green-900 dark:bg-green-950/40'
                            : 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/40'
                    "
                >
                    <div class="text-xs text-muted-foreground">Profit</div>
                    <div class="text-xl font-semibold">{{ usd(totals.profit) }}</div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="text-xs text-muted-foreground">Miles driven</div>
                    <div class="text-xl font-semibold">{{ totals.mileage }}</div>
                </div>
            </section>

            <!-- Which services are worth doing (§5.6c). -->
            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 font-semibold">Which services are worth doing</h2>
                <div v-if="byServiceType.length" class="flex flex-col divide-y divide-sidebar-border/70">
                    <div
                        v-for="row in byServiceType"
                        :key="(row.service_name ?? '') + row.service_type"
                        class="flex items-center justify-between py-2"
                    >
                        <div>
                            <div class="font-medium">{{ row.service_name ?? 'Uncategorised' }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ row.appointments }} appt · {{ usd(row.revenue) }} revenue · {{ pct(row.margin) }} margin
                            </div>
                        </div>
                        <div
                            class="text-right font-semibold"
                            :class="row.profit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ usd(row.profit) }}
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No completed appointments yet.</p>
            </section>

            <!-- Per client (staff only). -->
            <section v-if="isStaff" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 font-semibold">Profit per client</h2>
                <div v-if="byClient.length" class="flex flex-col divide-y divide-sidebar-border/70">
                    <div v-for="row in byClient" :key="row.team_id" class="flex items-center justify-between py-2">
                        <div>
                            <div class="font-medium">{{ row.client_name ?? 'Client #' + row.team_id }}</div>
                            <div class="text-xs text-muted-foreground">{{ row.appointments }} appt · {{ usd(row.revenue) }} revenue</div>
                        </div>
                        <div
                            class="text-right font-semibold"
                            :class="row.profit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ usd(row.profit) }}
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No client activity yet.</p>
            </section>

            <!-- Mileage totals by period, for the tax deduction (§5.6c). -->
            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">Mileage for tax</h2>
                    <div class="flex gap-1 text-xs">
                        <button
                            class="rounded px-2 py-1"
                            :class="filters.period === 'month' ? 'bg-primary text-primary-foreground' : 'bg-muted'"
                            @click="setPeriod('month')"
                        >
                            Monthly
                        </button>
                        <button
                            class="rounded px-2 py-1"
                            :class="filters.period === 'year' ? 'bg-primary text-primary-foreground' : 'bg-muted'"
                            @click="setPeriod('year')"
                        >
                            Yearly
                        </button>
                    </div>
                </div>
                <p class="mb-2 text-xs text-muted-foreground">Deduction estimated at {{ usd(mileageRate) }}/mile.</p>
                <div v-if="mileageByPeriod.length" class="flex flex-col divide-y divide-sidebar-border/70">
                    <div v-for="row in mileageByPeriod" :key="row.period" class="flex items-center justify-between py-2">
                        <div>
                            <div class="font-medium">{{ row.period }}</div>
                            <div class="text-xs text-muted-foreground">{{ row.visits }} visits · {{ row.miles }} mi</div>
                        </div>
                        <div class="text-right font-semibold">{{ usd(row.deduction) }}</div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No mileage logged yet.</p>
            </section>

            <!-- Per-appointment detail. -->
            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 font-semibold">Appointments</h2>
                <div v-if="appointments.length" class="flex flex-col divide-y divide-sidebar-border/70">
                    <div v-for="a in appointments" :key="a.booking_id" class="py-3">
                        <div class="flex items-center justify-between">
                            <div class="font-medium">
                                {{ a.service_name ?? 'Appointment'
                                }}<span v-if="isStaff && a.client_name" class="text-muted-foreground"> · {{ a.client_name }}</span>
                            </div>
                            <div
                                class="font-semibold"
                                :class="a.profit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                                {{ usd(a.profit) }}
                            </div>
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            {{ a.date ?? '—' }} · rev {{ usd(a.revenue) }} · supplies {{ usd(a.supply_cost) }} · drugs {{ usd(a.drug_cost) }} ·
                            {{ a.mileage }}mi {{ usd(a.mileage_cost) }}
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No completed appointments yet.</p>
            </section>

            <div class="text-xs text-muted-foreground">
                <Link href="/reports/breeding" class="underline">AI success-rate reporting →</Link>
            </div>
        </div>
    </AppLayout>
</template>

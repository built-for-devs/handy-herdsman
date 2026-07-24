<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Rate {
    settled: number;
    evaluated: number;
    recheck: number;
    total: number;
    conception_rate: number | null;
}

interface GroupRow extends Rate {
    group: string;
}

interface BcsRow extends Rate {
    bucket: string;
    label: string;
    order: number;
}

interface TrendRow {
    cattle_id: number;
    name: string;
    points: Array<{ date: string | null; bcs: number }>;
}

interface ClientRow extends Rate {
    team_id: number;
    client_name: string | null;
    breedings: number;
    last_bred_at: string | null;
    dormant_days: number | null;
}

interface Props {
    isStaff: boolean;
    overall: Rate;
    bcsVsConception: BcsRow[];
    bySire: GroupRow[];
    byBreed: GroupRow[];
    byAnimalType: GroupRow[];
    byProtocolType: GroupRow[];
    bySeason: GroupRow[];
    byClient: GroupRow[];
    bcsTrend: TrendRow[];
    clientValue: ClientRow[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'AI success rate', href: '/reports/breeding' }];

const rate = (r: number | null) => (r === null ? '—' : `${Math.round(r * 100)}%`);

// The headline comparison the whole report exists for (§5.6c).
const headline = computed(() => {
    const high = props.bcsVsConception.find((b) => b.bucket === '7plus');
    const target = props.bcsVsConception.find((b) => b.bucket === '5-6');
    if (high?.conception_rate == null || target?.conception_rate == null) return null;
    return { high: high.conception_rate, target: target.conception_rate };
});
</script>

<template>
    <Head title="AI success rate" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Overall. -->
            <section class="grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="text-xs text-muted-foreground">Conception rate</div>
                    <div class="text-2xl font-semibold">{{ rate(overall.conception_rate) }}</div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="text-xs text-muted-foreground">Settled</div>
                    <div class="text-2xl font-semibold">{{ overall.settled }}/{{ overall.evaluated }}</div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="text-xs text-muted-foreground">Rechecks</div>
                    <div class="text-2xl font-semibold">{{ overall.recheck }}</div>
                </div>
            </section>

            <!-- BCS vs conception — the headline (§5.6c). -->
            <section class="rounded-xl border-2 border-primary/40 p-4">
                <h2 class="font-semibold">BCS vs. conception — the headline</h2>
                <p v-if="headline" class="mt-1 text-sm text-muted-foreground">
                    Cows at BCS 7+ settled <span class="font-semibold text-foreground">{{ rate(headline.high) }}</span> vs.
                    <span class="font-semibold text-foreground">{{ rate(headline.target) }}</span> at 5–6.
                </p>
                <div v-if="bcsVsConception.length" class="mt-3 flex flex-col gap-3">
                    <div v-for="b in bcsVsConception" :key="b.bucket">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ b.label }}</span>
                            <span class="text-muted-foreground">{{ rate(b.conception_rate) }} ({{ b.settled }}/{{ b.evaluated }})</span>
                        </div>
                        <div class="mt-1 h-2 w-full rounded bg-muted">
                            <div class="h-2 rounded bg-primary" :style="{ width: `${Math.round((b.conception_rate ?? 0) * 100)}%` }"></div>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-2 text-sm text-muted-foreground">No BCS recorded on breedings yet.</p>
            </section>

            <!-- Conception rate by dimension. -->
            <section class="grid gap-4 sm:grid-cols-2">
                <div
                    v-for="dim in [
                        { title: 'By sire', rows: bySire },
                        { title: 'By breed', rows: byBreed },
                        { title: 'Cow vs. heifer', rows: byAnimalType },
                        { title: 'Sync vs. natural', rows: byProtocolType },
                        { title: 'By season', rows: bySeason },
                        ...(isStaff ? [{ title: 'By client', rows: byClient }] : []),
                    ]"
                    :key="dim.title"
                    class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <h3 class="mb-2 font-semibold">{{ dim.title }}</h3>
                    <div v-if="dim.rows.length" class="flex flex-col divide-y divide-sidebar-border/70">
                        <div v-for="row in dim.rows" :key="row.group" class="flex items-center justify-between py-1.5 text-sm">
                            <span class="capitalize">{{ row.group }}</span>
                            <span class="text-muted-foreground"
                                >{{ rate(row.conception_rate) }} <span class="text-xs">({{ row.settled }}/{{ row.evaluated }})</span></span
                            >
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">No data yet.</p>
                </div>
            </section>

            <!-- Client value over time (staff). -->
            <section v-if="isStaff" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 font-semibold">Client value over time</h2>
                <div v-if="clientValue.length" class="flex flex-col divide-y divide-sidebar-border/70">
                    <div v-for="row in clientValue" :key="row.team_id" class="flex items-center justify-between py-2 text-sm">
                        <div>
                            <div class="font-medium">{{ row.client_name ?? 'Client #' + row.team_id }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ row.breedings }} breedings · {{ rate(row.conception_rate) }} settle
                                <span v-if="row.dormant_days !== null"> · {{ row.dormant_days }}d since last</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No client breeding history yet.</p>
            </section>

            <!-- BCS trend per animal. -->
            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-3 font-semibold">BCS trend per animal</h2>
                <div v-if="bcsTrend.length" class="flex flex-col divide-y divide-sidebar-border/70">
                    <div v-for="animal in bcsTrend" :key="animal.cattle_id" class="flex items-center justify-between py-2 text-sm">
                        <span class="font-medium">{{ animal.name }}</span>
                        <span class="font-mono text-muted-foreground">{{ animal.points.map((p) => p.bcs).join(' → ') }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No body-condition scores recorded yet.</p>
            </section>

            <div class="text-xs text-muted-foreground">
                <Link href="/reports/profitability" class="underline">← Cost & profitability</Link>
            </div>
        </div>
    </AppLayout>
</template>

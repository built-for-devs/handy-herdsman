<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Stop {
    visit_id: number;
    type: string;
    status: string;
    scheduled_at: string | null;
    team_name: string | null;
    client_name: string | null;
    address: string | null;
    lat: number | null;
    lng: number | null;
    distance_miles: number | null;
    in_range: boolean;
    fee_applies: boolean;
    fee_amount: number;
    declined: boolean;
    out_of_range: boolean;
}

const props = defineProps<{
    date: string;
    stops: Stop[];
    origin: { lat: number | null; lng: number | null };
    outOfRangeCount: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dispatch', href: '/admin/dispatch' }];

const goToDate = (date: string) => router.get(route('admin.dispatch.index'), { date }, { preserveScroll: true, preserveState: true });
const shiftDay = (days: number) => {
    const d = new Date(`${props.date}T00:00:00`);
    d.setDate(d.getDate() + days);
    goToDate(d.toISOString().slice(0, 10));
};

const formatTime = (iso: string | null): string =>
    iso ? new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }) : 'Unscheduled';

const directionsUrl = (stop: Stop): string => {
    if (stop.lat !== null && stop.lng !== null) {
        return `https://www.google.com/maps/dir/?api=1&destination=${stop.lat},${stop.lng}`;
    }
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(stop.address ?? '')}`;
};

// A single route map through every geocoded stop for the day (§5.10 — map +
// ordered day list). Origin is the farm; stops stay in scheduled-time order.
const routeMapUrl = computed<string | null>(() => {
    const points = props.stops.filter((s) => s.lat !== null && s.lng !== null).map((s) => `${s.lat},${s.lng}`);
    if (points.length === 0) {
        return null;
    }
    const origin = props.origin.lat !== null && props.origin.lng !== null ? `${props.origin.lat},${props.origin.lng}` : points[0];
    const destination = points[points.length - 1];
    const waypoints = points.slice(0, -1).join('|');
    const base = `https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${destination}`;
    return waypoints ? `${base}&waypoints=${encodeURIComponent(waypoints)}` : base;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Dispatch" />

        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <HeadingSmall title="Dispatch" description="Your scheduled visits for the day, in order, with distance flags." />

            <div class="flex items-center gap-2">
                <Button type="button" variant="outline" class="min-h-11" @click="shiftDay(-1)">Prev</Button>
                <Input type="date" class="min-h-11" :model-value="date" @update:model-value="(v) => goToDate(String(v))" />
                <Button type="button" variant="outline" class="min-h-11" @click="shiftDay(1)">Next</Button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a v-if="routeMapUrl" :href="routeMapUrl" target="_blank" rel="noopener" class="flex-1">
                    <Button type="button" class="min-h-11 w-full">Open route map</Button>
                </a>
                <span v-if="outOfRangeCount > 0" class="rounded-full bg-red-100 px-3 py-1 text-xs text-red-800">
                    {{ outOfRangeCount }} out of range
                </span>
            </div>

            <p v-if="stops.length === 0" class="text-sm text-muted-foreground">No scheduled visits for this day.</p>

            <ol v-else class="space-y-3">
                <li v-for="(stop, i) in stops" :key="stop.visit_id" class="rounded-md border p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                <span class="mr-1 text-muted-foreground">{{ i + 1 }}.</span>
                                {{ formatTime(stop.scheduled_at) }} · {{ stop.client_name || stop.team_name || 'Client' }}
                            </p>
                            <p class="text-xs uppercase text-muted-foreground">{{ stop.type }}</p>
                            <p v-if="stop.address" class="mt-1 truncate text-xs text-muted-foreground">{{ stop.address }}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span v-if="stop.distance_miles !== null" class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                    {{ stop.distance_miles.toFixed(1) }} mi
                                </span>
                                <span v-if="stop.declined" class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">Declined area</span>
                                <span v-else-if="stop.out_of_range" class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">
                                    Out of range
                                </span>
                                <span v-if="stop.fee_applies" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
                                    +${{ stop.fee_amount }} distance fee
                                </span>
                            </div>
                        </div>
                        <a :href="directionsUrl(stop)" target="_blank" rel="noopener" class="shrink-0">
                            <Button type="button" size="sm" variant="outline" class="min-h-11">Directions</Button>
                        </a>
                    </div>
                </li>
            </ol>
        </div>
    </AppLayout>
</template>

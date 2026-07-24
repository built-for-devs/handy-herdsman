<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Visit {
    id: number;
    type: string;
    status: string;
    scheduled_at: string | null;
    billed_amount: string | null;
}

interface BookingRow {
    id: number;
    team: string | null;
    service: string | null;
    type: string | null;
    status: string;
    status_label: string;
    requires_review: boolean;
    review_reason: string | null;
    is_oncall: boolean;
    distance_fee_flag: boolean;
    proposed_start: string | null;
    visits: Visit[];
}

defineProps<{ reviewQueue: BookingRow[]; all: BookingRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Bookings', href: '/admin/bookings' }];

const blackout = useForm({ start_date: '', end_date: '', reason: '' });

const confirm = (id: number) => router.post(route('admin.bookings.confirm', id), {}, { preserveScroll: true });
const decline = (id: number) => {
    const reason = window.prompt('Reason for declining (optional)') ?? '';
    router.post(route('admin.bookings.decline', id), { decline_reason: reason }, { preserveScroll: true });
};
const failVisit = (id: number) => router.post(route('admin.visits.fail', id), {}, { preserveScroll: true });

const formatDate = (iso: string | null): string =>
    iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : 'To schedule';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Bookings" />

        <div class="mx-auto w-full max-w-3xl space-y-8 p-4">
            <section class="space-y-3">
                <HeadingSmall title="Review queue" :description="`${reviewQueue.length} booking(s) awaiting your decision (24h SLA).`" />
                <p v-if="reviewQueue.length === 0" class="text-sm text-muted-foreground">Nothing to review.</p>
                <ul v-else class="divide-y rounded-md border">
                    <li v-for="b in reviewQueue" :key="b.id" class="space-y-2 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium">
                                    {{ b.service }} — {{ b.team }}
                                    <span v-if="b.is_oncall" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">On-call</span>
                                </p>
                                <p class="text-xs text-muted-foreground">{{ formatDate(b.proposed_start) }}</p>
                                <p v-if="b.review_reason" class="mt-1 text-xs text-amber-700">{{ b.review_reason }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <Button size="sm" @click="confirm(b.id)">Confirm</Button>
                                <Button size="sm" variant="outline" @click="decline(b.id)">Decline</Button>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="space-y-3">
                <HeadingSmall title="Add a blackout" description="Days you're unavailable. Existing bookings on those days are flagged." />
                <form
                    class="grid gap-3 sm:grid-cols-3"
                    @submit.prevent="blackout.post(route('admin.blackouts.store'), { preserveScroll: true, onSuccess: () => blackout.reset() })"
                >
                    <div class="grid gap-1">
                        <Label for="start">Start</Label>
                        <Input id="start" type="date" v-model="blackout.start_date" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="end">End</Label>
                        <Input id="end" type="date" v-model="blackout.end_date" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="reason">Reason</Label>
                        <Input id="reason" v-model="blackout.reason" placeholder="Travel" />
                    </div>
                    <Button type="submit" size="sm" class="sm:col-span-3" :disabled="blackout.processing">Save blackout</Button>
                </form>
            </section>

            <section class="space-y-3">
                <HeadingSmall title="All bookings" description="Every booking across clients." />
                <ul class="divide-y rounded-md border">
                    <li v-for="b in all" :key="b.id" class="space-y-2 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium">{{ b.service }} — {{ b.team }}</p>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs capitalize"
                                :class="b.status === 'confirmed' ? 'bg-green-100 text-green-800' : 'bg-muted text-muted-foreground'"
                                >{{ b.status }}</span
                            >
                        </div>
                        <ul v-if="b.visits.length" class="space-y-1">
                            <li v-for="v in b.visits" :key="v.id" class="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                <span class="uppercase"
                                    >{{ v.type }} · {{ formatDate(v.scheduled_at) }} · {{ v.status
                                    }}<span v-if="v.billed_amount"> · ${{ v.billed_amount }}</span></span
                                >
                                <Button v-if="v.status === 'scheduled'" size="sm" variant="outline" @click="failVisit(v.id)">Mark failed</Button>
                            </li>
                        </ul>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>

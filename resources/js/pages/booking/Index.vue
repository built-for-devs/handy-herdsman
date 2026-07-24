<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface BookingSummary {
    id: number;
    service: string | null;
    type: string | null;
    status: string;
    status_label: string;
    requires_review: boolean;
    is_oncall: boolean;
    distance_fee_flag: boolean;
    proposed_start: string | null;
    visit_count: number;
}

defineProps<{ bookings: BookingSummary[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Bookings', href: '/bookings' }];

const statusClass = (status: string): string => {
    switch (status) {
        case 'confirmed':
            return 'bg-green-100 text-green-800';
        case 'provisional':
            return 'bg-amber-100 text-amber-800';
        case 'declined':
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-muted text-muted-foreground';
    }
};

const formatDate = (iso: string | null): string =>
    iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : 'To be scheduled';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Bookings" />

        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <div class="flex items-center justify-between gap-4">
                <HeadingSmall title="Your bookings" description="Protocol visits, on-call requests and farm calls." />
                <Link :href="route('bookings.create')">
                    <Button size="sm">Book a visit</Button>
                </Link>
            </div>

            <p v-if="bookings.length === 0" class="text-sm text-muted-foreground">No bookings yet. Book your first visit.</p>

            <ul v-else class="divide-y rounded-md border">
                <li v-for="booking in bookings" :key="booking.id" class="flex items-center justify-between gap-4 p-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ booking.service || 'Booking' }}
                            <span v-if="booking.is_oncall" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">On-call</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDate(booking.proposed_start) }}
                            <span v-if="booking.visit_count > 1"> · {{ booking.visit_count }} visits</span>
                            <span v-if="booking.distance_fee_flag"> · distance fee applies</span>
                        </p>
                        <p v-if="booking.requires_review" class="mt-1 text-xs text-amber-700">Provisional — Jeff will review within 24 hours.</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs capitalize" :class="statusClass(booking.status)">
                        {{ booking.status }}
                    </span>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>

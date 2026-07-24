<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Link } from '@inertiajs/vue3';

interface PriceRow {
    name: string;
    category: string;
    price: string;
}

interface FeeRow {
    key: string;
    label: string | null;
    display: string;
}

defineProps<{
    services: PriceRow[];
    fees: FeeRow[];
}>();
</script>

<template>
    <Seo
        title="Pricing — Handy Herdsman"
        description="Transparent, up-to-date pricing for cattle AI, calving, ranch-hand and health services. Pulled live from our rate config so it's never stale."
    />
    <PublicLayout>
        <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Pricing</h1>
            <p class="mt-4 text-muted-foreground">
                All prices are pulled live from our rate config — what you see is current. Straw costs are not our prices; see the
                <Link :href="route('faq')" class="text-primary hover:underline">FAQ</Link> for reference ranges.
            </p>

            <div class="mt-10">
                <h2 class="text-lg font-semibold">Fees &amp; storage</h2>
                <div class="mt-3 divide-y divide-border rounded-lg border border-border">
                    <div v-for="fee in fees" :key="fee.key" class="flex items-center justify-between p-4">
                        <span class="text-sm">{{ fee.label }}</span>
                        <span class="text-sm font-semibold">{{ fee.display }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-10">
                <h2 class="text-lg font-semibold">All services</h2>
                <div class="mt-3 divide-y divide-border rounded-lg border border-border">
                    <div v-for="row in services" :key="row.name" class="flex items-center justify-between gap-4 p-4">
                        <span class="text-sm">{{ row.name }}</span>
                        <span class="shrink-0 text-sm font-semibold">{{ row.price }}</span>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

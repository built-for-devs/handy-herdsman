<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface ServiceItem {
    name: string;
    slug: string;
    description: string | null;
    type: string;
    price: string;
}

interface ServiceGroup {
    category: string;
    label: string;
    services: ServiceItem[];
}

interface NotOffered {
    title: string;
    note: string;
}

defineProps<{
    groups: ServiceGroup[];
    notOffered: NotOffered[];
}>();

const typeLabels: Record<string, string> = {
    protocol: 'Protocol',
    oncall: 'On-call',
    standard: 'Standard',
};
</script>

<template>
    <Seo
        title="Cattle & Ranch Services — Handy Herdsman"
        description="Breeding, calving, ranch-hand and non-vet health services for Central Texas cattle owners. Every service and price is pulled live from our catalog."
    />
    <PublicLayout>
        <section class="mx-auto max-w-5xl px-4 py-16 sm:px-6">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Services</h1>
            <p class="mt-4 text-muted-foreground">Everything below is bookable. Prices are pulled live from our catalog — never stale.</p>

            <div v-for="group in groups" :key="group.category" class="mt-12">
                <h2 class="text-xl font-semibold tracking-tight">{{ group.label }}</h2>
                <div class="mt-4 divide-y divide-border rounded-lg border border-border">
                    <div
                        v-for="service in group.services"
                        :key="service.slug"
                        class="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-medium">{{ service.name }}</h3>
                                <span class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                    {{ typeLabels[service.type] ?? service.type }}
                                </span>
                            </div>
                            <p v-if="service.description" class="mt-1 text-sm text-muted-foreground">
                                {{ service.description }}
                            </p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold sm:text-right">{{ service.price }}</p>
                    </div>
                </div>
            </div>

            <!-- Trust signals: what we explicitly do NOT offer (§2, §5.2). -->
            <div class="mt-12 rounded-lg border border-dashed border-border bg-muted/30 p-6">
                <h2 class="text-lg font-semibold">What we don't do</h2>
                <p class="mt-2 text-sm text-muted-foreground">We're upfront about our limits — it's part of doing this honestly.</p>
                <ul class="mt-4 space-y-3">
                    <li v-for="item in notOffered" :key="item.title" class="text-sm">
                        <span class="font-medium">{{ item.title }}:</span>
                        <span class="text-muted-foreground"> {{ item.note }}</span>
                    </li>
                </ul>
            </div>
        </section>
    </PublicLayout>
</template>

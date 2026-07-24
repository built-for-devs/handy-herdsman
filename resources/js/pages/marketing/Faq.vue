<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface FaqItem {
    question: string;
    answer: string;
}

interface FaqGroup {
    category: string;
    items: FaqItem[];
}

interface Range {
    min: number;
    max: number;
}

defineProps<{
    faqs: FaqGroup[];
    strawCostReference: Record<string, Range> | null;
    strawCostDisclaimer: string;
}>();

const categoryLabels: Record<string, string> = {
    general: 'General',
    breeding: 'Breeding & AI',
    billing: 'Billing',
};

const strawLabels: Record<string, string> = {
    standard: 'Standard semen',
    premium: 'Premium / rare breeds',
    shipping: 'Shipping',
};

function money(n: number): string {
    return `$${n.toLocaleString()}`;
}
</script>

<template>
    <Seo
        title="FAQ — Handy Herdsman"
        description="Answers to common questions about cattle AI, breeding plans, billing and straw-cost reference ranges."
    />
    <PublicLayout>
        <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Frequently asked questions</h1>

            <div v-for="group in faqs" :key="group.category" class="mt-10">
                <h2 class="text-lg font-semibold">{{ categoryLabels[group.category] ?? group.category }}</h2>
                <dl class="mt-4 space-y-6">
                    <div v-for="item in group.items" :key="item.question">
                        <dt class="font-medium">{{ item.question }}</dt>
                        <dd class="mt-1 text-sm text-muted-foreground">{{ item.answer }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Straw-cost REFERENCE ranges — explicitly NOT our prices (§2, §5.2b). -->
            <div
                v-if="strawCostReference"
                class="mt-12 rounded-lg border border-amber-300/60 bg-amber-50 p-6 dark:border-amber-500/30 dark:bg-amber-950/20"
            >
                <h2 class="text-lg font-semibold">Straw-cost reference ranges</h2>
                <p class="mt-2 text-sm font-medium text-amber-800 dark:text-amber-300">
                    {{ strawCostDisclaimer }}
                </p>
                <ul class="mt-4 space-y-2">
                    <li v-for="(range, key) in strawCostReference" :key="key" class="flex items-center justify-between text-sm">
                        <span>{{ strawLabels[key] ?? key }}</span>
                        <span class="font-semibold">{{ money(range.min) }}–{{ money(range.max) }}</span>
                    </li>
                </ul>
            </div>
        </section>
    </PublicLayout>
</template>

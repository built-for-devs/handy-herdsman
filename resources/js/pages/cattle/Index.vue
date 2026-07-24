<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface CattleSummary {
    id: number;
    reg_name: string | null;
    herd_number: string | null;
    breed: string | null;
    animal_type: string;
    status: string;
    for_sale: boolean;
}

defineProps<{ cattle: CattleSummary[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Herd', href: '/cattle' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Herd" />

        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <div class="flex items-center justify-between gap-4">
                <HeadingSmall title="Your herd" description="Profiles, health records and photos for each animal." />
                <Link :href="route('cattle.create')">
                    <Button size="sm">Add animal</Button>
                </Link>
            </div>

            <p v-if="cattle.length === 0" class="text-sm text-muted-foreground">No animals yet. Add your first one.</p>

            <ul v-else class="divide-y rounded-md border">
                <li v-for="animal in cattle" :key="animal.id">
                    <Link :href="route('cattle.show', animal.id)" class="flex items-center justify-between gap-4 p-3 hover:bg-muted/50">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ animal.reg_name || 'Unnamed' }}</p>
                            <p class="text-xs capitalize text-muted-foreground">
                                {{ animal.animal_type }}<span v-if="animal.breed"> · {{ animal.breed }}</span>
                                <span v-if="animal.herd_number"> · #{{ animal.herd_number }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span v-if="animal.for_sale" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">For sale</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs capitalize"
                                :class="animal.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-muted text-muted-foreground'"
                            >
                                {{ animal.status }}
                            </span>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>

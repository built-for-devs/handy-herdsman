<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface Supply {
    id: number;
    item: string;
    category: string | null;
    unit: string | null;
    on_hand: string;
    low_stock_threshold: string;
    unit_cost: string;
    is_prescription: boolean;
    notes: string | null;
}

interface Props {
    supplies: Supply[];
    lowStock: Supply[];
    usageProfiles: Array<{ id: number; service_id: number; consumables: Record<string, number>; service: { id: number; name: string } | null }>;
    services: Array<{ id: number; name: string }>;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Supplies & consumables', href: '/admin/supplies' }];

const form = useForm({
    item: '',
    category: '',
    unit: 'each',
    on_hand: 0,
    low_stock_threshold: 0,
    unit_cost: 0,
    is_prescription: false,
    notes: '',
});

const submit = () => form.post('/admin/supplies', { preserveScroll: true, onSuccess: () => form.reset() });

const isLow = (s: Supply) => Number(s.on_hand) <= Number(s.low_stock_threshold);
</script>

<template>
    <Head title="Supplies & consumables" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <section v-if="lowStock.length" class="rounded-xl border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/40">
                <h2 class="font-semibold text-red-700 dark:text-red-300">Low stock — reorder soon</h2>
                <ul class="mt-2 list-disc pl-5 text-sm text-red-700 dark:text-red-300">
                    <li v-for="s in lowStock" :key="s.id">{{ s.item }} — {{ s.on_hand }} {{ s.unit }} (threshold {{ s.low_stock_threshold }})</li>
                </ul>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-4 font-semibold">Add supply</h2>
                <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1">
                        <Label for="item">Item</Label>
                        <Input id="item" v-model="form.item" required />
                        <InputError :message="form.errors.item" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="category">Category</Label>
                        <Input id="category" v-model="form.category" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="unit">Unit</Label>
                        <Input id="unit" v-model="form.unit" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="on_hand">On hand</Label>
                        <Input id="on_hand" type="number" step="0.01" v-model="form.on_hand" required />
                    </div>
                    <div class="grid gap-1">
                        <Label for="low_stock_threshold">Low-stock threshold</Label>
                        <Input id="low_stock_threshold" type="number" step="0.01" v-model="form.low_stock_threshold" required />
                    </div>
                    <div class="grid gap-1">
                        <Label for="unit_cost">Unit cost (USD)</Label>
                        <Input id="unit_cost" type="number" step="0.01" v-model="form.unit_cost" required />
                    </div>
                    <label class="flex items-center gap-2 text-sm"> <input type="checkbox" v-model="form.is_prescription" /> Prescription </label>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <Button type="submit" :disabled="form.processing">Add supply</Button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-4 font-semibold">Stock</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="py-2 pr-4">Item</th>
                                <th class="py-2 pr-4">Category</th>
                                <th class="py-2 pr-4">On hand</th>
                                <th class="py-2 pr-4">Threshold</th>
                                <th class="py-2 pr-4">Unit cost</th>
                                <th class="py-2 pr-4">Rx</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in supplies" :key="s.id" class="border-b" :class="{ 'bg-red-50 dark:bg-red-950/40': isLow(s) }">
                                <td class="py-2 pr-4">{{ s.item }}</td>
                                <td class="py-2 pr-4">{{ s.category }}</td>
                                <td class="py-2 pr-4">{{ s.on_hand }} {{ s.unit }}</td>
                                <td class="py-2 pr-4">{{ s.low_stock_threshold }}</td>
                                <td class="py-2 pr-4">${{ s.unit_cost }}</td>
                                <td class="py-2 pr-4">{{ s.is_prescription ? 'Yes' : '' }}</td>
                            </tr>
                            <tr v-if="!supplies.length">
                                <td colspan="6" class="py-4 text-center text-muted-foreground">No supplies yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-2 font-semibold">Service usage profiles</h2>
                <p class="mb-4 text-sm text-muted-foreground">
                    Default consumables auto-decremented on appointment completion (adjustable per visit).
                </p>
                <ul class="space-y-1 text-sm">
                    <li v-for="p in usageProfiles" :key="p.id">
                        <span class="font-medium">{{ p.service?.name ?? 'Service #' + p.service_id }}</span
                        >: {{ Object.keys(p.consumables || {}).length }} supply line(s)
                    </li>
                    <li v-if="!usageProfiles.length" class="text-muted-foreground">No usage profiles configured.</li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>

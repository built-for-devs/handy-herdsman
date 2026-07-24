<script setup lang="ts">
import DirectoryEntryForm from '@/components/admin/DirectoryEntryForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface EntryData {
    id: number;
    category: string;
    name: string;
    area: string | null;
    url: string | null;
    notes: string | null;
    active: boolean;
}

defineProps<{
    entry: EntryData;
    categories: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Resource directory', href: '/admin/directory' },
    { title: 'Edit', href: '#' },
];
</script>

<template>
    <Head title="Edit resource" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1 class="mb-6 text-xl font-semibold">Edit resource</h1>
            <DirectoryEntryForm :entry="entry" :categories="categories" submit-label="Save changes" />
        </div>
    </AppLayout>
</template>

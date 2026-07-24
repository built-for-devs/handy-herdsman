<script setup lang="ts">
import DirectoryEntryForm from '@/components/admin/DirectoryEntryForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{
    categories: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Resource directory', href: '/admin/directory' },
    { title: 'Add', href: '/admin/directory/create' },
];
</script>

<template>
    <Head title="Add resource" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1 class="mb-6 text-xl font-semibold">Add resource</h1>
            <DirectoryEntryForm :categories="categories" submit-label="Add resource" />
        </div>
    </AppLayout>
</template>

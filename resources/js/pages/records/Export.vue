<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Export records', href: '/records/export' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Export records" />

        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <HeadingSmall
                title="Export your records"
                description="Download all of your herd's cattle, health records and photo metadata. We keep your records indefinitely — export anytime."
            />

            <div class="flex flex-col gap-3 sm:flex-row">
                <a :href="route('records.export.download', { format: 'json' })">
                    <Button>Download JSON</Button>
                </a>
                <a :href="route('records.export.download', { format: 'csv' })">
                    <Button variant="outline">Download CSV (zip)</Button>
                </a>
            </div>
        </div>
    </AppLayout>
</template>

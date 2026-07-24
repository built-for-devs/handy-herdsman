<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface ListingField {
    key: string;
    label: string;
    value: string;
}

interface ListingPhoto {
    id: number;
    url: string;
    caption: string | null;
}

interface Listing {
    id: number;
    fields: ListingField[];
    photos: ListingPhoto[];
    listed_by_role: string | null;
}

const props = defineProps<{ listing: Listing }>();

const headline = props.listing.fields.find((f) => f.key === 'reg_name')?.value ?? 'Cattle for sale';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Cattle for sale', href: '/for-sale' },
    { title: headline, href: `/for-sale/${props.listing.id}` },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="headline" />

        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <HeadingSmall :title="headline" description="Listed on the clients-only for-sale board. Contact staff to enquire." />

            <p v-if="listing.listed_by_role === 'staff'" class="text-xs text-muted-foreground">Posted by Handy Herdsman staff.</p>

            <div v-if="listing.photos.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <img
                    v-for="photo in listing.photos"
                    :key="photo.id"
                    :src="photo.url"
                    :alt="photo.caption || headline"
                    class="aspect-square w-full rounded-md object-cover"
                />
            </div>

            <dl v-if="listing.fields.length" class="divide-y rounded-md border">
                <div v-for="field in listing.fields" :key="field.key" class="flex items-center justify-between gap-4 p-3">
                    <dt class="text-xs text-muted-foreground">{{ field.label }}</dt>
                    <dd class="text-sm font-medium capitalize">{{ field.value }}</dd>
                </div>
            </dl>
            <p v-else class="text-sm text-muted-foreground">The seller has not shared any details for this animal yet.</p>
        </div>
    </AppLayout>
</template>

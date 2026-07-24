<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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

defineProps<{ listings: Listing[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cattle for sale', href: '/for-sale' }];

const headline = (listing: Listing): string => {
    const name = listing.fields.find((f) => f.key === 'reg_name');
    return name?.value ?? 'Cattle for sale';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Cattle for sale" />

        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <HeadingSmall title="Cattle for sale" description="A clients-only board of animals listed by other clients and staff." />

            <p v-if="listings.length === 0" class="text-sm text-muted-foreground">No animals are listed for sale right now.</p>

            <ul v-else class="grid gap-4 sm:grid-cols-2">
                <li v-for="listing in listings" :key="listing.id" class="overflow-hidden rounded-md border">
                    <Link :href="route('for-sale.show', listing.id)" class="block hover:bg-muted/50">
                        <img
                            v-if="listing.photos.length"
                            :src="listing.photos[0].url"
                            :alt="listing.photos[0].caption || headline(listing)"
                            class="aspect-video w-full object-cover"
                        />
                        <div v-else class="flex aspect-video w-full items-center justify-center bg-muted text-xs text-muted-foreground">No photo</div>
                        <div class="space-y-1 p-3">
                            <p class="text-sm font-medium">{{ headline(listing) }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ listing.fields.length }} shared detail(s)
                                <span v-if="listing.listed_by_role === 'staff'"> · posted by staff</span>
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>

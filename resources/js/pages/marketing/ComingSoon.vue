<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    blurb: string;
}>();
</script>

<template>
    <Seo :title="`${title} — Handy Herdsman`" :description="blurb" />
    <PublicLayout>
        <section class="mx-auto max-w-2xl px-4 py-24 text-center sm:px-6">
            <p class="text-sm font-semibold uppercase tracking-wide text-primary">Coming soon</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">{{ title }}</h1>
            <p class="mt-4 text-muted-foreground">{{ blurb }}</p>
            <div class="mt-8 flex justify-center gap-3">
                <Button as-child variant="outline">
                    <Link :href="route('services')">Browse services</Link>
                </Button>
                <Button as-child>
                    <Link :href="route('contact')">Contact us</Link>
                </Button>
            </div>
        </section>
    </PublicLayout>
</template>

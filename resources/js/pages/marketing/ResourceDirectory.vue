<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface Entry {
    name: string;
    area: string | null;
    url: string | null;
    notes: string | null;
}

interface Group {
    category: string;
    label: string;
    entries: Entry[];
}

defineProps<{
    groups: Group[];
    meta: { title: string; description: string };
}>();
</script>

<template>
    <Seo :title="meta.title" :description="meta.description" />
    <PublicLayout>
        <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Local resource directory</h1>
            <p class="mt-4 text-muted-foreground">
                Trusted local vets, nutritionists, hoof trimmers and other AI techs around Waco and Valley Mills. Something we don't offer — like hoof
                trimming — lives here.
            </p>

            <p v-if="!groups.length" class="mt-10 text-muted-foreground">Directory listings are coming soon.</p>

            <div v-for="group in groups" :key="group.category" class="mt-10">
                <h2 class="text-lg font-semibold">{{ group.label }}</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div v-for="entry in group.entries" :key="entry.name" class="rounded-lg border border-border p-4">
                        <p class="font-medium">
                            <a v-if="entry.url" :href="entry.url" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">
                                {{ entry.name }}
                            </a>
                            <span v-else>{{ entry.name }}</span>
                        </p>
                        <p v-if="entry.area" class="mt-1 text-xs text-muted-foreground">{{ entry.area }}</p>
                        <p v-if="entry.notes" class="mt-2 text-sm text-muted-foreground">{{ entry.notes }}</p>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

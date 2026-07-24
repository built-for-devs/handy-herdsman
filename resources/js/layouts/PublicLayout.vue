<script setup lang="ts">
import PublicFooter from '@/components/marketing/PublicFooter.vue';
import PublicNav from '@/components/marketing/PublicNav.vue';

defineProps<{
    title?: string;
    subtitle?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <PublicNav />
        <main class="flex-1">
            <div v-if="title" class="mx-auto w-full max-w-3xl px-4 pt-8">
                <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground">{{ subtitle }}</p>
            </div>
            <slot />
        </main>
        <PublicFooter />
    </div>
</template>

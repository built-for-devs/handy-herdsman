<script setup lang="ts">
import PublicFooter from '@/components/marketing/PublicFooter.vue';
import PublicNav from '@/components/marketing/PublicNav.vue';
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <PublicNav />
        <main class="flex-1">
            <slot />
        </main>
        <PublicFooter />
    </div>
</template>

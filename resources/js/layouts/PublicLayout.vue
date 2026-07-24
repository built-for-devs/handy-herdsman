<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<!--
    Lightweight standalone shell for the public calculator lead-magnet pages
    (spec §5.4, §5.4b). When the M2 marketing shell (issue 2.1) lands, these
    pages should render inside it and the "Resources ▾" dropdown should link to
    the calculators.route() named routes below — this is the integration point.
-->
<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="border-b">
            <div class="mx-auto flex w-full max-w-3xl items-center justify-between px-4 py-4">
                <Link :href="route('home')" class="text-base font-semibold tracking-tight"> Handy Herdsman </Link>
                <nav class="flex items-center gap-3 text-sm">
                    <Link
                        :href="route('calculators.ai-timing')"
                        class="text-muted-foreground hover:text-foreground"
                        :class="{ 'font-medium text-foreground': route().current('calculators.ai-timing') }"
                    >
                        AI Timing
                    </Link>
                    <Link
                        :href="route('calculators.due-date')"
                        class="text-muted-foreground hover:text-foreground"
                        :class="{ 'font-medium text-foreground': route().current('calculators.due-date') }"
                    >
                        Due Date
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-muted-foreground">{{ subtitle }}</p>
            </div>

            <slot />
        </main>

        <footer class="border-t">
            <div class="mx-auto w-full max-w-3xl px-4 py-6 text-xs text-muted-foreground">
                Handy Herdsman — Cattle AI &amp; ranch services, Valley Mills, TX. Estimates are informational, not a confirmed booking.
            </div>
        </footer>
    </div>
</template>

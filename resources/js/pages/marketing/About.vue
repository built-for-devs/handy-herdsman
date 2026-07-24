<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface NotOffered {
    title: string;
    note: string;
}

defineProps<{
    notOffered: NotOffered[];
}>();
</script>

<template>
    <Seo
        title="About Handy Herdsman"
        description="Jeff's path from custom homes and fiber to cattle AI and ranch services — and an honest word about where the vet-license line sits."
    />
    <PublicLayout>
        <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Local and handy.</h1>
            <div class="mt-6 space-y-4 text-muted-foreground">
                <p>
                    Handy Herdsman is Jeff — a builder before he was a herdsman. Custom homes, then fiber, and now cattle AI and ranch services around
                    Valley Mills, Texas. He and Tessa milk their own two cows every day, so a farm in the field is home ground.
                </p>
                <p>
                    The work here is practical and hands-on: artificial insemination, calving help, ranch-hand days, and the non-vet health tasks that
                    keep a small herd healthy. Everything is priced plainly and pulled straight from our live service catalog — no surprises.
                </p>
            </div>

            <div class="mt-12 rounded-lg border border-border bg-muted/30 p-6">
                <h2 class="text-lg font-semibold">Honest about the line</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Jeff does everything legal in Texas without a veterinary license, and he is straight with you about what he does not do:
                </p>
                <ul class="mt-4 space-y-3">
                    <li v-for="item in notOffered" :key="item.title" class="text-sm">
                        <span class="font-medium">{{ item.title }}:</span>
                        <span class="text-muted-foreground"> {{ item.note }}</span>
                    </li>
                </ul>
            </div>
        </section>
    </PublicLayout>
</template>

<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Link } from '@inertiajs/vue3';

interface ServiceSummary {
    name: string;
    slug: string;
    description: string | null;
    price: string;
}

interface PostSummary {
    title: string;
    slug: string;
    excerpt: string | null;
    published_at: string | null;
}

defineProps<{
    brand: string;
    tagline: string;
    featuredServices: ServiceSummary[];
    latestPosts: PostSummary[];
}>();
</script>

<template>
    <Seo
        title="Handy Herdsman — Cattle AI & Ranch Services, Valley Mills TX"
        description="Cattle artificial insemination, herd management and ranch services around Valley Mills and Waco, TX. Start with a free AI timing plan."
    />
    <PublicLayout>
        <!-- Hero + prominent AI Timing Calculator placement (the lead magnet). -->
        <section class="border-b border-border bg-muted/30">
            <div class="mx-auto grid max-w-6xl gap-8 px-4 py-16 sm:px-6 md:grid-cols-2 md:items-center md:py-24">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl md:text-5xl">Cattle AI done right, close to home.</h1>
                    <p class="mt-4 text-lg text-muted-foreground">{{ tagline }}</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Button as-child size="lg">
                            <Link :href="route('resources.ai-timing')">Get my AI timing plan</Link>
                        </Button>
                        <Button as-child size="lg" variant="outline">
                            <Link :href="route('services')">See all services</Link>
                        </Button>
                    </div>
                </div>
                <Card class="border-primary/20 bg-background">
                    <CardHeader>
                        <CardTitle>Free AI Timing Calculator</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm text-muted-foreground">
                        <p>
                            Pick your first-visit date and animal type to see your breeding windows and a plain-English visit plan — then have it
                            texted or emailed to you.
                        </p>
                        <Button as-child class="w-full">
                            <Link :href="route('resources.ai-timing')">Start the calculator</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </section>

        <!-- Featured services -->
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <div class="flex items-end justify-between">
                <h2 class="text-2xl font-semibold tracking-tight">Popular services</h2>
                <Link :href="route('services')" class="text-sm font-medium text-primary hover:underline"> View all → </Link>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="service in featuredServices" :key="service.slug">
                    <CardHeader>
                        <CardTitle class="text-base">{{ service.name }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p class="text-sm text-muted-foreground">{{ service.description }}</p>
                        <p class="text-sm font-semibold">{{ service.price }}</p>
                    </CardContent>
                </Card>
            </div>
        </section>

        <!-- Latest education posts -->
        <section v-if="latestPosts.length" class="border-t border-border bg-muted/30">
            <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div class="flex items-end justify-between">
                    <h2 class="text-2xl font-semibold tracking-tight">From the blog</h2>
                    <Link :href="route('blog.index')" class="text-sm font-medium text-primary hover:underline"> Read more → </Link>
                </div>
                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    <Link
                        v-for="post in latestPosts"
                        :key="post.slug"
                        :href="route('blog.show', post.slug)"
                        class="rounded-lg border border-border bg-background p-5 transition-colors hover:border-primary/40"
                    >
                        <h3 class="font-medium">{{ post.title }}</h3>
                        <p class="mt-2 text-sm text-muted-foreground">{{ post.excerpt }}</p>
                    </Link>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Link } from '@inertiajs/vue3';

interface PostSummary {
    title: string;
    slug: string;
    excerpt: string | null;
    feature_image: string | null;
    published_at: string | null;
    category: { name: string; slug: string } | null;
}

defineProps<{
    category: { name: string; slug: string; description: string | null };
    posts: PostSummary[];
    meta: { title: string; description: string };
}>();

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '';
}
</script>

<template>
    <Seo :title="meta.title" :description="meta.description" />
    <PublicLayout>
        <section class="mx-auto max-w-5xl px-4 py-16 sm:px-6">
            <Link :href="route('blog.index')" class="text-sm text-muted-foreground hover:text-foreground">← All posts</Link>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{{ category.name }}</h1>
            <p v-if="category.description" class="mt-4 text-muted-foreground">{{ category.description }}</p>

            <p v-if="!posts.length" class="mt-12 text-muted-foreground">No posts in this pillar yet.</p>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="post in posts"
                    :key="post.slug"
                    :href="route('blog.show', post.slug)"
                    class="flex flex-col rounded-lg border border-border bg-background p-5 transition-colors hover:border-primary/40"
                >
                    <h2 class="font-semibold">{{ post.title }}</h2>
                    <p class="mt-2 flex-1 text-sm text-muted-foreground">{{ post.excerpt }}</p>
                    <p class="mt-3 text-xs text-muted-foreground">{{ formatDate(post.published_at) }}</p>
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>

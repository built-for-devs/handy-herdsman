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

interface Category {
    name: string;
    slug: string;
    description: string | null;
}

defineProps<{
    posts: PostSummary[];
    categories: Category[];
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
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Cattle AI &amp; herd education</h1>
            <p class="mt-4 text-muted-foreground">Practical, no-nonsense education on heat detection, AI protocols, nutrition, health and calving.</p>

            <div v-if="categories.length" class="mt-6 flex flex-wrap gap-2">
                <Link
                    v-for="category in categories"
                    :key="category.slug"
                    :href="route('blog.category', category.slug)"
                    class="rounded-full border border-border px-3 py-1 text-sm text-muted-foreground hover:border-primary/40 hover:text-foreground"
                >
                    {{ category.name }}
                </Link>
            </div>

            <p v-if="!posts.length" class="mt-12 text-muted-foreground">No posts published yet — check back soon.</p>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="post in posts"
                    :key="post.slug"
                    :href="route('blog.show', post.slug)"
                    class="flex flex-col overflow-hidden rounded-lg border border-border bg-background transition-colors hover:border-primary/40"
                >
                    <img v-if="post.feature_image" :src="post.feature_image" :alt="post.title" class="aspect-video w-full object-cover" />
                    <div class="flex flex-1 flex-col p-5">
                        <p v-if="post.category" class="text-xs font-medium uppercase tracking-wide text-primary">
                            {{ post.category.name }}
                        </p>
                        <h2 class="mt-1 font-semibold">{{ post.title }}</h2>
                        <p class="mt-2 flex-1 text-sm text-muted-foreground">{{ post.excerpt }}</p>
                        <p class="mt-3 text-xs text-muted-foreground">{{ formatDate(post.published_at) }}</p>
                    </div>
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>

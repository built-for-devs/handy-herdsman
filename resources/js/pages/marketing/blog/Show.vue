<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Link } from '@inertiajs/vue3';

interface Post {
    title: string;
    slug: string;
    excerpt: string | null;
    body: string | null;
    feature_image: string | null;
    published_at: string | null;
    category: { name: string; slug: string } | null;
    meta_title: string | null;
    meta_description: string | null;
}

const props = defineProps<{
    post: Post;
    meta: { title: string; description: string | null };
    jsonLd: Record<string, unknown>;
}>();

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) : '';
}
</script>

<template>
    <Seo :title="meta.title" :description="meta.description" :json-ld="props.jsonLd" />
    <PublicLayout>
        <article class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
            <Link :href="route('blog.index')" class="text-sm text-muted-foreground hover:text-foreground">← All posts</Link>

            <p v-if="post.category" class="mt-6 text-sm font-medium uppercase tracking-wide text-primary">
                <Link :href="route('blog.category', post.category.slug)">{{ post.category.name }}</Link>
            </p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">{{ post.title }}</h1>
            <p class="mt-3 text-sm text-muted-foreground">{{ formatDate(post.published_at) }}</p>

            <img v-if="post.feature_image" :src="post.feature_image" :alt="post.title" class="mt-8 aspect-video w-full rounded-lg object-cover" />

            <!-- Body is trusted, cleaned content migrated by staff. -->
            <div class="prose prose-neutral dark:prose-invert mt-8 max-w-none" v-html="post.body" />
        </article>
    </PublicLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const year = new Date().getFullYear();

const columns = [
    {
        heading: 'Explore',
        links: [
            { label: 'Services', route: 'services' },
            { label: 'Pricing', route: 'pricing' },
            { label: 'Blog', route: 'blog.index' },
            { label: 'FAQ', route: 'faq' },
        ],
    },
    {
        heading: 'Resources',
        links: [
            { label: 'AI Timing Calculator', route: 'resources.ai-timing' },
            { label: 'Due Date Calculator', route: 'resources.due-date' },
            { label: 'Resource Directory', route: 'resources.directory' },
            { label: 'Cattle for Sale', route: 'resources.cattle-for-sale' },
        ],
    },
    {
        heading: 'Company',
        links: [
            { label: 'About', route: 'about' },
            { label: 'Contact', route: 'contact' },
        ],
    },
];
</script>

<template>
    <footer class="border-t border-border bg-muted/40">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-4">
            <div>
                <p class="font-semibold">Handy Herdsman</p>
                <p class="mt-2 text-sm text-muted-foreground">Cattle AI, herd management & ranch services — Valley Mills, TX.</p>
            </div>
            <div v-for="col in columns" :key="col.heading">
                <p class="text-sm font-semibold">{{ col.heading }}</p>
                <ul class="mt-3 space-y-2">
                    <li v-for="link in col.links" :key="link.route">
                        <Link :href="route(link.route)" class="text-sm text-muted-foreground hover:text-foreground">
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
        <div class="border-t border-border py-4 text-center text-xs text-muted-foreground">
            © {{ year }} Handy Herdsman. Distinct from Homestead Herds.
        </div>
    </footer>
</template>

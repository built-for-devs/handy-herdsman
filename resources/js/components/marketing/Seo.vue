<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps<{
    title?: string;
    description?: string | null;
    jsonLd?: Record<string, unknown> | null;
}>();

// JSON-LD structured data is injected as a real <script type="application/ld+json">
// in the document head (a script tag cannot live in a Vue template).
let ldEl: HTMLScriptElement | null = null;

function syncJsonLd(): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (props.jsonLd) {
        if (!ldEl) {
            ldEl = document.createElement('script');
            ldEl.type = 'application/ld+json';
            document.head.appendChild(ldEl);
        }
        ldEl.textContent = JSON.stringify(props.jsonLd);
    } else if (ldEl) {
        ldEl.remove();
        ldEl = null;
    }
}

onMounted(syncJsonLd);
watch(() => props.jsonLd, syncJsonLd);
onBeforeUnmount(() => {
    ldEl?.remove();
    ldEl = null;
});
</script>

<template>
    <Head :title="props.title">
        <meta v-if="props.description" name="description" :content="props.description" head-key="description" />
        <meta v-if="props.title" property="og:title" :content="props.title" head-key="og:title" />
        <meta v-if="props.description" property="og:description" :content="props.description" head-key="og:description" />
        <meta property="og:type" content="website" head-key="og:type" />
    </Head>
</template>

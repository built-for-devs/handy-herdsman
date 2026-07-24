<script setup lang="ts">
import { loadStripe, type Stripe, type StripeCardElement } from '@stripe/stripe-js';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Mobile-first Stripe Elements card entry (spec §5.6, §8.1). Captures a card at
 * booking via a SetupIntent and hands back a PaymentMethod id — no card data
 * ever touches our server. Charged later, when the booking reaches `confirmed`.
 */
const props = defineProps<{
    publishableKey: string;
    clientSecret: string;
}>();

const cardMount = ref<HTMLElement | null>(null);
const error = ref<string>('');
const ready = ref(false);

let stripe: Stripe | null = null;
let card: StripeCardElement | null = null;

onMounted(async () => {
    stripe = await loadStripe(props.publishableKey);
    if (!stripe || !cardMount.value) {
        error.value = 'Unable to load secure card entry. Try cash, or refresh.';
        return;
    }
    const elements = stripe.elements();
    card = elements.create('card', { hidePostalCode: false });
    card.mount(cardMount.value);
    card.on('change', (event) => {
        error.value = event.error?.message ?? '';
    });
    ready.value = true;
});

onBeforeUnmount(() => {
    card?.destroy();
});

/**
 * Confirm the SetupIntent and resolve the stored PaymentMethod id, or null on
 * failure (the message is surfaced inline).
 */
async function confirm(): Promise<string | null> {
    if (!stripe || !card) {
        return null;
    }
    const result = await stripe.confirmCardSetup(props.clientSecret, {
        payment_method: { card },
    });
    if (result.error) {
        error.value = result.error.message ?? 'We could not verify that card.';
        return null;
    }
    const pm = result.setupIntent?.payment_method;
    return typeof pm === 'string' ? pm : (pm?.id ?? null);
}

defineExpose({ confirm });
</script>

<template>
    <div class="space-y-2">
        <div ref="cardMount" class="min-h-11 rounded-md border border-input bg-background px-3 py-3" />
        <p v-if="!ready" class="text-xs text-muted-foreground">Loading secure card entry…</p>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    </div>
</template>

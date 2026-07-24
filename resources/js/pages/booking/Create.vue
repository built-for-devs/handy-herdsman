<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import StripeCardField from '@/components/booking/StripeCardField.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Service {
    id: number;
    name: string;
    description: string | null;
    category: string | null;
    type: string;
    price_rule: Record<string, unknown>;
    breeds: boolean;
}

interface CattleOption {
    id: number;
    name: string;
    animal_type: string;
}

interface ServiceArea {
    distance_miles: number | null;
    in_range: boolean;
    fee_applies: boolean;
    fee_amount: number;
    declined: boolean;
    zone_label: string | null;
}

interface Candidate {
    visit1_at: string;
    visit1_local: string;
    visit2_local: string;
    visit3_recommended_local: string;
    visit3_window_local: string;
    beyond_horizon: boolean;
}

interface OnCallKind {
    value: string;
    label: string;
}

interface PaymentSetup {
    client_secret: string | null;
    id: string | null;
    publishable_key: string | null;
}

const props = defineProps<{
    services: Service[];
    cattle: CattleOption[];
    serviceArea: ServiceArea | null;
    onCallKinds: OnCallKind[];
    payment: PaymentSetup;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bookings', href: '/bookings' },
    { title: 'Book a visit', href: '/bookings/create' },
];

const selected = ref<Service | null>(null);
const candidates = ref<Candidate[]>([]);
const candidateMessage = ref<string>('');
const loadingDates = ref(false);

const form = useForm<{
    service_id: number | null;
    cattle_ids: number[];
    proposed_start: string;
    oncall_kind: string | null;
    heat_observed_at: string;
    is_cash: boolean;
    payment_method_id: string | null;
}>({
    service_id: null,
    cattle_ids: [],
    proposed_start: '',
    oncall_kind: null,
    heat_observed_at: '',
    is_cash: false,
    payment_method_id: null,
});

const stripeAvailable = computed(() => !!props.payment.publishable_key && !!props.payment.client_secret);
const cardField = ref<InstanceType<typeof StripeCardField> | null>(null);
const paymentError = ref('');
const confirmingCard = ref(false);

// The window type of the current selection: cow and heifer windows are ~8h
// apart so a protocol booking may not mix them (§6.4).
const windowKeys = computed(() => {
    const types = props.cattle.filter((c) => form.cattle_ids.includes(c.id)).map((c) => c.animal_type);
    return [...new Set(types.map((t) => (t === 'heifer' ? 'heifer' : 'cow')))];
});

const mixedGroup = computed(() => selected.value?.type === 'protocol' && windowKeys.value.length > 1);

const selectService = async (service: Service) => {
    selected.value = service;
    form.service_id = service.id;
    form.proposed_start = '';
    candidates.value = [];
    candidateMessage.value = '';
    form.oncall_kind = service.type === 'oncall' ? (props.onCallKinds[0]?.value ?? null) : null;
};

const loadDates = async () => {
    if (!selected.value || selected.value.type !== 'protocol' || windowKeys.value.length !== 1) {
        return;
    }
    loadingDates.value = true;
    const animalType = windowKeys.value[0] === 'heifer' ? 'heifer' : 'cow';
    const res = await fetch(`${route('bookings.candidates')}?animal_type=${animalType}`, {
        headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    candidates.value = data.candidates ?? [];
    candidateMessage.value = data.message ?? '';
    loadingDates.value = false;
};

const toggleCattle = (id: number) => {
    const i = form.cattle_ids.indexOf(id);
    if (i === -1) {
        form.cattle_ids.push(id);
    } else {
        form.cattle_ids.splice(i, 1);
    }
    if (selected.value?.type === 'protocol') {
        candidates.value = [];
        candidateMessage.value = '';
    }
};

const pickDate = (candidate: Candidate) => {
    form.proposed_start = candidate.visit1_at;
};

const estimate = computed(() => {
    if (!selected.value) return null;
    const rule = selected.value.price_rule as Record<string, number | string>;
    const head = Math.max(1, form.cattle_ids.length);
    let base = 0;
    if (rule.model === 'per_head') {
        const perHead = Number(rule.per_head ?? 0);
        const min = Number(rule.visit_minimum ?? 0);
        base = Math.max(perHead * head, min);
    } else {
        base = Number(rule.price ?? 0);
        const included = Number(rule.included_cows ?? 0);
        const perAdd = Number(rule.per_additional_cow ?? 0);
        if (included > 0 && perAdd > 0 && head > included) {
            base += (head - included) * perAdd;
        }
    }
    const fee = props.serviceArea?.fee_applies ? props.serviceArea.fee_amount : 0;
    return { base, fee, total: base + fee };
});

const canSubmit = computed(() => {
    if (!selected.value || form.cattle_ids.length === 0 || mixedGroup.value) return false;
    if (selected.value.type === 'oncall') return !!form.oncall_kind;
    if (selected.value.type === 'protocol') return !!form.proposed_start;
    return !!form.proposed_start;
});

const submit = async () => {
    paymentError.value = '';

    // Card payment: capture the method via Stripe Elements before submitting, so
    // the server only ever sees a PaymentMethod id (spec §5.6).
    if (!form.is_cash && stripeAvailable.value) {
        confirmingCard.value = true;
        const pm = await cardField.value?.confirm();
        confirmingCard.value = false;
        if (!pm) {
            paymentError.value = 'Please enter valid card details, or choose cash.';
            return;
        }
        form.payment_method_id = pm;
    } else {
        form.payment_method_id = null;
    }

    form.post(route('bookings.store'));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Book a visit" />

        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <HeadingSmall title="Book a visit" description="Pick a service, choose your animals, confirm the time." />

            <InputError :message="form.errors.booking" />

            <!-- Step 1: service picker cards -->
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="service in services"
                    :key="service.id"
                    type="button"
                    @click="selectService(service)"
                    class="rounded-xl border p-4 text-left transition active:scale-[.99]"
                    :class="selected?.id === service.id ? 'border-primary ring-2 ring-primary' : 'hover:bg-muted/50'"
                >
                    <p class="text-base font-semibold">{{ service.name }}</p>
                    <p v-if="service.description" class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ service.description }}</p>
                    <span class="mt-2 inline-block rounded-full bg-muted px-2 py-0.5 text-[11px] uppercase tracking-wide">{{ service.type }}</span>
                </button>
            </div>

            <template v-if="selected">
                <!-- Step 2: animals -->
                <div class="space-y-2">
                    <p class="text-sm font-medium">Which animals?</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="animal in cattle"
                            :key="animal.id"
                            class="flex items-center gap-3 rounded-lg border p-3"
                            :class="form.cattle_ids.includes(animal.id) ? 'border-primary bg-primary/5' : ''"
                        >
                            <input type="checkbox" class="size-6" :checked="form.cattle_ids.includes(animal.id)" @change="toggleCattle(animal.id)" />
                            <span class="text-sm"
                                >{{ animal.name }} <span class="text-xs capitalize text-muted-foreground">· {{ animal.animal_type }}</span></span
                            >
                        </label>
                    </div>
                    <p v-if="cattle.length === 0" class="text-sm text-muted-foreground">Add animals to your herd first.</p>
                    <InputError :message="form.errors.cattle_ids" />
                </div>

                <!-- Mixed-group block + split offer (§6.4) -->
                <div v-if="mixedGroup" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-medium">Cows and heifers can't share one AI protocol.</p>
                    <p class="mt-1">
                        Their Visit 3 windows are about 8 hours apart, so they can't be bred on one visit. Book them as two separate protocols.
                    </p>
                </div>

                <!-- Protocol: viable Visit 1 dates -->
                <div v-if="selected.type === 'protocol' && !mixedGroup && form.cattle_ids.length" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium">Choose your Visit 1 date</p>
                        <Button type="button" size="sm" variant="outline" :disabled="loadingDates" @click="loadDates">
                            {{ loadingDates ? 'Finding…' : 'Find dates' }}
                        </Button>
                    </div>
                    <p v-if="candidateMessage" class="text-xs text-muted-foreground">{{ candidateMessage }}</p>
                    <div class="grid gap-2">
                        <button
                            v-for="c in candidates"
                            :key="c.visit1_at"
                            type="button"
                            @click="pickDate(c)"
                            class="rounded-lg border p-3 text-left"
                            :class="form.proposed_start === c.visit1_at ? 'border-primary ring-2 ring-primary' : 'hover:bg-muted/50'"
                        >
                            <p class="text-sm font-medium">
                                {{ c.visit1_local }}
                                <span v-if="c.beyond_horizon" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-800"
                                    >later date</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">Visit 2: {{ c.visit2_local }}</p>
                            <p class="text-xs text-muted-foreground">
                                Visit 3: {{ c.visit3_recommended_local }} (window {{ c.visit3_window_local }})
                            </p>
                        </button>
                    </div>
                </div>

                <!-- On-call: one-tap options (§6.5) -->
                <div v-else-if="selected.type === 'oncall'" class="space-y-3">
                    <p class="text-sm font-medium">What's happening?</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="kind in onCallKinds"
                            :key="kind.value"
                            type="button"
                            @click="form.oncall_kind = kind.value"
                            class="rounded-lg border p-4 text-center text-sm font-medium"
                            :class="form.oncall_kind === kind.value ? 'border-primary ring-2 ring-primary' : 'hover:bg-muted/50'"
                        >
                            {{ kind.label }}
                        </button>
                    </div>
                    <div v-if="form.oncall_kind === 'standing_heat'" class="grid gap-2">
                        <label class="text-sm text-muted-foreground" for="heat">When did you first see standing heat?</label>
                        <input
                            id="heat"
                            type="datetime-local"
                            v-model="form.heat_observed_at"
                            class="h-11 rounded-md border border-input bg-background px-3 text-sm"
                        />
                    </div>
                    <p class="rounded-lg bg-red-50 p-3 text-xs text-red-800">
                        Jeff is paged by text immediately. He'll respond in the app — AM heat breeds this afternoon, PM heat breeds next morning.
                    </p>
                </div>

                <!-- Standard: date/time -->
                <div v-else-if="selected.type === 'standard' && !mixedGroup" class="grid gap-2">
                    <label class="text-sm font-medium" for="start">Preferred date &amp; time</label>
                    <input
                        id="start"
                        type="datetime-local"
                        v-model="form.proposed_start"
                        class="h-11 rounded-md border border-input bg-background px-3 text-sm"
                    />
                </div>

                <!-- Transparent pricing + distance fee (§6.6) -->
                <div v-if="estimate" class="rounded-lg border p-4 text-sm">
                    <div class="flex justify-between">
                        <span>Service</span><span>${{ estimate.base.toFixed(2) }}</span>
                    </div>
                    <div v-if="serviceArea && serviceArea.fee_applies" class="flex justify-between text-muted-foreground">
                        <span>Distance fee (one per booking, {{ serviceArea.distance_miles }} mi)</span><span>${{ estimate.fee.toFixed(2) }}</span>
                    </div>
                    <div class="mt-1 flex justify-between border-t pt-1 font-semibold">
                        <span>Estimated total</span><span>${{ estimate.total.toFixed(2) }}</span>
                    </div>
                </div>

                <!-- Step: how will you pay? (§5.6) big card/cash toggle -->
                <div class="space-y-3">
                    <p class="text-sm font-medium">How would you like to pay?</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="form.is_cash = false"
                            class="rounded-xl border p-4 text-center text-sm font-medium transition active:scale-[.99]"
                            :class="!form.is_cash ? 'border-primary ring-2 ring-primary' : 'hover:bg-muted/50'"
                        >
                            Pay by card
                            <span class="mt-0.5 block text-[11px] font-normal text-muted-foreground">Charged when confirmed</span>
                        </button>
                        <button
                            type="button"
                            @click="form.is_cash = true"
                            class="rounded-xl border p-4 text-center text-sm font-medium transition active:scale-[.99]"
                            :class="form.is_cash ? 'border-primary ring-2 ring-primary' : 'hover:bg-muted/50'"
                        >
                            Cash in person
                            <span class="mt-0.5 block text-[11px] font-normal text-muted-foreground">Settle with Jeff</span>
                        </button>
                    </div>

                    <!-- Card entry via Stripe Elements -->
                    <div v-if="!form.is_cash">
                        <StripeCardField
                            v-if="stripeAvailable"
                            ref="cardField"
                            :publishable-key="payment.publishable_key!"
                            :client-secret="payment.client_secret!"
                        />
                        <p v-else class="rounded-lg bg-muted p-3 text-xs text-muted-foreground">
                            Card payments aren't set up yet — choose “Cash in person” and settle with Jeff.
                        </p>
                    </div>
                    <p v-else class="rounded-lg bg-muted p-3 text-xs text-muted-foreground">
                        We'll mark this booking unpaid — Jeff collects cash when he's on the farm.
                    </p>
                    <InputError :message="paymentError" />
                </div>

                <Button type="button" class="h-12 w-full text-base" :disabled="!canSubmit || form.processing || confirmingCard" @click="submit">
                    <template v-if="form.is_cash">Submit booking</template>
                    <template v-else-if="estimate">Pay ${{ estimate.total.toFixed(2) }} on confirm</template>
                    <template v-else>Submit booking</template>
                </Button>
            </template>
        </div>
    </AppLayout>
</template>

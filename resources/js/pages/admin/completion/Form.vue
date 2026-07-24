<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCompletionDraft } from '@/composables/useCompletionDraft';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Animal {
    id: number;
    label: string;
    breed: string | null;
}
interface SemenLot {
    id: number;
    label: string;
    straws_count: number;
}
interface SupplyOption {
    id: number;
    item: string;
    unit: string | null;
    on_hand: number;
    default_qty: number;
}

interface Props {
    visit: {
        id: number;
        type: string;
        status: string;
        scheduled_at: string | null;
        completed_at: string | null;
        service: string | null;
        is_breeding_related: boolean;
        is_ai: boolean;
    };
    animals: Animal[];
    semenLots: SemenLot[];
    supplies: SupplyOption[];
    existing: { completed_at: string | null; straws_used: number; straws_wasted: number; mileage: string | null; notes: string | null } | null;
    bcsRange: { min: number; max: number };
    draftKey: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Bookings', href: '/admin/bookings' },
    { title: `Complete visit #${props.visit.id}`, href: `/admin/visits/${props.visit.id}/completion` },
];

// Default the authoritative timestamp to "now" in the browser's local time.
const localNow = () => {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
};

const form = useForm({
    completed_at: props.existing?.completed_at?.slice(0, 16) ?? localNow(),
    procedure_confirmed: false,
    semen_inventory_id: null as number | null,
    straws_used: props.existing?.straws_used ?? (props.visit.is_ai ? 1 : 0),
    straws_wasted: props.existing?.straws_wasted ?? 0,
    supplies_used: Object.fromEntries(props.supplies.map((s) => [s.id, s.default_qty])) as Record<number, number>,
    mileage: props.existing?.mileage ? Number(props.existing.mileage) : (null as number | null),
    notes: props.existing?.notes ?? '',
    animals: props.animals.map((a) => ({ cattle_id: a.id, bcs_score: null as number | null, note: '' })),
});

// ---- Offline draft persistence (#240) --------------------------------------
const draftState = computed(() => ({ ...form.data() }));
const queuedOffline = ref(false);

const submitToServer = (): Promise<void> =>
    new Promise((resolve, reject) => {
        form.transform((d) => d).post(`/admin/visits/${props.visit.id}/completion`, {
            onSuccess: () => {
                draftApi.clear();
                queuedOffline.value = false;
                resolve();
            },
            onError: () => reject(new Error('validation')),
        });
    });

const draftApi = useCompletionDraft({
    key: props.draftKey,
    state: draftState,
    onFlush: submitToServer,
    shouldFlush: () => queuedOffline.value,
});

// Restore any local draft (e.g. after a reload with no signal).
const restored = draftApi.restore();
if (restored) {
    Object.assign(form, restored);
}

const submit = () => {
    if (navigator.onLine) {
        void submitToServer();
    } else {
        // No signal — keep it saved locally and flush automatically on reconnect.
        draftApi.persist();
        queuedOffline.value = true;
    }
};

const statusLabel = computed(() => {
    switch (draftApi.status.value) {
        case 'saved_local':
            return 'Saved on this device';
        case 'offline':
            return queuedOffline.value ? 'Offline — will sync when signal returns' : 'Offline — saved on this device';
        case 'syncing':
            return 'Syncing…';
        case 'synced':
            return 'Synced';
        default:
            return 'Not yet saved';
    }
});

// ---- Steppers --------------------------------------------------------------
const step = (obj: Record<string, number>, key: string, delta: number, min = 0, max = 999) => {
    const next = (Number(obj[key]) || 0) + delta;
    obj[key] = Math.min(max, Math.max(min, next));
};

// ---- Photos (reuse the existing per-animal media endpoint) -----------------
const uploaded = ref<Record<number, number>>({});
const uploadPhotos = (cattleId: number, event: Event) => {
    const files = (event.target as HTMLInputElement).files;
    if (!files) return;
    Array.from(files).forEach((file) => {
        router.post(
            `/cattle/${cattleId}/media`,
            { photo: file, visit_id: props.visit.id, taken_at: form.completed_at },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => (uploaded.value[cattleId] = (uploaded.value[cattleId] ?? 0) + 1),
            },
        );
    });
};
</script>

<template>
    <Head :title="`Complete visit #${visit.id}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 pb-32" @submit.prevent="submit">
            <!-- Draft status: always visible so Jeff trusts nothing is lost. -->
            <div class="flex items-center justify-between rounded-lg border bg-muted/40 px-3 py-2 text-sm">
                <span class="font-medium">{{ visit.service ?? 'Visit' }} · {{ visit.type.toUpperCase() }}</span>
                <span :class="draftApi.isOnline.value ? 'text-emerald-600' : 'text-amber-600'">● {{ statusLabel }}</span>
            </div>

            <!-- Procedure + authoritative timestamp -->
            <section class="flex flex-col gap-4 rounded-xl border p-4">
                <label class="flex items-center gap-3 text-base">
                    <Checkbox id="confirmed" v-model:checked="form.procedure_confirmed" class="size-6" />
                    <span class="font-medium">Procedure performed</span>
                </label>
                <InputError :message="form.errors.procedure_confirmed" />

                <div class="grid gap-2">
                    <Label for="completed_at">Completed at</Label>
                    <Input id="completed_at" v-model="form.completed_at" type="datetime-local" class="h-12 text-base" />
                    <p class="text-xs text-muted-foreground">This is the authoritative time the appointment happened.</p>
                    <InputError :message="form.errors.completed_at" />
                </div>
            </section>

            <!-- AI: straw used + wasted -->
            <section v-if="visit.is_ai" class="flex flex-col gap-4 rounded-xl border p-4">
                <h2 class="font-semibold">Semen used</h2>
                <div class="grid gap-2">
                    <Label for="lot">Straw / sire</Label>
                    <select id="lot" v-model="form.semen_inventory_id" class="h-12 rounded-md border border-input bg-background px-3 text-base">
                        <option :value="null">— select lot —</option>
                        <option v-for="lot in semenLots" :key="lot.id" :value="lot.id">{{ lot.label }} ({{ lot.straws_count }} left)</option>
                    </select>
                    <InputError :message="form.errors.semen_inventory_id" />
                </div>

                <div class="flex items-center justify-between">
                    <span>Straws used</span>
                    <div class="flex items-center gap-3">
                        <Button type="button" variant="outline" class="size-11 text-xl" @click="step(form, 'straws_used', -1)">−</Button>
                        <span class="w-8 text-center text-lg font-semibold">{{ form.straws_used }}</span>
                        <Button type="button" variant="outline" class="size-11 text-xl" @click="step(form, 'straws_used', 1)">+</Button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <span>Wasted / failed straws</span>
                    <div class="flex items-center gap-3">
                        <Button type="button" variant="outline" class="size-11 text-xl" @click="step(form, 'straws_wasted', -1)">−</Button>
                        <span class="w-8 text-center text-lg font-semibold">{{ form.straws_wasted }}</span>
                        <Button type="button" variant="outline" class="size-11 text-xl" @click="step(form, 'straws_wasted', 1)">+</Button>
                    </div>
                </div>
            </section>

            <!-- Per-animal BCS + notes + photos -->
            <section class="flex flex-col gap-4 rounded-xl border p-4">
                <h2 class="font-semibold">
                    Animals
                    <span v-if="visit.is_breeding_related" class="text-sm font-normal text-amber-600">· BCS required</span>
                </h2>
                <InputError :message="form.errors.animals" />

                <div v-for="(row, i) in form.animals" :key="row.cattle_id" class="flex flex-col gap-3 rounded-lg border p-3">
                    <div class="font-medium">{{ animals[i]?.label }}</div>

                    <div>
                        <Label class="text-sm">Body Condition Score ({{ bcsRange.min }}–{{ bcsRange.max }})</Label>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <button
                                v-for="n in bcsRange.max - bcsRange.min + 1"
                                :key="n"
                                type="button"
                                class="size-11 rounded-md border text-base font-semibold"
                                :class="
                                    row.bcs_score === n + bcsRange.min - 1 ? 'border-primary bg-primary text-primary-foreground' : 'bg-background'
                                "
                                @click="row.bcs_score = n + bcsRange.min - 1"
                            >
                                {{ n + bcsRange.min - 1 }}
                            </button>
                        </div>
                    </div>

                    <textarea
                        v-model="row.note"
                        rows="2"
                        placeholder="Quick condition note (optional)"
                        class="rounded-md border border-input bg-background p-2 text-base"
                    />

                    <label class="text-sm text-muted-foreground">
                        <span class="mb-1 block">Photos {{ uploaded[row.cattle_id] ? `(${uploaded[row.cattle_id]} uploaded)` : '' }}</span>
                        <input type="file" accept="image/*" capture="environment" multiple @change="uploadPhotos(row.cattle_id, $event)" />
                    </label>
                </div>
            </section>

            <!-- Supplies -->
            <section v-if="supplies.length" class="flex flex-col gap-3 rounded-xl border p-4">
                <h2 class="font-semibold">Supplies used</h2>
                <div v-for="s in supplies" :key="s.id" class="flex items-center justify-between gap-3">
                    <span class="text-sm"
                        >{{ s.item }} <span class="text-muted-foreground">({{ s.on_hand }} {{ s.unit }} on hand)</span></span
                    >
                    <div class="flex items-center gap-3">
                        <Button type="button" variant="outline" class="size-10 text-lg" @click="step(form.supplies_used, String(s.id), -1)">−</Button>
                        <span class="w-8 text-center font-semibold">{{ form.supplies_used[s.id] ?? 0 }}</span>
                        <Button type="button" variant="outline" class="size-10 text-lg" @click="step(form.supplies_used, String(s.id), 1)">+</Button>
                    </div>
                </div>
            </section>

            <!-- Mileage + notes -->
            <section class="flex flex-col gap-4 rounded-xl border p-4">
                <div class="grid gap-2">
                    <Label for="mileage">Trip mileage</Label>
                    <Input id="mileage" v-model="form.mileage" type="number" step="0.1" min="0" class="h-12 text-base" />
                    <InputError :message="form.errors.mileage" />
                </div>
                <div class="grid gap-2">
                    <Label for="notes">Visit notes (written to each animal's records)</Label>
                    <textarea id="notes" v-model="form.notes" rows="3" class="rounded-md border border-input bg-background p-2 text-base" />
                    <InputError :message="form.errors.notes" />
                </div>
            </section>

            <!-- Sticky submit — big tap target -->
            <div class="fixed inset-x-0 bottom-0 border-t bg-background/95 p-4 backdrop-blur">
                <div class="mx-auto flex max-w-2xl items-center gap-3">
                    <span class="flex-1 text-sm text-muted-foreground">{{ statusLabel }}</span>
                    <Button type="submit" size="lg" class="h-14 flex-1 text-base" :disabled="form.processing">Complete appointment</Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>

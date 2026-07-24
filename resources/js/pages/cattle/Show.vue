<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Cattle {
    id: number;
    reg_name: string | null;
    herd_number: string | null;
    dob: string | null;
    breed: string | null;
    animal_type: string;
    has_calved: boolean;
    status: string;
    for_sale: boolean;
    for_sale_shared_fields: string[];
    for_sale_listed_by_role: string | null;
    notes: string | null;
}

interface HealthRecordItem {
    id: number;
    type: string;
    type_label: string;
    bcs_score: number | null;
    recorded_at: string | null;
    added_role: string | null;
    added_by: string | null;
    edited_by: string | null;
    edited_at: string | null;
    can_edit: boolean;
    can_delete: boolean;
}

interface MediaItem {
    id: number;
    url: string;
    caption: string | null;
    taken_at: string | null;
    uploaded_role: string | null;
    uploaded_by: string | null;
    visit_id: number | null;
    can_delete: boolean;
}

const props = defineProps<{
    cattle: Cattle;
    canEdit: boolean;
    shareableFields: Record<string, string>;
    recordTypes: Record<string, string>;
    bcs: { min: number; max: number };
    records: HealthRecordItem[];
    media: MediaItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Herd', href: '/cattle' },
    { title: props.cattle.reg_name || 'Animal', href: `/cattle/${props.cattle.id}` },
];

const recordForm = useForm<{ type: string; bcs_score: number | null; note: string }>({
    type: 'general',
    bcs_score: null,
    note: '',
});

const submitRecord = () =>
    recordForm
        .transform((data) => ({
            type: data.type,
            bcs_score: data.type === 'body_condition' ? data.bcs_score : null,
            payload: data.note ? { note: data.note } : null,
        }))
        .post(route('cattle.records.store', props.cattle.id), {
            preserveScroll: true,
            onSuccess: () => recordForm.reset(),
        });

const deleteRecord = (id: number) => router.delete(route('records.destroy', id), { preserveScroll: true });

const mediaForm = useForm<{ photo: File | null; caption: string }>({ photo: null, caption: '' });

const submitMedia = () =>
    mediaForm.post(route('cattle.media.store', props.cattle.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => mediaForm.reset(),
    });

const onFile = (e: Event) => {
    mediaForm.photo = (e.target as HTMLInputElement).files?.[0] ?? null;
};

const deleteMedia = (id: number) => router.delete(route('cattle.media.destroy', id), { preserveScroll: true });

const listingForm = useForm<{ for_sale: boolean; shared_fields: string[] }>({
    for_sale: props.cattle.for_sale,
    shared_fields: [...props.cattle.for_sale_shared_fields],
});

const toggleSharedField = (key: string) => {
    const next = new Set(listingForm.shared_fields);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    listingForm.shared_fields = [...next];
};

const listAnimal = () => {
    listingForm.for_sale = true;
    submitListing();
};

const unlistAnimal = () => {
    listingForm.for_sale = false;
    submitListing();
};

const submitListing = () => listingForm.put(route('cattle.for-sale.update', props.cattle.id), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="cattle.reg_name || 'Animal'" />

        <div class="mx-auto w-full max-w-2xl space-y-8 p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold">{{ cattle.reg_name || 'Unnamed animal' }}</h1>
                    <p class="text-sm capitalize text-muted-foreground">
                        {{ cattle.animal_type }}<span v-if="cattle.breed"> · {{ cattle.breed }}</span>
                        <span v-if="cattle.herd_number"> · #{{ cattle.herd_number }}</span>
                    </p>
                    <p class="mt-1 text-xs capitalize text-muted-foreground">Status: {{ cattle.status }}</p>
                </div>
                <Link v-if="canEdit" :href="route('cattle.edit', cattle.id)">
                    <Button size="sm" variant="outline">Edit</Button>
                </Link>
            </div>

            <p v-if="cattle.notes" class="rounded-md border bg-muted/30 p-3 text-sm">{{ cattle.notes }}</p>

            <!-- For-sale listing (§5.9) — clients-only bulletin board -->
            <section v-if="canEdit" class="space-y-4">
                <HeadingSmall title="For-sale board" description="List this animal to other clients. You choose exactly which fields are shared." />

                <div v-if="cattle.for_sale" class="space-y-3 rounded-md border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-medium text-amber-900">Listed on the for-sale board</p>
                    <p class="text-xs text-amber-800">Only the fields checked below are visible to other clients.</p>
                </div>

                <form @submit.prevent="submitListing" class="space-y-4 rounded-md border p-4">
                    <fieldset class="space-y-2">
                        <legend class="text-sm font-medium">Fields to share</legend>
                        <label
                            v-for="(label, key) in shareableFields"
                            :key="key"
                            class="flex min-h-11 items-center gap-3 rounded-md border px-3 py-2 text-sm"
                            :class="listingForm.shared_fields.includes(key) ? 'border-primary bg-primary/5' : ''"
                        >
                            <input
                                type="checkbox"
                                class="h-5 w-5"
                                :checked="listingForm.shared_fields.includes(key)"
                                @change="toggleSharedField(key)"
                            />
                            <span>{{ label }}</span>
                        </label>
                    </fieldset>

                    <div class="flex flex-wrap gap-2">
                        <Button v-if="!cattle.for_sale" type="button" class="min-h-11 flex-1" :disabled="listingForm.processing" @click="listAnimal">
                            List this animal
                        </Button>
                        <template v-else>
                            <Button type="submit" class="min-h-11 flex-1" :disabled="listingForm.processing">Update shared fields</Button>
                            <Button type="button" variant="outline" class="min-h-11" :disabled="listingForm.processing" @click="unlistAnimal">
                                Remove listing
                            </Button>
                        </template>
                    </div>
                </form>
            </section>

            <!-- Health records -->
            <section class="space-y-4">
                <HeadingSmall title="Health records" description="Vaccinations, treatments, body condition and more." />

                <form v-if="canEdit" @submit.prevent="submitRecord" class="space-y-3 rounded-md border p-4">
                    <div class="grid gap-2">
                        <Label for="rtype">Type</Label>
                        <select id="rtype" v-model="recordForm.type" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="(label, value) in recordTypes" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>
                    <div v-if="recordForm.type === 'body_condition'" class="grid gap-2">
                        <Label for="bcs">Body condition score ({{ bcs.min }}–{{ bcs.max }})</Label>
                        <Input id="bcs" type="number" :min="bcs.min" :max="bcs.max" v-model.number="recordForm.bcs_score" />
                        <InputError :message="recordForm.errors.bcs_score" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="note">Note</Label>
                        <textarea
                            id="note"
                            v-model="recordForm.note"
                            rows="2"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        ></textarea>
                    </div>
                    <Button type="submit" size="sm" :disabled="recordForm.processing">Add record</Button>
                </form>

                <p v-if="records.length === 0" class="text-sm text-muted-foreground">No records yet.</p>
                <ul v-else class="divide-y rounded-md border">
                    <li v-for="record in records" :key="record.id" class="flex items-start justify-between gap-4 p-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                {{ record.type_label }}
                                <span v-if="record.bcs_score" class="text-muted-foreground">· BCS {{ record.bcs_score }}</span>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ record.recorded_at?.substring(0, 10) }} ·
                                {{ record.added_role === 'staff' ? 'Added by staff' : 'Added by ' + (record.added_by || 'client') }}
                                <span v-if="record.edited_by"> · edited by {{ record.edited_by }}</span>
                            </p>
                        </div>
                        <Button v-if="record.can_delete" variant="ghost" size="sm" @click="deleteRecord(record.id)">Delete</Button>
                    </li>
                </ul>
            </section>

            <!-- Photos -->
            <section class="space-y-4">
                <HeadingSmall title="Photos" description="Upload photos for the animal (for-sale listings, condition tracking)." />

                <form v-if="canEdit" @submit.prevent="submitMedia" class="space-y-3 rounded-md border p-4">
                    <input type="file" accept="image/*" @change="onFile" class="text-sm" />
                    <InputError :message="mediaForm.errors.photo" />
                    <Input v-model="mediaForm.caption" placeholder="Caption (optional)" />
                    <Button type="submit" size="sm" :disabled="mediaForm.processing || !mediaForm.photo">Upload photo</Button>
                </form>

                <p v-if="media.length === 0" class="text-sm text-muted-foreground">No photos yet.</p>
                <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <figure v-for="item in media" :key="item.id" class="space-y-1">
                        <img :src="item.url" :alt="item.caption || 'photo'" class="aspect-square w-full rounded-md object-cover" />
                        <figcaption class="text-xs text-muted-foreground">
                            {{ item.caption }}
                            <span v-if="item.visit_id" class="block">Visit #{{ item.visit_id }}</span>
                            <button v-if="item.can_delete" class="text-red-600 hover:underline" @click="deleteMedia(item.id)">Remove</button>
                        </figcaption>
                    </figure>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';

interface EntryData {
    id?: number;
    category: string;
    name: string;
    area: string | null;
    url: string | null;
    notes: string | null;
    active: boolean;
}

const props = defineProps<{
    entry?: EntryData;
    categories: { value: string; label: string }[];
    submitLabel: string;
}>();

const form = useForm({
    category: props.entry?.category ?? props.categories[0]?.value ?? 'other',
    name: props.entry?.name ?? '',
    area: props.entry?.area ?? '',
    url: props.entry?.url ?? '',
    notes: props.entry?.notes ?? '',
    active: props.entry?.active ?? true,
});

function submit(): void {
    if (props.entry?.id) {
        form.put(route('admin.directory.update', props.entry.id));
    } else {
        form.post(route('admin.directory.store'));
    }
}
</script>

<template>
    <form class="max-w-xl space-y-5" @submit.prevent="submit">
        <div class="space-y-2">
            <Label for="category">Category</Label>
            <select id="category" v-model="form.category" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                <option v-for="option in categories" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <InputError :message="form.errors.category" />
        </div>

        <div class="space-y-2">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" type="text" required />
            <InputError :message="form.errors.name" />
        </div>

        <div class="space-y-2">
            <Label for="area">Area</Label>
            <Input id="area" v-model="form.area" type="text" placeholder="e.g. Waco, TX" />
            <InputError :message="form.errors.area" />
        </div>

        <div class="space-y-2">
            <Label for="url">Website</Label>
            <Input id="url" v-model="form.url" type="url" placeholder="https://…" />
            <InputError :message="form.errors.url" />
        </div>

        <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <textarea id="notes" v-model="form.notes" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
            <InputError :message="form.errors.notes" />
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input v-model="form.active" type="checkbox" class="size-4 rounded border-input" />
            Show on the public directory
        </label>

        <div class="flex gap-3">
            <Button type="submit" :disabled="form.processing">{{ submitLabel }}</Button>
            <Button as-child type="button" variant="outline">
                <Link :href="route('admin.directory.index')">Cancel</Link>
            </Button>
        </div>
    </form>
</template>

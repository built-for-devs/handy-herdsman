<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

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
    a2a2: boolean | null;
    for_sale: boolean;
    notes: string | null;
}

const props = defineProps<{ cattle: Cattle; animalTypes: string[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Herd', href: '/cattle' },
    { title: props.cattle.reg_name || 'Animal', href: `/cattle/${props.cattle.id}` },
    { title: 'Edit', href: `/cattle/${props.cattle.id}/edit` },
];

const form = useForm({
    reg_name: props.cattle.reg_name ?? '',
    herd_number: props.cattle.herd_number ?? '',
    dob: props.cattle.dob ? props.cattle.dob.substring(0, 10) : '',
    breed: props.cattle.breed ?? '',
    animal_type: props.cattle.animal_type,
    has_calved: props.cattle.has_calved,
    status: props.cattle.status,
    a2a2: props.cattle.a2a2 ?? false,
    for_sale: props.cattle.for_sale,
    notes: props.cattle.notes ?? '',
});

const submit = () => form.put(route('cattle.update', props.cattle.id));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Edit animal" />

        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <HeadingSmall title="Edit animal" description="Marking an animal inactive stops all its pending reminders." />

            <form @submit.prevent="submit" class="space-y-5">
                <div class="grid gap-2">
                    <Label for="reg_name">Registered name</Label>
                    <Input id="reg_name" v-model="form.reg_name" />
                    <InputError :message="form.errors.reg_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="herd_number">Herd number</Label>
                    <Input id="herd_number" v-model="form.herd_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="animal_type">Type</Label>
                    <select
                        id="animal_type"
                        v-model="form.animal_type"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm capitalize"
                    >
                        <option v-for="type in animalTypes" :key="type" :value="type">{{ type }}</option>
                    </select>
                    <InputError :message="form.errors.animal_type" />
                </div>

                <div class="grid gap-2">
                    <Label for="status">Status</Label>
                    <select id="status" v-model="form.status" class="h-9 rounded-md border border-input bg-background px-3 text-sm capitalize">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>

                <div class="grid gap-2">
                    <Label for="dob">Date of birth</Label>
                    <Input id="dob" type="date" v-model="form.dob" />
                </div>

                <div class="grid gap-2">
                    <Label for="breed">Breed</Label>
                    <Input id="breed" v-model="form.breed" />
                </div>

                <label class="flex items-center gap-3"
                    ><input type="checkbox" v-model="form.has_calved" class="size-5" /><span class="text-sm">Has calved</span></label
                >
                <label class="flex items-center gap-3"
                    ><input type="checkbox" v-model="form.a2a2" class="size-5" /><span class="text-sm">A2/A2</span></label
                >
                <label class="flex items-center gap-3"
                    ><input type="checkbox" v-model="form.for_sale" class="size-5" /><span class="text-sm">Listed for sale</span></label
                >

                <div class="grid gap-2">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    ></textarea>
                </div>

                <Button type="submit" :disabled="form.processing">Save changes</Button>
            </form>
        </div>
    </AppLayout>
</template>

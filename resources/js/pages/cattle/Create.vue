<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

defineProps<{ animalTypes: string[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Herd', href: '/cattle' },
    { title: 'Add animal', href: '/cattle/create' },
];

const form = useForm({
    reg_name: '',
    herd_number: '',
    dob: '',
    breed: '',
    animal_type: 'cow',
    has_calved: false,
    a2a2: false,
    for_sale: false,
    notes: '',
});

const submit = () => form.post(route('cattle.store'));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Add animal" />

        <div class="mx-auto w-full max-w-2xl space-y-6 p-4">
            <HeadingSmall title="Add animal" description="Create a new cattle profile." />

            <form @submit.prevent="submit" class="space-y-5">
                <div class="grid gap-2">
                    <Label for="reg_name">Registered name</Label>
                    <Input id="reg_name" v-model="form.reg_name" />
                    <InputError :message="form.errors.reg_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="herd_number">Herd number</Label>
                    <Input id="herd_number" v-model="form.herd_number" />
                    <InputError :message="form.errors.herd_number" />
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
                    <Label for="dob">Date of birth</Label>
                    <Input id="dob" type="date" v-model="form.dob" />
                    <InputError :message="form.errors.dob" />
                </div>

                <div class="grid gap-2">
                    <Label for="breed">Breed</Label>
                    <Input id="breed" v-model="form.breed" />
                    <InputError :message="form.errors.breed" />
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
                    <InputError :message="form.errors.notes" />
                </div>

                <Button type="submit" :disabled="form.processing">Save animal</Button>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface Milestone {
    key: string;
    label: string;
    date: string;
}

interface DueDateResult {
    gestation_days: number;
    estimated_due_date: string;
    window_start: string;
    window_end: string;
    range_label: string;
    caveat: string;
    milestones: Milestone[];
}

const props = defineProps<{
    breeds: string[];
    input?: { breeding_date: string; breed: string | null; animal_type: string | null };
    result?: DueDateResult | null;
}>();

const form = useForm({
    breeding_date: props.input?.breeding_date ?? '',
    breed: props.input?.breed ?? '',
    animal_type: props.input?.animal_type ?? '',
});

const calculate = () => {
    form.post(route('calculators.due-date.calculate'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Due Date Calculator" />

    <PublicLayout
        title="Due Date Calculator"
        subtitle="Enter the breeding date and breed to estimate the calving window — plus the calving-prep milestones to watch for."
    >
        <Card>
            <CardContent class="pt-6">
                <form @submit.prevent="calculate" class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="breeding_date">Breeding date</Label>
                        <Input id="breeding_date" type="date" v-model="form.breeding_date" required />
                        <InputError :message="form.errors.breeding_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="breed">Breed</Label>
                        <input
                            id="breed"
                            list="breed-options"
                            v-model="form.breed"
                            placeholder="Unknown / crossbreed"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                        <datalist id="breed-options">
                            <option v-for="breed in breeds" :key="breed" :value="breed" />
                        </datalist>
                        <InputError :message="form.errors.breed" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="animal_type">Animal type (optional)</Label>
                        <select
                            id="animal_type"
                            v-model="form.animal_type"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="">Cow / not sure</option>
                            <option value="heifer">Heifer</option>
                            <option value="cow">Cow</option>
                        </select>
                        <InputError :message="form.errors.animal_type" />
                    </div>

                    <div class="sm:col-span-3">
                        <Button type="submit" :disabled="form.processing">Estimate due date</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div v-if="result" class="mt-6 space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>{{ result.range_label }}</CardTitle>
                    <CardDescription>
                        Based on an average gestation of {{ result.gestation_days }} days. Always treat this as an estimate — never a hard date.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        {{ result.caveat }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Calving-prep milestones</CardTitle>
                    <CardDescription>Dates to watch as the window approaches.</CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li v-for="milestone in result.milestones" :key="milestone.key" class="flex justify-between py-2 text-sm">
                            <span class="text-muted-foreground">{{ milestone.label }}</span>
                            <span class="font-medium">{{ milestone.date }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>

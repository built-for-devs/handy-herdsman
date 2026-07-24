<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarHeart, Info, Milestone } from 'lucide-vue-next';

interface MilestoneItem {
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
    milestones: MilestoneItem[];
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

    <PublicLayout>
        <!-- Hero -->
        <section class="border-b border-border bg-gradient-to-b from-muted/60 to-background">
            <div class="mx-auto max-w-3xl px-4 py-14 text-center sm:px-6 sm:py-20">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                >
                    <CalendarHeart class="h-3.5 w-3.5" />
                    Free calving tool
                </span>
                <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">Due Date Calculator</h1>
                <p class="mx-auto mt-3 max-w-xl text-base text-muted-foreground sm:text-lg">
                    Enter the breeding date and breed to estimate the calving window — plus the calving-prep milestones to watch for.
                </p>
            </div>
        </section>

        <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6">
            <!-- Input form -->
            <div class="rounded-xl border border-border bg-card p-6 shadow-sm sm:p-8">
                <form @submit.prevent="calculate" class="grid gap-5 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="breeding_date">Breeding date</Label>
                        <Input id="breeding_date" type="date" v-model="form.breeding_date" required class="h-11" />
                        <InputError :message="form.errors.breeding_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="breed">Breed</Label>
                        <input
                            id="breed"
                            list="breed-options"
                            v-model="form.breed"
                            placeholder="Unknown / crossbreed"
                            class="h-11 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
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
                            class="h-11 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option value="">Cow / not sure</option>
                            <option value="heifer">Heifer</option>
                            <option value="cow">Cow</option>
                        </select>
                        <InputError :message="form.errors.animal_type" />
                    </div>

                    <div class="sm:col-span-3">
                        <Button type="submit" size="lg" class="w-full sm:w-auto" :disabled="form.processing">
                            <CalendarHeart class="h-4 w-4" />
                            Estimate due date
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Result -->
            <div v-if="result" class="mt-8 space-y-6">
                <!-- Due window highlight -->
                <div class="rounded-xl border border-primary/30 bg-primary/5 p-6 text-center sm:p-8">
                    <p class="text-xs font-medium uppercase tracking-wide text-primary">Estimated calving window</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{{ result.range_label }}</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Based on an average gestation of {{ result.gestation_days }} days · always an estimate, never a hard date
                    </p>
                </div>

                <!-- Caveat -->
                <div
                    class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <Info class="mt-0.5 h-5 w-5 shrink-0" />
                    <p>{{ result.caveat }}</p>
                </div>

                <!-- Milestones -->
                <div class="rounded-xl border border-border bg-card p-6 shadow-sm sm:p-8">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Milestone class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight">Calving-prep milestones</h2>
                            <p class="mt-0.5 text-sm text-muted-foreground">Dates to watch as the window approaches.</p>
                        </div>
                    </div>
                    <ul class="mt-5 divide-y divide-border">
                        <li v-for="milestone in result.milestones" :key="milestone.key" class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span class="text-muted-foreground">{{ milestone.label }}</span>
                            <span class="font-semibold tabular-nums">{{ milestone.date }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, CalendarClock, CheckCircle2, Clock, Mail, Send } from 'lucide-vue-next';
import { computed } from 'vue';

interface AnimalTypeOption {
    value: string;
    label: string;
    can_be_bred: boolean;
}

interface Visit {
    key: string;
    title: string;
    when: string;
    window: string | null;
    explanation: string;
}

interface TimingResult {
    animal_type: string;
    timezone: string;
    visit_count: number;
    recommended_at: string;
    visits: Visit[];
}

const props = defineProps<{
    animalTypes: AnimalTypeOption[];
    input?: { visit1_at: string; animal_type: string };
    result?: TimingResult | null;
    ineligible?: string | null;
}>();

const page = usePage();
const flashStatus = computed(() => (page.props.flash as { status?: string } | undefined)?.status);

const form = useForm({
    visit1_at: props.input?.visit1_at ?? '',
    animal_type: props.input?.animal_type ?? 'cow',
});

const calculate = () => {
    form.post(route('calculators.ai-timing.calculate'), { preserveScroll: true });
};

const sendForm = useForm({
    visit1_at: props.input?.visit1_at ?? '',
    animal_type: props.input?.animal_type ?? 'cow',
    email: '',
    phone: '',
});

const sendResult = () => {
    sendForm
        .transform((data) => ({
            ...data,
            visit1_at: form.visit1_at,
            animal_type: form.animal_type,
        }))
        .post(route('calculators.ai-timing.send'), {
            preserveScroll: true,
            onSuccess: () => sendForm.reset('email', 'phone'),
        });
};
</script>

<template>
    <Head title="AI Timing Calculator" />

    <PublicLayout>
        <!-- Hero -->
        <section class="border-b border-border bg-gradient-to-b from-muted/60 to-background">
            <div class="mx-auto max-w-3xl px-4 py-14 text-center sm:px-6 sm:py-20">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                >
                    <CalendarClock class="h-3.5 w-3.5" />
                    Free timing tool
                </span>
                <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">AI Timing Calculator</h1>
                <p class="mx-auto mt-3 max-w-xl text-base text-muted-foreground sm:text-lg">
                    Pick your Visit&nbsp;1 date and animal type to see the full timed-AI schedule — and exactly what happens at each visit.
                </p>
            </div>
        </section>

        <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6">
            <!-- Input form -->
            <div class="rounded-xl border border-border bg-card p-6 shadow-sm sm:p-8">
                <form @submit.prevent="calculate" class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="visit1_at">Visit 1 date &amp; time</Label>
                        <Input id="visit1_at" type="datetime-local" v-model="form.visit1_at" required class="h-11" />
                        <InputError :message="form.errors.visit1_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="animal_type">Animal type</Label>
                        <select
                            id="animal_type"
                            v-model="form.animal_type"
                            class="h-11 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option v-for="option in animalTypes" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.animal_type" />
                    </div>

                    <div class="sm:col-span-2">
                        <Button type="submit" size="lg" class="w-full sm:w-auto" :disabled="form.processing">
                            <CalendarClock class="h-4 w-4" />
                            Calculate timing
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Ineligible notice -->
            <div
                v-if="ineligible"
                class="mt-6 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
            >
                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" />
                <p>{{ ineligible }}</p>
            </div>

            <!-- Result -->
            <div v-if="result" class="mt-8 space-y-6">
                <!-- Recommended breeding highlight -->
                <div class="rounded-xl border border-primary/30 bg-primary/5 p-6 text-center sm:p-8">
                    <p class="text-xs font-medium uppercase tracking-wide text-primary">Recommended breeding time</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{{ result.recommended_at }}</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ result.visit_count }}-visit plan for a {{ result.animal_type }} · all times in {{ result.timezone }}
                    </p>
                </div>

                <!-- Visit timeline -->
                <div>
                    <h2 class="mb-4 text-lg font-semibold tracking-tight">Your visit schedule</h2>
                    <ol class="relative space-y-4 border-l-2 border-border pl-6">
                        <li v-for="(visit, i) in result.visits" :key="visit.key" class="relative">
                            <span
                                class="absolute -left-[33px] flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground ring-4 ring-background"
                            >
                                {{ i + 1 }}
                            </span>
                            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="font-semibold">{{ visit.title }}</h3>
                                    <span
                                        v-if="visit.window"
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                    >
                                        <Clock class="h-3 w-3" />
                                        {{ visit.window }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm font-medium text-foreground">{{ visit.when }}</p>
                                <p class="mt-2 text-sm text-muted-foreground">{{ visit.explanation }}</p>
                            </div>
                        </li>
                    </ol>
                </div>

                <!-- Send plan -->
                <div class="rounded-xl border border-border bg-card p-6 shadow-sm sm:p-8">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <Mail class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight">Send this plan to yourself</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Get an informal proposal by email or text so you can plan and book with Jeff. This is not a confirmed booking.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="flashStatus"
                        class="mt-5 flex items-center gap-2 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800 dark:border-green-500/40 dark:bg-green-500/10 dark:text-green-300"
                    >
                        <CheckCircle2 class="h-4 w-4 shrink-0" />
                        {{ flashStatus }}
                    </div>

                    <form @submit.prevent="sendResult" class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input id="email" type="email" v-model="sendForm.email" placeholder="you@example.com" class="h-11" />
                            <InputError :message="sendForm.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phone">Mobile number</Label>
                            <Input id="phone" type="tel" v-model="sendForm.phone" placeholder="(555) 555-5555" class="h-11" />
                            <InputError :message="sendForm.errors.phone" />
                        </div>
                        <div class="sm:col-span-2">
                            <Button type="submit" size="lg" class="w-full sm:w-auto" :disabled="sendForm.processing">
                                <Send class="h-4 w-4" />
                                Send my plan
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

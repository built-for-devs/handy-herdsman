<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
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

    <PublicLayout
        title="AI Timing Calculator"
        subtitle="Pick your Visit 1 date and animal type to see the full timed-AI schedule — and what happens at each visit."
    >
        <Card>
            <CardContent class="pt-6">
                <form @submit.prevent="calculate" class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="visit1_at">Visit 1 date &amp; time</Label>
                        <Input id="visit1_at" type="datetime-local" v-model="form.visit1_at" required />
                        <InputError :message="form.errors.visit1_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="animal_type">Animal type</Label>
                        <select
                            id="animal_type"
                            v-model="form.animal_type"
                            class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option v-for="option in animalTypes" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.animal_type" />
                    </div>

                    <div class="sm:col-span-2">
                        <Button type="submit" :disabled="form.processing">Calculate timing</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div v-if="ineligible" class="mt-6 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            {{ ineligible }}
        </div>

        <div v-if="result" class="mt-6 space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>Your {{ result.visit_count }}-visit plan ({{ result.animal_type }})</CardTitle>
                    <CardDescription>
                        Recommended breeding time: <strong>{{ result.recommended_at }}</strong
                        >. All times shown in {{ result.timezone }}.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-for="visit in result.visits" :key="visit.key" class="rounded-md border p-4">
                        <h3 class="font-medium">{{ visit.title }}</h3>
                        <p class="mt-1 text-sm">{{ visit.when }}</p>
                        <p v-if="visit.window" class="mt-1 text-sm text-muted-foreground">Acceptable window: {{ visit.window }}</p>
                        <p class="mt-2 text-sm text-muted-foreground">{{ visit.explanation }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Send this plan to yourself</CardTitle>
                    <CardDescription>
                        Get an informal proposal by email or text so you can plan and book with Jeff. This is not a confirmed booking.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="flashStatus" class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                        {{ flashStatus }}
                    </div>
                    <form @submit.prevent="sendResult" class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input id="email" type="email" v-model="sendForm.email" placeholder="you@example.com" />
                            <InputError :message="sendForm.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phone">Mobile number</Label>
                            <Input id="phone" type="tel" v-model="sendForm.phone" placeholder="(555) 555-5555" />
                            <InputError :message="sendForm.errors.phone" />
                        </div>
                        <div class="sm:col-span-2">
                            <Button type="submit" :disabled="sendForm.processing">Send my plan</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </PublicLayout>
</template>

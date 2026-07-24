<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

defineProps<{ agreementVersion: string }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Get started', href: '/onboarding' }];

const form = useForm({
    contact_name: '',
    email: '',
    phone: '',
    address_line1: '',
    address_line2: '',
    city: '',
    state: '',
    postal_code: '',
    accept_agreement: false,
    accept_waiver: false,
});

const submit = () => form.post(route('onboarding.store'));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Get started" />

        <div class="mx-auto w-full max-w-xl p-4">
            <HeadingSmall title="Set up your account" description="Tell us how to reach you and accept the agreements to book." />

            <form @submit.prevent="submit" class="mt-6 space-y-6">
                <div class="grid gap-2">
                    <Label for="contact_name">Your name</Label>
                    <Input id="contact_name" v-model="form.contact_name" required autocomplete="name" />
                    <InputError :message="form.errors.contact_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email <span class="text-muted-foreground">(required)</span></Label>
                    <Input id="email" type="email" v-model="form.email" required autocomplete="email" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone <span class="text-muted-foreground">(required)</span></Label>
                    <Input id="phone" type="tel" v-model="form.phone" required autocomplete="tel" />
                    <InputError :message="form.errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="address_line1">Address</Label>
                    <Input id="address_line1" v-model="form.address_line1" placeholder="Street address" required />
                    <Input v-model="form.address_line2" placeholder="Apt, suite, etc. (optional)" />
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <Input v-model="form.city" placeholder="City" required />
                        <Input v-model="form.state" placeholder="State" required />
                        <Input v-model="form.postal_code" placeholder="ZIP" required />
                    </div>
                    <InputError :message="form.errors.address_line1" />
                </div>

                <div class="space-y-3 rounded-md border p-4">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" v-model="form.accept_agreement" class="mt-1 size-5" />
                        <span class="text-sm">I accept the {{ agreementVersion }}.</span>
                    </label>
                    <InputError :message="form.errors.accept_agreement" />

                    <label class="flex items-start gap-3">
                        <input type="checkbox" v-model="form.accept_waiver" class="mt-1 size-5" />
                        <span class="text-sm">I accept the liability waiver.</span>
                    </label>
                    <InputError :message="form.errors.accept_waiver" />
                </div>

                <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">Complete setup</Button>
            </form>
        </div>
    </AppLayout>
</template>

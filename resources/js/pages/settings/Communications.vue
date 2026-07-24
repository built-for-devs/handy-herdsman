<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface Category {
    number: number;
    key: string;
    label: string;
    default_channel: string;
    bypasses_quiet_hours: boolean;
}

interface Props {
    categories: Category[];
    channelPrefs: Record<string, string>;
    smsConsent: Record<string, boolean>;
    staffOverride: Record<string, string> | null;
    staffOverrideNote: string | null;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Communications', href: '/settings/communications' }];

const form = useForm<{ channel_prefs: Record<string, string>; sms_consent: Record<string, boolean> }>({
    channel_prefs: { ...props.channelPrefs },
    sms_consent: { ...props.smsConsent },
});

const submit = () => form.put(route('communications.update'), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Communication preferences" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Communication preferences" description="Choose how we reach you. SMS requires separate consent per category." />

                <div v-if="staffOverride" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    Staff has set a channel override for your account.
                    <span v-if="staffOverrideNote">Note: {{ staffOverrideNote }}</span>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div v-for="category in categories" :key="category.number" class="space-y-3 rounded-md border p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium">{{ category.label }}</p>
                            <span v-if="category.bypasses_quiet_hours" class="text-xs text-muted-foreground">sends anytime</span>
                        </div>

                        <div class="grid gap-2">
                            <label :for="`channel-${category.number}`" class="text-xs text-muted-foreground">Channel</label>
                            <select
                                :id="`channel-${category.number}`"
                                v-model="form.channel_prefs[category.number]"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="email">Email</option>
                                <option value="text">Text</option>
                                <option value="both">Both</option>
                            </select>
                        </div>

                        <label class="flex items-center gap-3">
                            <input type="checkbox" v-model="form.sms_consent[category.number]" class="size-5" />
                            <span class="text-sm">I consent to receive texts for this category</span>
                        </label>
                    </div>

                    <Button type="submit" :disabled="form.processing">Save preferences</Button>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

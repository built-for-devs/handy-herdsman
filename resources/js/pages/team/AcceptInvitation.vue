<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Props {
    invitation: {
        team_name: string;
        role: string;
        role_label: string;
        email: string;
        status: string;
        vet_scope: { profile?: boolean; record_types?: string[] } | null;
        token: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Invitation', href: '#' }];

const form = useForm({});

const accept = () => form.post(route('team-invitations.accept', props.invitation.token));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Team invitation" />

        <div class="mx-auto w-full max-w-md p-4">
            <HeadingSmall :title="`Join ${invitation.team_name}`" :description="`You've been invited as ${invitation.role_label}.`" />

            <div class="mt-6 space-y-4 rounded-md border p-4 text-sm">
                <p>
                    Invitation sent to <span class="font-medium">{{ invitation.email }}</span
                    >.
                </p>

                <div v-if="invitation.role === 'vet' && invitation.vet_scope">
                    <p class="font-medium">Read-only access to:</p>
                    <ul class="mt-1 list-inside list-disc text-muted-foreground">
                        <li v-if="invitation.vet_scope.profile">Animal profiles</li>
                        <li v-for="type in invitation.vet_scope.record_types ?? []" :key="type" class="capitalize">
                            {{ type.replace('_', ' ') }} records
                        </li>
                    </ul>
                </div>

                <InputError :message="form.errors.invitation" />

                <Button v-if="invitation.status === 'pending'" @click="accept" :disabled="form.processing" class="w-full"> Accept invitation </Button>
                <p v-else class="text-muted-foreground">This invitation is {{ invitation.status }}.</p>
            </div>
        </div>
    </AppLayout>
</template>

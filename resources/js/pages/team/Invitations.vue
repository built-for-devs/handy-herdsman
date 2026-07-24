<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

interface Invitation {
    id: number;
    email: string;
    role: string;
    role_label: string;
    status: string;
    vet_scope: { profile?: boolean; record_types?: string[] } | null;
}

defineProps<{ invitations: Invitation[]; recordTypes: string[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Team invitations', href: '/team/invitations' }];

const form = useForm<{
    email: string;
    role: string;
    vet_scope: { profile: boolean; record_types: string[] };
}>({
    email: '',
    role: 'member',
    vet_scope: { profile: true, record_types: [] },
});

const submit = () =>
    form.post(route('team-invitations.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });

const revoke = (id: number) => router.delete(route('team-invitations.destroy', id), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Team invitations" />

        <div class="mx-auto w-full max-w-2xl space-y-8 p-4">
            <div>
                <HeadingSmall title="Invite to your team" description="Invite your spouse (full access) or a vet (read-only)." />

                <form @submit.prevent="submit" class="mt-6 space-y-6">
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input id="email" type="email" v-model="form.email" required />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role">Role</Label>
                        <select id="role" v-model="form.role" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="member">Member — full access (spouse)</option>
                            <option value="vet">Veterinarian — read-only</option>
                        </select>
                        <InputError :message="form.errors.role" />
                    </div>

                    <div v-if="form.role === 'vet'" class="space-y-3 rounded-md border p-4">
                        <p class="text-sm font-medium">What can this vet see?</p>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" v-model="form.vet_scope.profile" class="size-5" />
                            <span class="text-sm">Animal profiles</span>
                        </label>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label v-for="type in recordTypes" :key="type" class="flex items-center gap-3">
                                <input type="checkbox" :value="type" v-model="form.vet_scope.record_types" class="size-5" />
                                <span class="text-sm capitalize">{{ type.replace('_', ' ') }} records</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.vet_scope" />
                    </div>

                    <Button type="submit" :disabled="form.processing">Send invitation</Button>
                </form>
            </div>

            <div class="space-y-3">
                <h3 class="text-sm font-medium">Invitations</h3>
                <p v-if="invitations.length === 0" class="text-sm text-muted-foreground">No invitations yet.</p>
                <ul class="divide-y rounded-md border">
                    <li v-for="invitation in invitations" :key="invitation.id" class="flex items-center justify-between gap-4 p-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ invitation.email }}</p>
                            <p class="text-xs text-muted-foreground">{{ invitation.role_label }} · {{ invitation.status }}</p>
                        </div>
                        <Button variant="ghost" size="sm" @click="revoke(invitation.id)">Revoke</Button>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

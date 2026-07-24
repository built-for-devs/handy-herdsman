<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface LedgerEntry {
    id: number;
    type: string;
    straws_delta: number;
    fee_type: string | null;
    fee_amount: string;
    occurred_at: string;
    location_to: string | null;
}

interface Lot {
    id: number;
    sire: string | null;
    breed: string | null;
    straws_count: number;
    source: string;
    location: string | null;
    with_ai: boolean;
    owned_by: string;
    team: { id: number; name: string } | null;
    ledger_entries: LedgerEntry[];
}

interface Props {
    lots: Lot[];
    teams: Array<{ id: number; name: string }>;
    fees: { receipt: number; storage: number; storage_free_year_one: boolean; storage_max_straws: number };
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Semen custody', href: '/admin/semen' }];

const form = useForm({
    team_id: '',
    source: 'client',
    sire: '',
    breed: '',
    location: '',
    with_ai: false,
    intake_shipping_address: '',
    tank_details: '',
    expected_arrival: '',
    source_farm: '',
    bull_info: '',
    source_contact: '',
    pay_to: '',
});

const submit = () => form.post('/admin/semen', { preserveScroll: true, onSuccess: () => form.reset() });

const receiveForm = useForm({ straws: 1, notes: '' });
const receive = (lotId: number) => receiveForm.post(`/admin/semen/${lotId}/receive`, { preserveScroll: true });
</script>

<template>
    <Head title="Semen custody" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <section class="rounded-xl border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border">
                <p>
                    Custody/storage only — we never sell straws, and straws never expire. Receipt fee
                    <span class="font-medium">${{ fees.receipt }}</span
                    >/shipment; storage <span class="font-medium">${{ fees.storage }}</span
                    >/yr
                    <span v-if="fees.storage_free_year_one">(free year 1 with AI)</span>
                    up to {{ fees.storage_max_straws }} straws.
                </p>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-4 font-semibold">New custody lot</h2>
                <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1">
                        <Label for="team_id">Client (team)</Label>
                        <select id="team_id" v-model="form.team_id" required class="rounded-md border p-2">
                            <option value="" disabled>Select client</option>
                            <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <InputError :message="form.errors.team_id" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="source">Sourcing</Label>
                        <select id="source" v-model="form.source" class="rounded-md border p-2">
                            <option value="client">Client-sourced</option>
                            <option value="jeff">Jeff-sourced</option>
                        </select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="sire">Sire</Label>
                        <Input id="sire" v-model="form.sire" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="breed">Breed</Label>
                        <Input id="breed" v-model="form.breed" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="location">Tank / canister / location</Label>
                        <Input id="location" v-model="form.location" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"> <input type="checkbox" v-model="form.with_ai" /> Paired with AI plan </label>

                    <template v-if="form.source === 'client'">
                        <div class="grid gap-1 sm:col-span-2">
                            <Label for="intake_shipping_address">Intake shipping address</Label>
                            <Input id="intake_shipping_address" v-model="form.intake_shipping_address" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="expected_arrival">Expected arrival (before protocol)</Label>
                            <Input id="expected_arrival" type="date" v-model="form.expected_arrival" />
                        </div>
                    </template>
                    <template v-else>
                        <div class="grid gap-1">
                            <Label for="source_farm">Farm / bank</Label>
                            <Input id="source_farm" v-model="form.source_farm" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="bull_info">Bull</Label>
                            <Input id="bull_info" v-model="form.bull_info" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="source_contact">Contact</Label>
                            <Input id="source_contact" v-model="form.source_contact" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="pay_to">Pay to</Label>
                            <Input id="pay_to" v-model="form.pay_to" />
                        </div>
                    </template>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <Button type="submit" :disabled="form.processing">Create lot</Button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <h2 class="mb-4 font-semibold">Custody lots</h2>
                <div v-for="lot in lots" :key="lot.id" class="mb-4 rounded-lg border p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="font-medium">{{ lot.sire || 'Unknown sire' }}</span>
                            <span class="text-muted-foreground"> · {{ lot.breed }} · {{ lot.team?.name }}</span>
                            <span class="ml-2 rounded bg-muted px-2 py-0.5 text-xs">{{ lot.source }}-sourced</span>
                            <span v-if="lot.with_ai" class="ml-1 rounded bg-muted px-2 py-0.5 text-xs">AI</span>
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">{{ lot.straws_count }}</span> straws · {{ lot.location }} · owned by {{ lot.owned_by }}
                        </div>
                    </div>
                    <form @submit.prevent="receive(lot.id)" class="mt-2 flex items-end gap-2">
                        <div class="grid gap-1">
                            <Label :for="'straws-' + lot.id">Receive straws</Label>
                            <Input :id="'straws-' + lot.id" type="number" min="1" v-model="receiveForm.straws" class="w-28" />
                        </div>
                        <Button type="submit" size="sm" :disabled="receiveForm.processing">Log shipment (${{ fees.receipt }})</Button>
                    </form>
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-muted-foreground">Ledger ({{ lot.ledger_entries.length }})</summary>
                        <ul class="mt-1 space-y-1">
                            <li v-for="e in lot.ledger_entries" :key="e.id">
                                {{ e.occurred_at }} · {{ e.type }} · {{ e.straws_delta > 0 ? '+' : '' }}{{ e.straws_delta }} straws
                                <span v-if="Number(e.fee_amount) > 0"> · ${{ e.fee_amount }} {{ e.fee_type }} fee</span>
                            </li>
                        </ul>
                    </details>
                </div>
                <p v-if="!lots.length" class="text-sm text-muted-foreground">No custody lots yet.</p>
            </section>
        </div>
    </AppLayout>
</template>

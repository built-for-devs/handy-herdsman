<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Row {
    id: number;
    animal: string;
    team: string | null;
    method: string;
    lab_requested: boolean;
    fee: number;
    state: string;
    result_recorded_at: string | null;
}

interface CattleOption {
    id: number;
    label: string;
    team: string | null;
}

interface Props {
    pending: Row[];
    recent: Row[];
    labFee: number;
    finalStates: string[];
    methods: string[];
    cattle: CattleOption[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Preg checks', href: '/admin/preg-checks' }];

const stateLabel: Record<string, string> = { open: 'Open (not bred)', bred: 'Bred', recheck: 'Recheck' };
const methodLabel: Record<string, string> = { blood: 'Blood draw', palpation: 'Palpation' };

// Jeff records the definitive lab result on a pending blood draw. Clients never
// see this screen (staff-only route).
const recordResult = (id: number, state: string) => {
    router.post(`/admin/preg-checks/${id}/result`, { state }, { preserveScroll: true });
};

// Jeff records a new preg check performed at a visit: blood → pending draw
// (optional lab confirmation), palpation → immediate/definitive final result.
const form = useForm({
    cattle_id: '' as number | '',
    method: 'blood',
    lab_requested: false,
    state: '' as string,
});

const isPalpation = computed(() => form.method === 'palpation');

const submit = () => {
    form.transform((data) => ({
        ...data,
        lab_requested: data.method === 'blood' ? data.lab_requested : false,
        state: data.method === 'palpation' ? data.state : null,
    })).post('/admin/preg-checks', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString() : '—');
</script>

<template>
    <Head title="Preg checks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
            <section class="flex flex-col gap-4 rounded-xl border p-4">
                <div>
                    <h2 class="font-semibold">Record a preg check</h2>
                    <p class="text-sm text-muted-foreground">
                        Blood draws start pending until the lab result comes back; palpation is definitive at the visit.
                    </p>
                </div>

                <form @submit.prevent="submit" class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="cattle_id">Animal</Label>
                        <select
                            id="cattle_id"
                            v-model="form.cattle_id"
                            class="h-11 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option value="" disabled>Select an animal…</option>
                            <option v-for="c in cattle" :key="c.id" :value="c.id">
                                {{ c.label }}<span v-if="c.team"> · {{ c.team }}</span>
                            </option>
                        </select>
                        <InputError :message="form.errors.cattle_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="method">Method</Label>
                        <select
                            id="method"
                            v-model="form.method"
                            class="h-11 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option v-for="m in methods" :key="m" :value="m">{{ methodLabel[m] ?? m }}</option>
                        </select>
                        <InputError :message="form.errors.method" />
                    </div>

                    <div v-if="isPalpation" class="grid gap-2">
                        <Label for="state">Result</Label>
                        <select
                            id="state"
                            v-model="form.state"
                            class="h-11 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option value="" disabled>Select a result…</option>
                            <option v-for="s in finalStates" :key="s" :value="s">{{ stateLabel[s] ?? s }}</option>
                        </select>
                        <InputError :message="form.errors.state" />
                    </div>

                    <label v-else class="flex items-center gap-2 self-end pb-2 text-sm">
                        <input type="checkbox" v-model="form.lab_requested" class="h-4 w-4 rounded border-input" />
                        Send to lab for confirmation (+${{ labFee }}/head)
                    </label>

                    <div class="sm:col-span-2">
                        <Button type="submit" :disabled="form.processing || form.cattle_id === ''">Record preg check</Button>
                    </div>
                </form>
            </section>

            <section class="flex flex-col gap-3 rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Awaiting lab result</h2>
                    <span class="text-sm text-muted-foreground">Lab confirmation fee ${{ labFee }}/head when opted in</span>
                </div>

                <p v-if="!pending.length" class="text-sm text-muted-foreground">No blood draws are awaiting a result.</p>

                <div v-for="row in pending" :key="row.id" class="flex flex-col gap-3 rounded-lg border p-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-medium">{{ row.animal }}</div>
                            <div class="text-sm text-muted-foreground">{{ row.team }} · {{ row.method }}</div>
                        </div>
                        <span v-if="row.lab_requested" class="rounded bg-muted px-2 py-1 text-xs">Lab requested · ${{ row.fee }}</span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="s in finalStates"
                            :key="s"
                            type="button"
                            variant="outline"
                            class="h-11 flex-1"
                            @click="recordResult(row.id, s)"
                        >
                            {{ stateLabel[s] ?? s }}
                        </Button>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-2 rounded-xl border p-4">
                <h2 class="font-semibold">Recent results</h2>
                <p v-if="!recent.length" class="text-sm text-muted-foreground">No results recorded yet.</p>
                <ul class="divide-y text-sm">
                    <li v-for="row in recent" :key="row.id" class="flex items-center justify-between py-2">
                        <span
                            >{{ row.animal }} <span class="text-muted-foreground">· {{ row.method }}</span></span
                        >
                        <span class="flex items-center gap-3">
                            <span class="font-medium">{{ stateLabel[row.state] ?? row.state }}</span>
                            <span class="text-muted-foreground">{{ fmt(row.result_recorded_at) }}</span>
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>

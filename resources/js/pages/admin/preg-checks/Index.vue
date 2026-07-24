<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';

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

interface Props {
    pending: Row[];
    recent: Row[];
    labFee: number;
    finalStates: string[];
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Preg checks', href: '/admin/preg-checks' }];

const stateLabel: Record<string, string> = { open: 'Open (not bred)', bred: 'Bred', recheck: 'Recheck' };

// Jeff records the definitive lab result on a pending blood draw. Clients never
// see this screen (staff-only route).
const recordResult = (id: number, state: string) => {
    router.post(`/admin/preg-checks/${id}/result`, { state }, { preserveScroll: true });
};

const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString() : '—');
</script>

<template>
    <Head title="Preg checks" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
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

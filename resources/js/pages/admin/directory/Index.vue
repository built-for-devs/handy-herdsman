<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';

interface Entry {
    id: number;
    category: string;
    name: string;
    area: string | null;
    url: string | null;
    notes: string | null;
    active: boolean;
    deleted: boolean;
}

defineProps<{
    entries: Entry[];
    categories: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Resource directory', href: '/admin/directory' }];

function archive(entry: Entry): void {
    if (confirm(`Archive "${entry.name}"?`)) {
        router.delete(route('admin.directory.destroy', entry.id), { preserveScroll: true });
    }
}

function restore(entry: Entry): void {
    router.put(route('admin.directory.restore', entry.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Resource directory" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Resource directory</h1>
                <Button as-child size="sm">
                    <Link :href="route('admin.directory.create')">Add resource</Link>
                </Button>
            </div>

            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="p-3">Name</th>
                            <th class="p-3">Category</th>
                            <th class="p-3">Area</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="entry in entries" :key="entry.id" :class="{ 'opacity-50': entry.deleted }">
                            <td class="p-3 font-medium">{{ entry.name }}</td>
                            <td class="p-3">{{ entry.category }}</td>
                            <td class="p-3 text-muted-foreground">{{ entry.area }}</td>
                            <td class="p-3">
                                <span v-if="entry.deleted" class="text-destructive">Archived</span>
                                <span v-else-if="entry.active">Active</span>
                                <span v-else class="text-muted-foreground">Hidden</span>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button v-if="!entry.deleted" as-child size="sm" variant="outline">
                                        <Link :href="route('admin.directory.edit', entry.id)">Edit</Link>
                                    </Button>
                                    <Button v-if="!entry.deleted" size="sm" variant="ghost" @click="archive(entry)"> Archive </Button>
                                    <Button v-else size="sm" variant="ghost" @click="restore(entry)">Restore</Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!entries.length">
                            <td colspan="5" class="p-6 text-center text-muted-foreground">No resources yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

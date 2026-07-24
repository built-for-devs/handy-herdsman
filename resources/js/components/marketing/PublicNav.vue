<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Menu } from 'lucide-vue-next';
import { ref } from 'vue';

interface NavLink {
    label: string;
    route: string;
}

const primaryLinks: NavLink[] = [
    { label: 'Home', route: 'home' },
    { label: 'About', route: 'about' },
    { label: 'Services', route: 'services' },
    { label: 'Pricing', route: 'pricing' },
];

const resourceLinks: NavLink[] = [
    { label: 'AI Timing Calculator', route: 'resources.ai-timing' },
    { label: 'Due Date Calculator', route: 'resources.due-date' },
    { label: 'Resource Directory', route: 'resources.directory' },
    { label: 'Cattle for Sale', route: 'resources.cattle-for-sale' },
];

const trailingLinks: NavLink[] = [
    { label: 'Blog', route: 'blog.index' },
    { label: 'Contact', route: 'contact' },
];

const page = usePage();
const mobileOpen = ref(false);

function isCurrent(name: string): boolean {
    return typeof route().current === 'function' && route().current(name);
}
</script>

<template>
    <header class="sticky top-0 z-40 w-full border-b border-border bg-background/95 backdrop-blur">
        <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
            <Link :href="route('home')" class="flex items-center gap-2 font-semibold tracking-tight">
                <span class="text-lg">Handy Herdsman</span>
            </Link>

            <!-- Desktop nav -->
            <div class="hidden items-center gap-1 md:flex">
                <Link
                    v-for="link in primaryLinks"
                    :key="link.route"
                    :href="route(link.route)"
                    class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    :class="{ 'text-foreground': isCurrent(link.route) }"
                >
                    {{ link.label }}
                </Link>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus:outline-none"
                        >
                            Resources
                            <ChevronDown class="size-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-56">
                        <DropdownMenuItem v-for="link in resourceLinks" :key="link.route" as-child>
                            <Link :href="route(link.route)">{{ link.label }}</Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Link
                    v-for="link in trailingLinks"
                    :key="link.route"
                    :href="route(link.route)"
                    class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    :class="{ 'text-foreground': isCurrent(link.route) }"
                >
                    {{ link.label }}
                </Link>

                <Button as-child size="sm" class="ml-2">
                    <Link :href="route('resources.ai-timing')">Get my AI timing</Link>
                </Button>
            </div>

            <!-- Mobile nav -->
            <div class="md:hidden">
                <Sheet v-model:open="mobileOpen">
                    <SheetTrigger as-child>
                        <Button variant="ghost" size="icon" aria-label="Open menu">
                            <Menu class="size-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="right" class="w-72 overflow-y-auto">
                        <SheetTitle class="mb-4 text-left">Menu</SheetTitle>
                        <div class="flex flex-col gap-1">
                            <Link
                                v-for="link in primaryLinks"
                                :key="link.route"
                                :href="route(link.route)"
                                class="rounded-md px-3 py-2 text-base font-medium hover:bg-accent"
                                @click="mobileOpen = false"
                            >
                                {{ link.label }}
                            </Link>

                            <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Resources</p>
                            <Link
                                v-for="link in resourceLinks"
                                :key="link.route"
                                :href="route(link.route)"
                                class="rounded-md px-3 py-2 text-base hover:bg-accent"
                                @click="mobileOpen = false"
                            >
                                {{ link.label }}
                            </Link>

                            <div class="my-2 h-px bg-border" />
                            <Link
                                v-for="link in trailingLinks"
                                :key="link.route"
                                :href="route(link.route)"
                                class="rounded-md px-3 py-2 text-base font-medium hover:bg-accent"
                                @click="mobileOpen = false"
                            >
                                {{ link.label }}
                            </Link>

                            <Button as-child class="mt-4">
                                <Link :href="route('resources.ai-timing')" @click="mobileOpen = false"> Get my AI timing </Link>
                            </Button>

                            <Link
                                v-if="!page.props.auth.user"
                                :href="route('login')"
                                class="mt-2 rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent"
                                @click="mobileOpen = false"
                            >
                                Client log in
                            </Link>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </nav>
    </header>
</template>

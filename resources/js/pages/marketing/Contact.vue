<script setup lang="ts">
import Seo from '@/components/marketing/Seo.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface Contact {
    text_number: string;
    email: string;
}

interface Address {
    street: string;
    city: string;
    state: string;
    zip: string;
}

defineProps<{
    contact: Contact;
    address: Address;
    serviceRangeMiles: number;
}>();
</script>

<template>
    <Seo
        title="Contact Handy Herdsman"
        description="Reach Jeff for cattle AI and ranch services around Valley Mills, TX. Text the on-call number for standing heat or a calving emergency."
    />
    <PublicLayout>
        <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Get in touch</h1>
            <p class="mt-4 text-muted-foreground">On-call work — standing heat or a calving emergency — is fastest by text.</p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Text (on-call)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <a :href="`sms:${contact.text_number}`" class="text-lg font-semibold text-primary">
                            {{ contact.text_number }}
                        </a>
                        <p class="mt-1 text-sm text-muted-foreground">Text now for time-sensitive breeding or calving.</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Email</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <a :href="`mailto:${contact.email}`" class="text-lg font-semibold text-primary">
                            {{ contact.email }}
                        </a>
                        <p class="mt-1 text-sm text-muted-foreground">For general questions and scheduling.</p>
                    </CardContent>
                </Card>
            </div>

            <div class="mt-8 rounded-lg border border-border bg-muted/30 p-6 text-sm">
                <p class="font-medium">Based in</p>
                <p class="mt-1 text-muted-foreground">{{ address.street }}, {{ address.city }}, {{ address.state }} {{ address.zip }}</p>
                <p class="mt-3 text-muted-foreground">
                    We serve about {{ serviceRangeMiles }} miles out; farther is case-by-case and a distance fee applies over
                    {{ serviceRangeMiles }} miles.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>

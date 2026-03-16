<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    priorityLeads: Array<{
        id: number;
        name: string;
        objective: string;
        fit_score: number;
        recommended_flow: string | null;
    }>;
    channels: Array<{ id: string; label: string }>;
    campaignTemplates: Array<{ id: string; name: string }>;
}>();

const title = 'Nova acao Megacombo';
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="[
            { title: 'Megacombo', href: route('dashboard') },
            { title: 'Nova acao Megacombo' },
        ]"
    >
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Nova ação</h1>
                <p class="text-muted-foreground mt-1">
                    Monte rapidamente uma campanha comercial com base em
                    prioridade e canal.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Templates de campanha</CardTitle>
                        <CardDescription
                            >Escolha um modelo para disparo manual
                            assistido.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="template in props.campaignTemplates"
                            :key="template.id"
                            class="flex items-center justify-between rounded-lg border p-3"
                        >
                            <p class="font-medium">{{ template.name }}</p>
                            <Button size="sm" variant="outline"
                                >Selecionar</Button
                            >
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Canais disponiveis</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div
                            v-for="channel in props.channels"
                            :key="channel.id"
                            class="rounded-lg border p-3 text-sm"
                        >
                            {{ channel.label }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Leads prioritarios</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div
                        v-if="props.priorityLeads.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        Nenhum lead priorizado encontrado.
                    </div>
                    <div
                        v-for="lead in props.priorityLeads"
                        :key="lead.id"
                        class="rounded-lg border p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium">{{ lead.name }}</p>
                            <Badge>{{ lead.fit_score }}</Badge>
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ lead.objective }} |
                            {{ lead.recommended_flow ?? '-' }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

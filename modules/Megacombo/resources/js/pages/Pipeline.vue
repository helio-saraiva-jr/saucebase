<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    leads: Array<{
        id: number;
        name: string;
        objective: string;
        fit_score: number;
        recommended_flow: string | null;
        created_at: string;
    }>;
    flowSummary: Record<string, number>;
}>();

const title = 'Pipeline Megacombo';
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="[
            { title: 'Megacombo', href: route('dashboard') },
            { title: 'Pipeline Megacombo' },
        ]"
    >
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Pipeline</h1>
                <p class="text-muted-foreground mt-1">
                    Visao consolidada de fluxo recomendado e leads recentes.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Resumo por fluxo</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div
                            v-for="(count, flow) in props.flowSummary"
                            :key="flow"
                            class="rounded-lg border p-3"
                        >
                            <p class="text-muted-foreground text-xs">
                                {{ flow }}
                            </p>
                            <p class="text-2xl font-bold">{{ count }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Leads no pipeline</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div
                        v-if="props.leads.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        Nenhum lead no pipeline.
                    </div>
                    <div
                        v-for="lead in props.leads"
                        :key="lead.id"
                        class="rounded-lg border p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium">{{ lead.name }}</p>
                            <Badge variant="secondary">{{
                                lead.fit_score
                            }}</Badge>
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ lead.objective }} |
                            {{ lead.recommended_flow ?? '-' }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ lead.created_at }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

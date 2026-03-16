<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    kpis: {
        activeQuotas: number;
        contemplatedQuotas: number;
        openLeads: number;
    };
    contingencyQuota: {
        id: number;
        group_code: string;
        quota_number: string;
        credit_value: number;
        contemplated_at: string;
    } | null;
    recentAlerts: Array<{
        id: number;
        event_type: string;
        status: string;
        created_at: string;
        group_code: string | null;
        quota_number: string | null;
    }>;
}>();

const title = 'Operacao Megacombo';

const alertLabel: Record<string, string> = {
    contemplated_detected: 'Contemplacao detectada',
    quota_monitored: 'Cota monitorada',
};

const money = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 2,
});
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="[
            { title: 'Megacombo', href: route('dashboard') },
            { title: 'Operacao Megacombo' },
        ]"
    >
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Operacao</h1>
                <p class="text-muted-foreground mt-1">
                    Painel de tratativa imediata para cotas e leads de maior
                    prioridade.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Cotas ativas</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ props.kpis.activeQuotas }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Cotas contempladas</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ props.kpis.contemplatedQuotas }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Leads em aberto</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ props.kpis.openLeads }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Cota em destaque para abordagem</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="props.contingencyQuota"
                        class="rounded-lg border p-4"
                    >
                        <p class="font-semibold">
                            Grupo {{ props.contingencyQuota.group_code }} | Cota
                            {{ props.contingencyQuota.quota_number }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Credito:
                            {{
                                money.format(
                                    props.contingencyQuota.credit_value,
                                )
                            }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Contemplacao:
                            {{ props.contingencyQuota.contemplated_at }}
                        </p>
                    </div>
                    <p v-else class="text-muted-foreground text-sm">
                        Nenhuma cota contemplada no momento.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Ultimos alertas operacionais</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div
                        v-if="props.recentAlerts.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        Sem alertas recentes.
                    </div>
                    <div
                        v-for="alert in props.recentAlerts"
                        :key="alert.id"
                        class="rounded-lg border p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium">
                                {{
                                    alertLabel[alert.event_type] ??
                                    alert.event_type
                                }}
                            </p>
                            <Badge variant="outline">{{ alert.status }}</Badge>
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Grupo {{ alert.group_code ?? '-' }} | Cota
                            {{ alert.quota_number ?? '-' }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ alert.created_at }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

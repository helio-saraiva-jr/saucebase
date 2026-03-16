<script setup lang="ts">
import Alert from '@/components/ui/alert/Alert.vue';
import AlertDescription from '@/components/ui/alert/AlertDescription.vue';
import AlertTitle from '@/components/ui/alert/AlertTitle.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    kpis: {
        queuedAlerts: number;
        processedAlerts: number;
        contemplatedQuotas: number;
        monthlyQueueDemand: number;
    };
    priorityAlert: {
        id: number;
        event_type: string;
        status: string;
        created_at: string;
        group_code: string | null;
        quota_number: string | null;
        credit_value: number | null;
        client_name: string | null;
        payload: string | null;
    } | null;
    alerts: Array<{
        id: number;
        event_type: string;
        status: string;
        created_at: string;
        group_code: string | null;
        quota_number: string | null;
        credit_value: number | null;
        client_name: string | null;
        payload: string | null;
    }>;
    workflow: Array<{
        title: string;
        description: string;
    }>;
}>();

const title = 'Pos-venda e Alertas';

const money = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 2,
});

const alertLabel = (eventType: string) => {
    if (eventType === 'contemplated_detected') {
        return 'Cliente contemplado';
    }

    if (eventType === 'quota_monitored') {
        return 'Assembleia monitorada';
    }

    return eventType;
};
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="[
            { title: 'Megacombo', href: route('dashboard') },
            { title: title },
        ]"
    >
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">
                    Dashboard de Gestao de Pos-venda
                </h1>
                <p class="text-muted-foreground mt-1">
                    Central de alertas do representante para operacao de carta
                    contemplada e reinvestimento com alto lucro.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Alertas pendentes</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ props.kpis.queuedAlerts }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Alertas processados</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ props.kpis.processedAlerts }}
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
                        <CardTitle class="text-base"
                            >Demanda fila de espera</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-bold">
                            {{
                                money.format(props.kpis.monthlyQueueDemand)
                            }}/mes
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Alert v-if="props.priorityAlert" class="border-emerald-500/40">
                <AlertTitle>O Pulo do Gato: contemplacao detectada</AlertTitle>
                <AlertDescription>
                    Grupo {{ props.priorityAlert.group_code ?? '-' }}, cota
                    {{ props.priorityAlert.quota_number ?? '-' }}
                    <span v-if="props.priorityAlert.client_name"
                        >de {{ props.priorityAlert.client_name }}</span
                    >. Inicie a tratativa com mesa Mais Valor imediatamente para
                    preservar margem.
                </AlertDescription>
            </Alert>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
                <Card class="xl:col-span-3">
                    <CardHeader>
                        <CardTitle>Workflow operacional automatizado</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="(step, index) in props.workflow"
                            :key="step.title"
                            class="rounded-lg border p-3"
                        >
                            <p class="font-medium">
                                {{ index + 1 }}. {{ step.title }}
                            </p>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {{ step.description }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Portal do cliente final</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-muted-foreground text-sm">
                            Acesso exclusivo do perfil cliente para
                            acompanhamento da evolucao patrimonial nos 216
                            meses.
                        </p>
                        <p class="rounded-md border px-3 py-2 text-sm">
                            Oriente o cliente a acessar Cliente -> Portal no
                            menu da conta.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Central de alertas de contemplacao</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div
                        v-if="props.alerts.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        Nenhum alerta registrado.
                    </div>
                    <div
                        v-for="alert in props.alerts"
                        :key="alert.id"
                        class="rounded-lg border p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium">
                                {{ alertLabel(alert.event_type) }}
                            </p>
                            <Badge variant="outline">{{ alert.status }}</Badge>
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Grupo {{ alert.group_code ?? '-' }} | Cota
                            {{ alert.quota_number ?? '-' }}
                            <span v-if="alert.client_name"
                                >| Cliente {{ alert.client_name }}</span
                            >
                        </p>
                        <p
                            v-if="alert.credit_value"
                            class="text-muted-foreground text-xs"
                        >
                            Credito {{ money.format(alert.credit_value) }}
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

<script setup lang="ts">
import Alert from '@/components/ui/alert/Alert.vue';
import AlertDescription from '@/components/ui/alert/AlertDescription.vue';
import AlertTitle from '@/components/ui/alert/AlertTitle.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import IconArrowRight from '~icons/heroicons/arrow-right';
import IconBellAlert from '~icons/heroicons/bell-alert';
import IconCalculator from '~icons/heroicons/calculator';
import IconChart from '~icons/heroicons/chart-bar';
import IconChartPie from '~icons/heroicons/chart-pie';
import IconCheckBadge from '~icons/heroicons/check-badge';
import IconCurrencyDollar from '~icons/heroicons/currency-dollar';
import IconInformationCircle from '~icons/heroicons/information-circle';
import IconMegaphone from '~icons/heroicons/megaphone';
import IconPlayCircle from '~icons/heroicons/play-circle';
import IconUsers from '~icons/heroicons/user-group';

interface FinancialScenario {
    creditValue: number;
    installmentValue: number;
    remainingInstallments: number;
    discountRateMonthly: number;
}

interface ProbabilityScenario {
    openMarket: {
        participants: number;
        durationMonths: number;
        drawsPerMonth: number;
    };
    exclusiveGroup: {
        participants: number;
        durationMonths: number;
        drawsPerMonth: number;
    };
}

const props = defineProps<{
    defaultFinancialScenario: FinancialScenario;
    defaultProbabilityScenario: ProbabilityScenario;
    kpis: {
        leads: number;
        quotas: number;
        contemplatedQuotas: number;
        activeQuotas: number;
        avgFitScore: number;
        totalCredit: number;
    };
    periodDays: number;
    periodOptions: number[];
    recentLeads: Array<{
        id: number;
        name: string;
        objective: string;
        fit_score: number;
        recommended_flow: string | null;
    }>;
    recentAlerts: Array<{
        id: number;
        event_type: string;
        status: string;
        created_at: string;
        group_code: string | null;
        quota_number: string | null;
    }>;
}>();

const title = 'Megacombo';

const financial = ref(props.defaultFinancialScenario);
const probability = ref(props.defaultProbabilityScenario);

const financialPayload = computed(() => ({
    credit_value: financial.value.creditValue,
    installment_value: financial.value.installmentValue,
    remaining_installments: financial.value.remainingInstallments,
    discount_rate_monthly: financial.value.discountRateMonthly,
}));

const openMarketChance = computed(() => {
    const total =
        probability.value.openMarket.durationMonths *
        probability.value.openMarket.drawsPerMonth;
    return ((total / probability.value.openMarket.participants) * 100).toFixed(
        2,
    );
});

const exclusiveChance = computed(() => {
    const total =
        probability.value.exclusiveGroup.durationMonths *
        probability.value.exclusiveGroup.drawsPerMonth;
    return (
        (total / probability.value.exclusiveGroup.participants) *
        100
    ).toFixed(2);
});

const hasContemplated = computed(() => props.kpis.contemplatedQuotas > 0);
const hasRecentActivity = computed(
    () => props.recentAlerts.length > 0 || props.recentLeads.length > 0,
);

const activeRate = computed(() => {
    if (props.kpis.quotas === 0) {
        return '0.0';
    }

    return ((props.kpis.activeQuotas / props.kpis.quotas) * 100).toFixed(1);
});

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 2,
});

const alertLabel = (eventType: string) => {
    switch (eventType) {
        case 'contemplated_detected':
            return 'Contemplação detectada';
        case 'quota_monitored':
            return 'Cota monitorada';
        default:
            return eventType;
    }
};

const periodLabel = computed(() => `Ultimos ${props.periodDays} dias`);
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="[{ title: title }]">
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        {{ title }}
                    </h1>
                    <p class="text-muted-foreground mt-1">
                        Inteligencia comercial para operacao de consorcios com
                        simulacao financeira e probabilidade de grupos.
                    </p>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ periodLabel }}
                    </p>
                </div>
                <div class="mt-3 flex gap-2 sm:mt-0">
                    <Button as-child variant="secondary">
                        <Link :href="route('megacombo.financial-calculator')">
                            <IconCalculator class="size-4" />
                            Calculadora
                        </Link>
                    </Button>
                    <Button as-child variant="secondary">
                        <Link :href="route('megacombo.probability-engine')">
                            <IconChartPie class="size-4" />
                            Probabilidades
                        </Link>
                    </Button>
                    <Button as-child variant="secondary">
                        <Link :href="route('megacombo.post-sale-center')">
                            <IconBellAlert class="size-4" />
                            Pos-venda
                        </Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="route('megacombo.operation')">
                            <IconPlayCircle class="size-4" />
                            Operacao
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('megacombo.actions.create')">
                            <IconMegaphone class="size-4" />
                            Nova ação
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-muted-foreground text-sm">Periodo:</span>
                <Button
                    v-for="option in props.periodOptions"
                    :key="`period-${option}`"
                    as-child
                    :variant="
                        props.periodDays === option ? 'default' : 'outline'
                    "
                    size="sm"
                >
                    <Link
                        :href="route('dashboard', { period: option })"
                        preserve-scroll
                    >
                        {{ option }} dias
                    </Link>
                </Button>
            </div>

            <Alert
                v-if="hasContemplated"
                class="text-foreground border-emerald-500/40"
            >
                <IconInformationCircle class="size-4" />
                <AlertTitle>Oportunidade ativa no funil</AlertTitle>
                <AlertDescription>
                    {{ props.kpis.contemplatedQuotas }} cota(s) contemplada(s)
                    aguardando tratativa comercial. Priorize contato em até 2
                    horas para maximizar conversão.
                </AlertDescription>
            </Alert>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-base font-medium"
                            >Leads cadastrados</CardTitle
                        >
                        <IconUsers class="text-muted-foreground size-6" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">
                            {{ props.kpis.leads }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-base font-medium"
                            >Cotas monitoradas</CardTitle
                        >
                        <IconChart class="text-muted-foreground size-6" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">
                            {{ props.kpis.quotas }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-base font-medium"
                            >Cotas contempladas</CardTitle
                        >
                        <IconCheckBadge class="text-muted-foreground size-6" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">
                            {{ props.kpis.contemplatedQuotas }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-base font-medium"
                            >Taxa mensal simulada</CardTitle
                        >
                        <IconCurrencyDollar
                            class="text-muted-foreground size-6"
                        />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold">
                            {{
                                (financial.discountRateMonthly * 100).toFixed(
                                    2,
                                )
                            }}%
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Faixa alvo recomendada: 0,60% a 0,85% a.m.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Taxa de cotas ativas</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ activeRate }}%</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Score médio dos leads</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">
                            {{ props.kpis.avgFitScore }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Crédito total monitorado</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">
                            {{
                                currencyFormatter.format(props.kpis.totalCredit)
                            }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-7">
                <div class="lg:col-span-4">
                    <Card class="h-full">
                        <CardHeader>
                            <CardTitle>Simulacoes base do modulo</CardTitle>
                            <CardDescription>
                                Valores iniciais para o motor de precificacao e
                                comparativo de probabilidade.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="rounded-lg border p-4">
                                <p class="text-sm font-medium">
                                    Probabilidade acumulada
                                </p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-md border p-3">
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            Mar aberto
                                        </p>
                                        <p class="text-xl font-semibold">
                                            {{ openMarketChance }}%
                                        </p>
                                    </div>
                                    <div class="rounded-md border p-3">
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            Grupo exclusivo
                                        </p>
                                        <p class="text-xl font-semibold">
                                            {{ exclusiveChance }}%
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border p-4">
                                <p class="text-sm font-medium">
                                    Payload financeiro padrão
                                </p>
                                <pre
                                    class="text-muted-foreground bg-muted mt-2 overflow-auto rounded p-3 text-xs"
                                    >{{ financialPayload }}</pre
                                >
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="lg:col-span-3">
                    <Card class="h-full">
                        <CardHeader>
                            <CardTitle>Atividade recente</CardTitle>
                            <CardDescription
                                >Alertas operacionais e ultimos leads
                                qualificados no periodo
                                selecionado.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-if="!hasRecentActivity"
                                    class="text-muted-foreground rounded-lg border border-dashed p-4 text-sm"
                                >
                                    Nenhuma atividade registrada neste periodo.
                                </div>

                                <div
                                    v-for="alert in props.recentAlerts"
                                    :key="`alert-${alert.id}`"
                                    class="rounded-lg border p-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <p class="font-medium">
                                            {{ alertLabel(alert.event_type) }}
                                        </p>
                                        <Badge variant="outline">{{
                                            alert.status
                                        }}</Badge>
                                    </div>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs"
                                    >
                                        Grupo {{ alert.group_code ?? '-' }} |
                                        Cota {{ alert.quota_number ?? '-' }}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs"
                                    >
                                        {{ alert.created_at }}
                                    </p>
                                </div>

                                <div
                                    v-for="lead in props.recentLeads"
                                    :key="`lead-${lead.id}`"
                                    class="rounded-lg border p-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <p class="font-medium">
                                            Lead: {{ lead.name }}
                                        </p>
                                        <Badge variant="secondary">{{
                                            lead.fit_score
                                        }}</Badge>
                                    </div>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs"
                                    >
                                        {{ lead.objective }} |
                                        {{ lead.recommended_flow ?? '-' }}
                                    </p>
                                </div>

                                <Button
                                    as-child
                                    class="w-full"
                                    variant="outline"
                                >
                                    <Link :href="route('megacombo.pipeline')">
                                        Ver pipeline completo
                                        <IconArrowRight class="size-4" />
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

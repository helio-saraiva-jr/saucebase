<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    quota: {
        id: number | null;
        group_code: string | null;
        quota_number: string | null;
        credit_value: number;
        installment_value: number;
        remaining_installments: number;
        status: string;
        client_name: string;
    };
    inccMonthlyRate: number;
    evolution: Array<{
        label: string;
        months: number;
        paid_total: number;
        corrected_credit: number;
        patrimony_gap: number;
    }>;
}>();

const title = 'Portal do Cliente';

const money = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 2,
});

const maxValue = computed(() => {
    const values = props.evolution.flatMap((point) => [
        point.paid_total,
        point.corrected_credit,
    ]);
    return Math.max(...values, 1);
});

const chartWidth = 760;
const chartHeight = 260;
const chartPadding = 26;

const toChartPoint = (index: number, value: number, total: number): string => {
    if (total <= 1) {
        const x = chartPadding;
        const y = chartHeight - chartPadding;
        return `${x},${y}`;
    }

    const xStep = (chartWidth - chartPadding * 2) / (total - 1);
    const x = chartPadding + index * xStep;
    const normalized = value / maxValue.value;
    const y =
        chartHeight -
        chartPadding -
        normalized * (chartHeight - chartPadding * 2);

    return `${x},${y}`;
};

const paidPolyline = computed(() =>
    props.evolution
        .map((point, index) =>
            toChartPoint(index, point.paid_total, props.evolution.length),
        )
        .join(' '),
);

const correctedPolyline = computed(() =>
    props.evolution
        .map((point, index) =>
            toChartPoint(index, point.corrected_credit, props.evolution.length),
        )
        .join(' '),
);

const lastPoint = computed(
    () => props.evolution[props.evolution.length - 1] ?? null,
);
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
                    Portal do Cliente Final
                </h1>
                <p class="text-muted-foreground mt-1">
                    Olá {{ props.quota.client_name }}. Acompanhe a evolucao do
                    seu patrimonio com pagamento mensal e correcao do credito
                    pelo INCC.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Grupo / Cota</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-bold">
                            {{ props.quota.group_code ?? '-' }} /
                            {{ props.quota.quota_number ?? '-' }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Credito inicial</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-bold">
                            {{ money.format(props.quota.credit_value) }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Parcela mensal</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-bold">
                            {{ money.format(props.quota.installment_value) }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >INCC de referencia</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-bold">
                            {{ (props.inccMonthlyRate * 100).toFixed(2) }}% a.m.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle
                        >Evolucao de patrimonio (pagamento x correcao do
                        credito)</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <div class="w-full overflow-x-auto">
                        <svg
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            class="w-full min-w-[680px]"
                        >
                            <line
                                :x1="chartPadding"
                                :y1="chartHeight - chartPadding"
                                :x2="chartWidth - chartPadding"
                                :y2="chartHeight - chartPadding"
                                stroke="hsl(var(--muted-foreground))"
                                stroke-opacity="0.35"
                            />
                            <line
                                :x1="chartPadding"
                                :y1="chartPadding"
                                :x2="chartPadding"
                                :y2="chartHeight - chartPadding"
                                stroke="hsl(var(--muted-foreground))"
                                stroke-opacity="0.35"
                            />

                            <polyline
                                :points="paidPolyline"
                                fill="none"
                                stroke="hsl(215 70% 55%)"
                                stroke-width="3"
                                stroke-linecap="round"
                            />
                            <polyline
                                :points="correctedPolyline"
                                fill="none"
                                stroke="hsl(148 65% 45%)"
                                stroke-width="3"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3 text-sm">
                        <div
                            class="inline-flex items-center gap-2 rounded-md border px-3 py-1"
                        >
                            <span
                                class="inline-block h-2 w-2 rounded-full"
                                style="background-color: hsl(215 70% 55%)"
                            />
                            Pagamento acumulado
                        </div>
                        <div
                            class="inline-flex items-center gap-2 rounded-md border px-3 py-1"
                        >
                            <span
                                class="inline-block h-2 w-2 rounded-full"
                                style="background-color: hsl(148 65% 45%)"
                            />
                            Credito corrigido por INCC
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="lastPoint">
                <CardHeader>
                    <CardTitle>Resumo no horizonte atual</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-muted-foreground text-xs">
                            Pago acumulado
                        </p>
                        <p class="text-2xl font-bold">
                            {{ money.format(lastPoint.paid_total) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs">
                            Credito corrigido
                        </p>
                        <p class="text-2xl font-bold">
                            {{ money.format(lastPoint.corrected_credit) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs">
                            Patrimonio potencial
                        </p>
                        <p class="text-2xl font-bold">
                            {{ money.format(lastPoint.patrimony_gap) }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

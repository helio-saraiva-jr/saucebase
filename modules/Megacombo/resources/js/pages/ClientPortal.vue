<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    portfolio: {
        client_name: string;
        quota_count: number;
        assemblies_count: number;
        quota_credit_value: number;
        quota_installment_value: number;
        quota_term_months: number;
        credit_value_total: number;
        installment_value_total: number;
    };
    quotas: Array<{
        id: number;
        group_code: string | null;
        quota_number: string | null;
        credit_value: number;
        installment_value: number;
        remaining_installments: number;
        status: string;
        client_name: string;
    }>;
    acquireMoreAction: {
        label: string;
        url: string | null;
        enabled: boolean;
    };
    inccMonthlyRate: number;
    selicAnnualRate: number;
    selicSource: {
        provider: string;
        series: string;
        reference_date: string | null;
        is_fallback: boolean;
    };
    scenario: {
        active: string;
        options: Array<{
            key: string;
            label: string;
            incc_monthly_rate: number;
            projected_agio_rate: number;
        }>;
    };
    projectedAgioRate: number;
    evolution: Array<{
        label: string;
        months: number;
        paid_total: number;
        corrected_credit: number;
        patrimony_gap: number;
    }>;
    turningPoint: {
        timeline: Array<{
            label: string;
            year: number;
            months: number;
            credit_value: number;
            outstanding_debt: number;
            paid_total: number;
            total_obligation: number;
            turning_edge: number;
            is_turning_point: boolean;
        }>;
        summary: {
            found: boolean;
            year: number | null;
            months: number | null;
            credit_value: number | null;
            total_obligation: number | null;
            turning_edge: number | null;
            latest_margin_percent: number;
        };
    };
    shadowPortfolio: Array<{
        label: string;
        months: number;
        consorcio_value: number;
        selic_value: number;
        edge_value: number;
    }>;
    shadowSummary: {
        generated_at: string;
        horizon_months: number;
        consorcio_value: number;
        selic_value: number;
        edge_value: number;
        edge_percent: number;
    };
    gamification: {
        level: string;
        winning_streak_years: number;
        daily_growth_consorcio: number;
        daily_growth_selic: number;
    };
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

const shadowMaxValue = computed(() => {
    const values = props.shadowPortfolio.flatMap((point) => [
        point.consorcio_value,
        point.selic_value,
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

const shadowConsorcioPolyline = computed(() =>
    props.shadowPortfolio
        .map((point, index) => {
            const total = props.shadowPortfolio.length;
            if (total <= 1) {
                return `${chartPadding},${chartHeight - chartPadding}`;
            }

            const xStep = (chartWidth - chartPadding * 2) / (total - 1);
            const x = chartPadding + index * xStep;
            const normalized = point.consorcio_value / shadowMaxValue.value;
            const y =
                chartHeight -
                chartPadding -
                normalized * (chartHeight - chartPadding * 2);

            return `${x},${y}`;
        })
        .join(' '),
);

const shadowSelicPolyline = computed(() =>
    props.shadowPortfolio
        .map((point, index) => {
            const total = props.shadowPortfolio.length;
            if (total <= 1) {
                return `${chartPadding},${chartHeight - chartPadding}`;
            }

            const xStep = (chartWidth - chartPadding * 2) / (total - 1);
            const x = chartPadding + index * xStep;
            const normalized = point.selic_value / shadowMaxValue.value;
            const y =
                chartHeight -
                chartPadding -
                normalized * (chartHeight - chartPadding * 2);

            return `${x},${y}`;
        })
        .join(' '),
);

const lastPoint = computed(
    () => props.evolution[props.evolution.length - 1] ?? null,
);

const generatedAtLabel = computed(() => {
    const date = new Date(props.shadowSummary.generated_at);
    return Number.isNaN(date.getTime())
        ? '-'
        : date.toLocaleString('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          });
});

const edgeIsPositive = computed(() => props.shadowSummary.edge_value >= 0);

const selicReferenceLabel = computed(
    () => props.selicSource.reference_date ?? '-',
);

const selectedTurningPointIndex = ref(
    Math.max(props.turningPoint.timeline.length - 1, 0),
);
const isTurningTimelinePlaying = ref(false);
const turningTimelineIntervalMs = 1400;
let turningTimelineTimer: number | null = null;

const selectedTurningPoint = computed(
    () =>
        props.turningPoint.timeline[
            Math.min(
                Math.max(selectedTurningPointIndex.value, 0),
                Math.max(props.turningPoint.timeline.length - 1, 0),
            )
        ] ?? null,
);

const selectedTurningMax = computed(() => {
    if (!selectedTurningPoint.value) {
        return 1;
    }

    return Math.max(
        selectedTurningPoint.value.credit_value,
        selectedTurningPoint.value.outstanding_debt,
        selectedTurningPoint.value.paid_total,
        selectedTurningPoint.value.total_obligation,
        1,
    );
});

const turningBars = computed(() => {
    if (!selectedTurningPoint.value) {
        return [] as Array<{
            key: string;
            label: string;
            value: number;
            color: string;
            heightPercent: number;
        }>;
    }

    const point = selectedTurningPoint.value;
    const max = selectedTurningMax.value;

    return [
        {
            key: 'credit',
            label: 'Credito corrigido',
            value: point.credit_value,
            color: 'hsl(156 72% 42%)',
            heightPercent: Math.max((point.credit_value / max) * 100, 4),
        },
        {
            key: 'debt',
            label: 'Saldo devedor',
            value: point.outstanding_debt,
            color: 'hsl(38 92% 50%)',
            heightPercent: Math.max((point.outstanding_debt / max) * 100, 4),
        },
        {
            key: 'paid',
            label: 'Total pago',
            value: point.paid_total,
            color: 'hsl(217 91% 60%)',
            heightPercent: Math.max((point.paid_total / max) * 100, 4),
        },
    ];
});

const turningReferencePercent = computed(() => {
    if (!selectedTurningPoint.value) {
        return 0;
    }

    return Math.max(
        (selectedTurningPoint.value.total_obligation /
            selectedTurningMax.value) *
            100,
        0,
    );
});

const setTurningYear = (index: number) => {
    selectedTurningPointIndex.value = index;
};

const clearTurningTimelineTimer = () => {
    if (turningTimelineTimer !== null) {
        window.clearInterval(turningTimelineTimer);
        turningTimelineTimer = null;
    }
};

const playTurningTimeline = () => {
    if (props.turningPoint.timeline.length <= 1) {
        return;
    }

    clearTurningTimelineTimer();
    isTurningTimelinePlaying.value = true;

    turningTimelineTimer = window.setInterval(() => {
        const lastIndex = props.turningPoint.timeline.length - 1;
        if (selectedTurningPointIndex.value >= lastIndex) {
            isTurningTimelinePlaying.value = false;
            clearTurningTimelineTimer();
            return;
        }

        selectedTurningPointIndex.value += 1;
    }, turningTimelineIntervalMs);
};

const pauseTurningTimeline = () => {
    isTurningTimelinePlaying.value = false;
    clearTurningTimelineTimer();
};

const restartTurningTimeline = () => {
    selectedTurningPointIndex.value = 0;
    playTurningTimeline();
};

watch(selectedTurningPointIndex, (index) => {
    if (!isTurningTimelinePlaying.value) {
        return;
    }

    const lastIndex = props.turningPoint.timeline.length - 1;
    if (index >= lastIndex) {
        isTurningTimelinePlaying.value = false;
        clearTurningTimelineTimer();
    }
});

onMounted(() => {
    if (props.turningPoint.timeline.length > 2) {
        restartTurningTimeline();
    }
});

onBeforeUnmount(() => {
    clearTurningTimelineTimer();
});

const applyScenario = (profile: string) => {
    if (profile === props.scenario.active) {
        return;
    }

    router.get(
        route('megacombo.client-portal'),
        { profile },
        {
            preserveScroll: true,
            replace: true,
        },
    );
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
            <div
                class="relative overflow-hidden rounded-2xl border bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 p-6 text-slate-50"
            >
                <div
                    class="pointer-events-none absolute -top-10 -right-10 h-40 w-40 rounded-full bg-cyan-400/20 blur-2xl"
                />
                <div
                    class="pointer-events-none absolute -bottom-12 left-12 h-40 w-40 rounded-full bg-blue-500/20 blur-3xl"
                />

                <h1 class="text-3xl font-bold tracking-tight">
                    Portal do Cliente Final
                </h1>
                <p class="mt-2 max-w-3xl text-slate-300">
                    Olá {{ props.portfolio.client_name }}. Acompanhe a evolucao
                    do seu patrimonio com pagamento mensal e correcao do credito
                    pelo INCC.
                </p>

                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <span
                        class="rounded-full border border-slate-700 px-3 py-1"
                    >
                        Shadow Portfolio: Consorcio vs Selic
                    </span>
                    <span
                        class="rounded-full border border-slate-700 px-3 py-1"
                    >
                        Atualizado em {{ generatedAtLabel }}
                    </span>
                    <span
                        class="rounded-full border border-slate-700 px-3 py-1"
                    >
                        Selic via {{ props.selicSource.provider }} (serie
                        {{ props.selicSource.series }})
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Cotas em carteira</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-bold">
                            {{ props.portfolio.quota_count }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{ props.portfolio.assemblies_count }} assembleias
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Credito total</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-bold">
                            {{
                                money.format(props.portfolio.credit_value_total)
                            }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{
                                money.format(props.portfolio.quota_credit_value)
                            }}
                            por cota
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Parcela total mensal</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-bold">
                            {{
                                money.format(
                                    props.portfolio.installment_value_total,
                                )
                            }}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {{
                                money.format(
                                    props.portfolio.quota_installment_value,
                                )
                            }}
                            por cota
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Prazo contratual</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-xl font-bold">
                            {{ props.portfolio.quota_term_months }} meses
                        </p>
                        <p class="text-muted-foreground text-xs">
                            regra fixa da cota
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Ferramentas avançadas do cliente</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="
                                route('megacombo.client-financial-calculator')
                            "
                            class="inline-flex items-center rounded-md border border-cyan-600 bg-cyan-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-700"
                        >
                            Abrir Calculadora de Custo Financeiro
                        </Link>
                        <Link
                            :href="route('megacombo.client-probability-engine')"
                            class="inline-flex items-center rounded-md border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                        >
                            Abrir Motor de Probabilidades
                        </Link>
                    </div>
                    <p class="text-muted-foreground mt-3 text-xs">
                        As ferramentas foram separadas do dashboard e podem ser
                        acessadas por este atalho ou pelo menu lateral.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Controle de cotas por assembleia</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <p class="text-muted-foreground text-sm">
                            Cada cota possui valor de
                            {{
                                money.format(
                                    props.portfolio.quota_credit_value,
                                )
                            }}, parcela de
                            {{
                                money.format(
                                    props.portfolio.quota_installment_value,
                                )
                            }}
                            e prazo de
                            {{ props.portfolio.quota_term_months }} meses.
                        </p>
                        <a
                            v-if="
                                props.acquireMoreAction.enabled &&
                                props.acquireMoreAction.url
                            "
                            :href="props.acquireMoreAction.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center rounded-md border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                        >
                            {{ props.acquireMoreAction.label }}
                        </a>
                        <button
                            v-else
                            type="button"
                            disabled
                            class="inline-flex items-center rounded-md border px-4 py-2 text-sm font-semibold opacity-60"
                        >
                            {{ props.acquireMoreAction.label }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div
                            v-for="quota in props.quotas"
                            :key="quota.id"
                            class="rounded-lg border p-4"
                        >
                            <div
                                class="mb-2 flex items-center justify-between gap-2"
                            >
                                <p class="font-semibold">
                                    Grupo {{ quota.group_code ?? '-' }} / Cota
                                    {{ quota.quota_number ?? '-' }}
                                </p>
                                <span
                                    class="text-muted-foreground text-xs uppercase"
                                    >{{ quota.status }}</span
                                >
                            </div>
                            <p class="text-muted-foreground text-xs">
                                Credito: {{ money.format(quota.credit_value) }}
                            </p>
                            <p class="text-muted-foreground text-xs">
                                Parcela:
                                {{ money.format(quota.installment_value) }}
                            </p>
                            <p class="text-muted-foreground text-xs">
                                Prazo: {{ quota.remaining_installments }} meses
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle
                        >Shadow Portfolio: Consorcio x Tesouro Selic</CardTitle
                    >
                    <p class="text-muted-foreground text-sm">
                        Comparativo matematico da sua estrategia de consorcio
                        com o cenario alternativo de aportar a parcela mensal no
                        Tesouro Selic.
                    </p>
                </CardHeader>
                <CardContent>
                    <div class="mb-5 rounded-lg border p-4">
                        <p class="text-muted-foreground text-xs">
                            Perfil de projecao do cliente
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="scenarioOption in props.scenario.options"
                                :key="scenarioOption.key"
                                type="button"
                                class="rounded-full border px-3 py-1.5 text-sm transition"
                                :class="
                                    scenarioOption.key === props.scenario.active
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'hover:border-slate-500'
                                "
                                @click="applyScenario(scenarioOption.key)"
                            >
                                {{ scenarioOption.label }}
                            </button>
                        </div>
                        <p class="text-muted-foreground mt-2 text-xs">
                            Cenario atual: INCC
                            {{ (props.inccMonthlyRate * 100).toFixed(2) }}% a.m.
                            e agio
                            {{ (props.projectedAgioRate * 100).toFixed(2) }}%.
                        </p>
                    </div>

                    <div class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div class="rounded-lg border p-4">
                            <p class="text-muted-foreground text-xs">
                                Projecao Consorcio (INCC + agio)
                            </p>
                            <p class="mt-1 text-2xl font-bold text-emerald-600">
                                {{
                                    money.format(
                                        props.shadowSummary.consorcio_value,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-muted-foreground text-xs">
                                Projecao Selic
                            </p>
                            <p class="mt-1 text-2xl font-bold text-blue-600">
                                {{
                                    money.format(
                                        props.shadowSummary.selic_value,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-muted-foreground text-xs">
                                Vantagem do metodo
                            </p>
                            <p
                                class="mt-1 text-2xl font-bold"
                                :class="
                                    edgeIsPositive
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{
                                    money.format(props.shadowSummary.edge_value)
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-muted-foreground text-xs">
                                Horizonte analisado
                            </p>
                            <p class="mt-1 text-2xl font-bold">
                                {{ props.shadowSummary.horizon_months }} meses
                            </p>
                        </div>
                    </div>

                    <div class="w-full overflow-x-auto">
                        <svg
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            class="w-full min-w-[680px]"
                        >
                            <defs>
                                <linearGradient
                                    id="consorcioLineGradient"
                                    x1="0%"
                                    y1="0%"
                                    x2="100%"
                                    y2="0%"
                                >
                                    <stop
                                        offset="0%"
                                        stop-color="hsl(158 75% 42%)"
                                    />
                                    <stop
                                        offset="100%"
                                        stop-color="hsl(130 65% 50%)"
                                    />
                                </linearGradient>
                            </defs>

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
                                :points="shadowSelicPolyline"
                                fill="none"
                                stroke="hsl(217 91% 60%)"
                                stroke-width="3"
                                stroke-linecap="round"
                            />
                            <polyline
                                :points="shadowConsorcioPolyline"
                                fill="none"
                                stroke="url(#consorcioLineGradient)"
                                stroke-width="3.5"
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
                                style="background-color: hsl(217 91% 60%)"
                            />
                            Tesouro Selic com aportes mensais
                        </div>
                        <div
                            class="inline-flex items-center gap-2 rounded-md border px-3 py-1"
                        >
                            <span
                                class="inline-block h-2 w-2 rounded-full"
                                style="background-color: hsl(148 65% 45%)"
                            />
                            Consorcio corrigido por INCC + venda com agio
                        </div>
                    </div>

                    <p class="text-muted-foreground mt-4 text-xs">
                        Premissas atuais: Selic
                        {{ (props.selicAnnualRate * 100).toFixed(2) }}% a.a.,
                        INCC {{ (props.inccMonthlyRate * 100).toFixed(2) }}%
                        a.m., agio projetado
                        {{ (props.projectedAgioRate * 100).toFixed(2) }}%.
                    </p>

                    <p class="text-muted-foreground mt-2 text-xs">
                        Referencia Selic: {{ selicReferenceLabel }}.
                        <span v-if="props.selicSource.is_fallback">
                            API indisponivel no momento, usando taxa de
                            contingencia.
                        </span>
                    </p>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Nivel patrimonial</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">
                            {{ props.gamification.level }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Sequencia positiva de
                            {{ props.gamification.winning_streak_years }}
                            anos contra a Selic.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Valorizacao diaria estimada</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-emerald-600">
                            {{
                                money.format(
                                    props.gamification.daily_growth_consorcio,
                                )
                            }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Crescimento diario do patrimonio no consorcio.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base"
                            >Ritmo diario na Selic</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-blue-600">
                            {{
                                money.format(
                                    props.gamification.daily_growth_selic,
                                )
                            }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Referencia de rendimento diario no benchmark.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle
                        >Ponto de Virada: credito vs saldo devedor</CardTitle
                    >
                    <p class="text-muted-foreground text-sm">
                        O INCC corrige o credito para cima, enquanto os
                        pagamentos reduzem integralmente o saldo devedor.
                        Acompanhe o momento em que seu credito supera toda a
                        obrigacao do plano.
                    </p>
                </CardHeader>
                <CardContent>
                    <div class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="rounded-lg border p-4">
                            <p class="text-muted-foreground text-xs">
                                Status do ponto de virada
                            </p>
                            <p class="mt-1 text-xl font-bold">
                                {{
                                    props.turningPoint.summary.found
                                        ? 'Virada confirmada'
                                        : 'Em construcao'
                                }}
                            </p>
                            <p
                                class="text-muted-foreground mt-1 text-xs"
                                v-if="props.turningPoint.summary.found"
                            >
                                Cruzamento no ano
                                {{ props.turningPoint.summary.year }} ({{
                                    props.turningPoint.summary.months
                                }}
                                meses).
                            </p>
                        </div>
                        <div class="rounded-lg border p-4">
                            <p class="text-muted-foreground text-xs">
                                Folga no ultimo horizonte
                            </p>
                            <p
                                class="mt-1 text-xl font-bold"
                                :class="
                                    props.turningPoint.summary
                                        .latest_margin_percent >= 0
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{
                                    props.turningPoint.summary.latest_margin_percent.toFixed(
                                        2,
                                    )
                                }}%
                            </p>
                        </div>
                        <div
                            class="rounded-lg border p-4"
                            v-if="selectedTurningPoint"
                        >
                            <p class="text-muted-foreground text-xs">
                                Ano analisado no grafico
                            </p>
                            <p class="mt-1 text-xl font-bold">
                                {{ selectedTurningPoint.label }}
                            </p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Edge:
                                {{
                                    money.format(
                                        selectedTurningPoint.turning_edge,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="mb-5 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-full border border-emerald-600 px-3 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                            @click="playTurningTimeline"
                            v-if="!isTurningTimelinePlaying"
                        >
                            Iniciar timeline
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-amber-600 px-3 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/40"
                            @click="pauseTurningTimeline"
                            v-else
                        >
                            Pausar timeline
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-slate-500 px-3 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="restartTurningTimeline"
                        >
                            Reiniciar
                        </button>

                        <button
                            v-for="(point, index) in props.turningPoint
                                .timeline"
                            :key="point.label"
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs transition"
                            :class="
                                index === selectedTurningPointIndex
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'hover:border-slate-500'
                            "
                            @click="setTurningYear(index)"
                        >
                            {{ point.label }}
                        </button>
                    </div>

                    <div
                        v-if="selectedTurningPoint"
                        class="relative overflow-hidden rounded-xl border bg-gradient-to-b from-slate-50 to-white p-4 dark:from-slate-900/70 dark:to-slate-950"
                    >
                        <div class="text-muted-foreground mb-3 text-xs">
                            Linha tracejada: obrigacao total (total pago + saldo
                            devedor)
                        </div>

                        <div class="relative h-72">
                            <div
                                class="pointer-events-none absolute inset-x-2 z-10 border-t-2 border-dashed border-rose-500/80 transition-all duration-700 ease-out"
                                :style="{
                                    bottom: `${turningReferencePercent}%`,
                                }"
                            />
                            <div
                                class="absolute right-2 z-20 rounded bg-rose-500 px-2 py-1 text-[10px] font-semibold text-white transition-all duration-700 ease-out"
                                :style="{
                                    bottom: `calc(${turningReferencePercent}% + 6px)`,
                                }"
                            >
                                Obrigacao:
                                {{
                                    money.format(
                                        selectedTurningPoint.total_obligation,
                                    )
                                }}
                            </div>

                            <div
                                class="grid h-full grid-cols-3 items-end gap-6 px-4 pt-2 pb-8"
                            >
                                <div
                                    v-for="bar in turningBars"
                                    :key="bar.key"
                                    class="flex h-full flex-col items-center justify-end"
                                >
                                    <div
                                        class="relative w-full max-w-[120px] flex-1 overflow-hidden rounded-lg border bg-slate-100/80 dark:bg-slate-900/80"
                                    >
                                        <div
                                            class="absolute inset-x-0 bottom-0 rounded-lg transition-all duration-700 ease-out"
                                            :style="{
                                                height: `${bar.heightPercent}%`,
                                                backgroundColor: bar.color,
                                            }"
                                        />
                                    </div>
                                    <p
                                        class="mt-2 text-center text-xs font-semibold"
                                    >
                                        {{ bar.label }}
                                    </p>
                                    <p class="text-center text-sm font-bold">
                                        {{ money.format(bar.value) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p
                        class="text-muted-foreground mt-4 text-xs"
                        v-if="selectedTurningPoint"
                    >
                        Obrigacao total no {{ selectedTurningPoint.label }}:
                        {{
                            money.format(selectedTurningPoint.total_obligation)
                        }}. Quando o credito corrigido fica acima dessa linha,
                        seu patrimonio passa a ganhar da inflacao com folga.
                    </p>
                </CardContent>
            </Card>

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

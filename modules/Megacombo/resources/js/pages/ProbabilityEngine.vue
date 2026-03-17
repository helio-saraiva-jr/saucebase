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
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Defaults {
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
    snowball: {
        baseYearsWithoutBids: number;
        targetReductionRate: number;
        freeBidsPerYear: number;
    };
    multiplier: {
        quotas: number;
        horizonYears: number;
    };
    leverage: {
        monthlyCapacity: number;
        targetPatrimony: number;
        seed?: number | null;
    };
}

interface ProbabilityResult {
    open_market: {
        probability_percentage: number;
        participants: number;
        draws_per_month: number;
        duration_months: number;
    };
    exclusive_group: {
        probability_percentage: number;
        participants: number;
        draws_per_month: number;
        duration_months: number;
    };
    delta: {
        percentage_points: number;
        multiple: number | null;
    };
    snowball: {
        base_years_without_bids: number;
        estimated_years_with_bids: number;
        time_saved_years: number;
        free_bids_per_year: number;
        reduction_rate_percentage: number;
    };
    multiplier: {
        quotas: number;
        horizon_years: number;
        single_quota_horizon_probability_percentage: number;
        at_least_one_contemplation_probability_percentage: number;
        expected_contemplations: number;
    };
    leverage: {
        inputs: {
            monthly_capacity: number;
            target_patrimony: number;
            seed: number | null;
        };
        group_assumptions: {
            participants: number;
            duration_months: number;
            draws_per_month: number;
            average_contemplation_months: number;
        };
        financial_assumptions: {
            pv_discount_rate_min_monthly: number;
            pv_discount_rate_max_monthly: number;
            incc_annual_rate: number;
            cdi_monthly_rate: number;
            selic_annual_rate: number;
            selic_monthly_rate: number;
        };
        simulation: {
            is_reproducible: boolean;
            random_source: string;
        };
        portfolio: {
            quota_count: number;
            block1_quota_count: number;
            block2_quota_count: number;
            quota_credit_value: number;
            quota_installment_value: number;
            effective_monthly_outflow: number;
        };
        snowball_curve: Array<{
            month: number;
            year: number;
            money_out: number;
            projected_patrimony: number;
            net_patrimony: number;
        }>;
        milestone: {
            target_patrimony: number;
            crossing_month: number | null;
            crossing_year: number | null;
            million_month: number | null;
            million_year: number | null;
            final_net_patrimony: number;
        };
        benchmark: {
            selic_final_value: number;
            consortium_expected_value: number;
            cdi_reference_final_value: number;
            vs_selic_ratio: number | null;
            vs_cdi_ratio: number | null;
        };
    };
}

const props = defineProps<{
    defaults: Defaults;
    audience?: 'representative' | 'client';
    quickLinks?: Array<{
        label: string;
        url: string;
    }>;
}>();

const title = 'Motor de Análise de Probabilidades';

const isClientAudience = computed(() => props.audience === 'client');

const homeHref = computed(() =>
    isClientAudience.value
        ? route('megacombo.client-portal')
        : route('dashboard'),
);

const form = ref({
    openMarket: { ...props.defaults.openMarket },
    exclusiveGroup: { ...props.defaults.exclusiveGroup },
    snowball: { ...props.defaults.snowball },
    multiplier: { ...props.defaults.multiplier },
    leverage: {
        ...props.defaults.leverage,
        seed: props.defaults.leverage.seed ?? '',
    },
});

const result = ref<ProbabilityResult | null>(null);
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);

const toNumber = (value: string | number): number => {
    if (typeof value === 'number') {
        return value;
    }

    return Number(value.replace(',', '.'));
};

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 2,
});

const decimalFormatter = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const formatCurrency = (value: number): string =>
    currencyFormatter.format(value);

const formatDecimal = (value: number): string => decimalFormatter.format(value);

const openMarketSliceStyle = computed(() => {
    const chance = result.value?.open_market.probability_percentage ?? 0;
    return {
        background: `conic-gradient(hsl(215 75% 55%) 0% ${chance}%, hsl(215 20% 85%) ${chance}% 100%)`,
    };
});

const exclusiveSliceStyle = computed(() => {
    const chance = result.value?.exclusive_group.probability_percentage ?? 0;
    return {
        background: `conic-gradient(hsl(148 65% 45%) 0% ${chance}%, hsl(148 20% 85%) ${chance}% 100%)`,
    };
});

const leverageCurveMax = computed(() => {
    const points = result.value?.leverage.snowball_curve;

    if (!points || points.length === 0) {
        return 1;
    }

    const maxValue = points.reduce((carry, point) => {
        return Math.max(carry, point.money_out, point.projected_patrimony);
    }, 1);

    return Math.max(maxValue, 1);
});

const leverageChart = computed(() => {
    const points = result.value?.leverage.snowball_curve ?? [];

    if (points.length < 2) {
        return null;
    }

    const width = 980;
    const height = 280;
    const paddingTop = 16;
    const paddingRight = 16;
    const paddingBottom = 26;
    const paddingLeft = 36;
    const chartWidth = width - paddingLeft - paddingRight;
    const chartHeight = height - paddingTop - paddingBottom;
    const maxMonth = Math.max(points[points.length - 1]?.month ?? 1, 1);

    const xAt = (month: number): number => {
        return paddingLeft + (month / maxMonth) * chartWidth;
    };

    const yAt = (value: number): number => {
        return paddingTop + (1 - value / leverageCurveMax.value) * chartHeight;
    };

    const toPath = (values: number[]): string => {
        return values
            .map((value, index) => {
                const point = points[index];
                const x = xAt(point.month);
                const y = yAt(value);

                return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`;
            })
            .join(' ');
    };

    const projectedValues = points.map((point) => point.projected_patrimony);
    const moneyOutValues = points.map((point) => point.money_out);
    const projectedLinePath = toPath(projectedValues);
    const moneyOutLinePath = toPath(moneyOutValues);

    const firstX = xAt(points[0].month);
    const lastX = xAt(points[points.length - 1].month);
    const bottomY = paddingTop + chartHeight;
    const projectedAreaPath = `${projectedLinePath} L ${lastX.toFixed(2)} ${bottomY.toFixed(2)} L ${firstX.toFixed(2)} ${bottomY.toFixed(2)} Z`;

    return {
        width,
        height,
        projectedLinePath,
        projectedAreaPath,
        moneyOutLinePath,
    };
});

const getCsrfToken = (): string =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const buildProbabilityReportHtml = (generatedAt: string): string => {
    if (!result.value) {
        return '';
    }

    return `
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title>Relatorio de Probabilidades - Megacombo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        h1 { margin-bottom: 4px; }
        .muted { color: #6b7280; font-size: 12px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
        .card { border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; }
        .label { color: #6b7280; font-size: 12px; }
        .value { font-size: 24px; font-weight: 700; margin-top: 4px; }
        .summary { border: 1px solid #86efac; background: #f0fdf4; border-radius: 8px; padding: 12px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px 0; font-size: 14px; }
    </style>
</head>
<body>
    <h1>Motor de Analise de Probabilidades</h1>
    <div class="muted">Gerado em ${generatedAt}</div>

    <div class="grid">
        <div class="card">
            <div class="label">Mar aberto</div>
            <div class="value">${result.value.open_market.probability_percentage.toFixed(1)}%</div>
            <table>
                <tr><td>Participantes</td><td>${result.value.open_market.participants}</td></tr>
                <tr><td>Sorteios por mes</td><td>${result.value.open_market.draws_per_month}</td></tr>
                <tr><td>Duracao</td><td>${result.value.open_market.duration_months} meses</td></tr>
            </table>
        </div>
        <div class="card">
            <div class="label">Megacombo</div>
            <div class="value">${result.value.exclusive_group.probability_percentage.toFixed(1)}%</div>
            <table>
                <tr><td>Participantes</td><td>${result.value.exclusive_group.participants}</td></tr>
                <tr><td>Sorteios por mes</td><td>${result.value.exclusive_group.draws_per_month}</td></tr>
                <tr><td>Duracao</td><td>${result.value.exclusive_group.duration_months} meses</td></tr>
            </table>
        </div>
    </div>

    <div class="summary">
        <strong>Comparativo e previsoes</strong>
        <table>
            <tr><td>Diferenca de chance</td><td>${result.value.delta.percentage_points.toFixed(1)} p.p.</td></tr>
            <tr><td>Multiplicador</td><td>${(result.value.delta.multiple ?? 0).toFixed(2)}x</td></tr>
            <tr><td>Bola de neve (sem lances)</td><td>${result.value.snowball.base_years_without_bids.toFixed(1)} anos</td></tr>
            <tr><td>Bola de neve (com lances)</td><td>${result.value.snowball.estimated_years_with_bids.toFixed(1)} anos</td></tr>
            <tr><td>Economia de tempo</td><td>${result.value.snowball.time_saved_years.toFixed(1)} anos</td></tr>
            <tr><td>Lei das Medias (>= 1 contemplacao)</td><td>${result.value.multiplier.at_least_one_contemplation_probability_percentage.toFixed(1)}%</td></tr>
            <tr><td>Contemplacoes esperadas</td><td>${result.value.multiplier.expected_contemplations.toFixed(2)}</td></tr>
            <tr><td>Cotas ativas (Projeto Milionario)</td><td>${result.value.leverage.portfolio.quota_count}</td></tr>
            <tr><td>Saida mensal efetiva</td><td>${formatCurrency(result.value.leverage.portfolio.effective_monthly_outflow)}</td></tr>
            <tr><td>Patrimonio projetado (consorcio)</td><td>${formatCurrency(result.value.leverage.benchmark.consortium_expected_value)}</td></tr>
            <tr><td>Patrimonio projetado (Selic)</td><td>${formatCurrency(result.value.leverage.benchmark.selic_final_value)}</td></tr>
            <tr><td>Multiplicador vs Selic</td><td>${(result.value.leverage.benchmark.vs_selic_ratio ?? 0).toFixed(2)}x</td></tr>
            <tr><td>Seed da simulacao</td><td>${result.value.leverage.inputs.seed ?? 'aleatorio'}</td></tr>
            <tr><td>INCC anual</td><td>${(result.value.leverage.financial_assumptions.incc_annual_rate * 100).toFixed(2)}%</td></tr>
            <tr><td>CDI mensal</td><td>${(result.value.leverage.financial_assumptions.cdi_monthly_rate * 100).toFixed(2)}%</td></tr>
            <tr><td>Selic anual</td><td>${(result.value.leverage.financial_assumptions.selic_annual_rate * 100).toFixed(2)}%</td></tr>
            <tr><td>Contemplacao media</td><td>${result.value.leverage.group_assumptions.average_contemplation_months} meses</td></tr>
        </table>
    </div>
</body>
</html>
`;
};

const exportProbabilityPdf = () => {
    if (!result.value) {
        return;
    }

    errorMessage.value = null;

    const now = new Date().toLocaleString('pt-BR');
    const reportHtml = buildProbabilityReportHtml(now);

    const reportWindow = window.open(
        '',
        '_blank',
        'noopener,noreferrer,width=900,height=700',
    );

    if (reportWindow) {
        reportWindow.document.write(reportHtml);
        reportWindow.document.close();
        reportWindow.focus();
        reportWindow.print();
        return;
    }

    // Fallback for popup blockers: print from an off-screen iframe.
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const iframeDoc = iframe.contentWindow?.document;

    if (!iframeDoc || !iframe.contentWindow) {
        document.body.removeChild(iframe);
        errorMessage.value =
            'Nao foi possivel exportar em PDF neste navegador.';
        return;
    }

    iframeDoc.open();
    iframeDoc.write(reportHtml);
    iframeDoc.close();

    setTimeout(() => {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
        document.body.removeChild(iframe);
    }, 120);
};

const runSimulation = async () => {
    errorMessage.value = null;
    isLoading.value = true;

    try {
        const response = await fetch(
            route('api.megacombo.simulate.probability'),
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    open_market: {
                        participants: Math.round(
                            toNumber(form.value.openMarket.participants),
                        ),
                        duration_months: Math.round(
                            toNumber(form.value.openMarket.durationMonths),
                        ),
                        draws_per_month: Math.round(
                            toNumber(form.value.openMarket.drawsPerMonth),
                        ),
                    },
                    exclusive_group: {
                        participants: Math.round(
                            toNumber(form.value.exclusiveGroup.participants),
                        ),
                        duration_months: Math.round(
                            toNumber(form.value.exclusiveGroup.durationMonths),
                        ),
                        draws_per_month: Math.round(
                            toNumber(form.value.exclusiveGroup.drawsPerMonth),
                        ),
                    },
                    snowball: {
                        base_years_without_bids: toNumber(
                            form.value.snowball.baseYearsWithoutBids,
                        ),
                        target_reduction_rate: toNumber(
                            form.value.snowball.targetReductionRate,
                        ),
                        free_bids_per_year: Math.round(
                            toNumber(form.value.snowball.freeBidsPerYear),
                        ),
                    },
                    multiplier: {
                        quotas: Math.round(
                            toNumber(form.value.multiplier.quotas),
                        ),
                        horizon_years: Math.round(
                            toNumber(form.value.multiplier.horizonYears),
                        ),
                    },
                    leverage: {
                        monthly_capacity: toNumber(
                            form.value.leverage.monthlyCapacity,
                        ),
                        target_patrimony: toNumber(
                            form.value.leverage.targetPatrimony,
                        ),
                        seed:
                            form.value.leverage.seed === ''
                                ? null
                                : Math.round(
                                      toNumber(form.value.leverage.seed),
                                  ),
                    },
                }),
            },
        );

        if (!response.ok) {
            if (response.status === 419) {
                errorMessage.value =
                    'Sessao expirada. Atualize a pagina e tente novamente.';
                return;
            }

            let payload: Record<string, unknown> | null = null;
            try {
                payload = (await response.json()) as Record<string, unknown>;
            } catch {
                payload = null;
            }

            const errors = payload?.errors as
                | Record<string, string[]>
                | undefined;
            const firstError = errors ? Object.values(errors)[0] : null;
            errorMessage.value =
                Array.isArray(firstError) && firstError.length > 0
                    ? String(firstError[0])
                    : 'Nao foi possivel executar a simulacao de probabilidades.';
            return;
        }

        result.value = (await response.json()) as ProbabilityResult;
    } catch {
        errorMessage.value =
            'Falha de comunicacao com o motor de probabilidades.';
    } finally {
        isLoading.value = false;
    }
};

void runSimulation();
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="[
            {
                title: isClientAudience ? 'Portal do cliente' : 'Megacombo',
                href: homeHref,
            },
            { title: 'Motor de Probabilidades' },
        ]"
    >
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div
                class="relative overflow-hidden rounded-2xl border bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 p-6 text-slate-50"
            >
                <div
                    class="pointer-events-none absolute -top-8 -right-8 h-36 w-36 rounded-full bg-emerald-400/20 blur-2xl"
                />
                <h1 class="text-3xl font-bold tracking-tight">
                    Motor de Análise de Probabilidades de Grupos
                </h1>
                <p class="mt-2 text-slate-300">
                    Painel interativo para comprovar matematicamente a
                    superioridade do grupo fechado e reduzir a objeção de sorte.
                </p>

                <div
                    v-if="props.quickLinks && props.quickLinks.length"
                    class="mt-4 flex flex-wrap gap-2"
                >
                    <Link
                        v-for="quickLink in props.quickLinks"
                        :key="quickLink.url"
                        :href="quickLink.url"
                        class="rounded-full border border-slate-600 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800"
                    >
                        {{ quickLink.label }}
                    </Link>
                </div>
            </div>

            <Alert>
                <AlertTitle>Prova matemática da vantagem Megacombo</AlertTitle>
                <AlertDescription>
                    Comparamos mar aberto e grupo fechado, estimamos efeito bola
                    de neve e aplicamos Lei das Médias com múltiplas cotas.
                </AlertDescription>
            </Alert>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
                <Card class="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Parâmetros da simulação</CardTitle>
                        <CardDescription
                            >Altere os cenários para validar cada objeção do
                            cliente.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="space-y-3 rounded-lg border p-3">
                            <p class="text-sm font-semibold">
                                Mar aberto (bancos)
                            </p>
                            <div class="space-y-2">
                                <Label>Participantes</Label>
                                <Input
                                    v-model="form.openMarket.participants"
                                    type="number"
                                    min="1"
                                />
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-2">
                                    <Label>Sorteios/mês</Label>
                                    <Input
                                        v-model="form.openMarket.drawsPerMonth"
                                        type="number"
                                        min="1"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>Meses</Label>
                                    <Input
                                        v-model="form.openMarket.durationMonths"
                                        type="number"
                                        min="1"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 rounded-lg border p-3">
                            <p class="text-sm font-semibold">
                                Megacombo (grupo fechado)
                            </p>
                            <div class="space-y-2">
                                <Label>Participantes</Label>
                                <Input
                                    v-model="form.exclusiveGroup.participants"
                                    type="number"
                                    min="1"
                                />
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-2">
                                    <Label>Sorteios/mês</Label>
                                    <Input
                                        v-model="
                                            form.exclusiveGroup.drawsPerMonth
                                        "
                                        type="number"
                                        min="1"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>Meses</Label>
                                    <Input
                                        v-model="
                                            form.exclusiveGroup.durationMonths
                                        "
                                        type="number"
                                        min="1"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 rounded-lg border p-3">
                            <p class="text-sm font-semibold">
                                Efeito bola de neve
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-2">
                                    <Label>Tempo base sem lances (anos)</Label>
                                    <Input
                                        v-model="
                                            form.snowball.baseYearsWithoutBids
                                        "
                                        type="number"
                                        min="1"
                                        step="0.1"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>Lances livres/ano</Label>
                                    <Input
                                        v-model="form.snowball.freeBidsPerYear"
                                        type="number"
                                        min="0"
                                        max="24"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 rounded-lg border p-3">
                            <Label>Multiplicador de cotas (5 a 10)</Label>
                            <input
                                v-model="form.multiplier.quotas"
                                type="range"
                                min="5"
                                max="10"
                                step="1"
                                class="w-full"
                            />
                            <div
                                class="text-muted-foreground flex items-center justify-between text-xs"
                            >
                                <span>5 cotas</span>
                                <span class="text-foreground font-semibold"
                                    >{{ form.multiplier.quotas }} cotas</span
                                >
                                <span>10 cotas</span>
                            </div>
                            <div class="space-y-2">
                                <Label>Janela de análise (anos)</Label>
                                <Input
                                    v-model="form.multiplier.horizonYears"
                                    type="number"
                                    min="1"
                                    max="20"
                                />
                            </div>
                        </div>

                        <div class="space-y-3 rounded-lg border p-3">
                            <p class="text-sm font-semibold">
                                Projeto Milionário (alavancagem)
                            </p>
                            <div class="space-y-2">
                                <Label>Capacidade mensal (R$)</Label>
                                <Input
                                    v-model="form.leverage.monthlyCapacity"
                                    type="number"
                                    min="644.02"
                                    step="0.01"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Meta de patrimônio (R$)</Label>
                                <Input
                                    v-model="form.leverage.targetPatrimony"
                                    type="number"
                                    min="100000"
                                    step="1000"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Seed opcional da simulação</Label>
                                <Input
                                    v-model="form.leverage.seed"
                                    type="number"
                                    min="1"
                                    step="1"
                                    placeholder="Ex.: 20260316"
                                />
                                <p class="text-muted-foreground text-xs">
                                    Use o mesmo seed para reproduzir exatamente
                                    o mesmo cenário em apresentações.
                                </p>
                            </div>
                        </div>

                        <Button
                            class="w-full"
                            :disabled="isLoading"
                            @click="runSimulation"
                        >
                            {{
                                isLoading
                                    ? 'Simulando...'
                                    : 'Atualizar simulação'
                            }}
                        </Button>

                        <Button
                            class="w-full"
                            variant="outline"
                            :disabled="!result"
                            @click="exportProbabilityPdf"
                        >
                            Exportar relatório em PDF
                        </Button>

                        <p v-if="errorMessage" class="text-sm text-red-600">
                            {{ errorMessage }}
                        </p>
                    </CardContent>
                </Card>

                <div class="space-y-6 xl:col-span-3">
                    <div
                        class="grid grid-cols-1 gap-6 md:grid-cols-2"
                        v-if="result"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>Mar Aberto</CardTitle>
                                <CardDescription
                                    >Chance total de contemplação por
                                    sorteio</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    class="mx-auto h-44 w-44 rounded-full"
                                    :style="openMarketSliceStyle"
                                />
                                <p class="mt-4 text-center text-3xl font-bold">
                                    {{
                                        result.open_market.probability_percentage.toFixed(
                                            1,
                                        )
                                    }}%
                                </p>
                                <p
                                    class="text-muted-foreground mt-1 text-center text-xs"
                                >
                                    {{ result.open_market.participants }}
                                    participantes |
                                    {{ result.open_market.draws_per_month }}
                                    sorteio/mês |
                                    {{ result.open_market.duration_months }}
                                    meses
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Megacombo</CardTitle>
                                <CardDescription
                                    >Chance total de contemplação por
                                    sorteio</CardDescription
                                >
                            </CardHeader>
                            <CardContent>
                                <div
                                    class="mx-auto h-44 w-44 rounded-full"
                                    :style="exclusiveSliceStyle"
                                />
                                <p class="mt-4 text-center text-3xl font-bold">
                                    {{
                                        result.exclusive_group.probability_percentage.toFixed(
                                            1,
                                        )
                                    }}%
                                </p>
                                <p
                                    class="text-muted-foreground mt-1 text-center text-xs"
                                >
                                    {{ result.exclusive_group.participants }}
                                    participantes |
                                    {{ result.exclusive_group.draws_per_month }}
                                    sorteios/mês |
                                    {{ result.exclusive_group.duration_months }}
                                    meses
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card v-if="result">
                        <CardHeader>
                            <CardTitle>Comparativo direto</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-wrap gap-2">
                            <Badge variant="outline"
                                >Diferença:
                                {{ result.delta.percentage_points.toFixed(1) }}
                                p.p.</Badge
                            >
                            <Badge variant="outline"
                                >Multiplicador:
                                {{
                                    (result.delta.multiple ?? 0).toFixed(2)
                                }}x</Badge
                            >
                        </CardContent>
                    </Card>

                    <Card v-if="result">
                        <CardHeader>
                            <CardTitle>Simulador efeito bola de neve</CardTitle>
                            <CardDescription
                                >Tempo médio estimado com lances livres
                                estratégicos.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Sem lances
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            result.snowball.base_years_without_bids.toFixed(
                                                1,
                                            )
                                        }}
                                        anos
                                    </p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Com bola de neve
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            result.snowball.estimated_years_with_bids.toFixed(
                                                1,
                                            )
                                        }}
                                        anos
                                    </p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Tempo economizado
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            result.snowball.time_saved_years.toFixed(
                                                1,
                                            )
                                        }}
                                        anos
                                    </p>
                                </div>
                            </div>
                            <p class="text-muted-foreground mt-3 text-xs">
                                Redução aplicada:
                                {{
                                    result.snowball.reduction_rate_percentage.toFixed(
                                        1,
                                    )
                                }}% com
                                {{ result.snowball.free_bids_per_year }}
                                lances/ano.
                            </p>
                        </CardContent>
                    </Card>

                    <Card v-if="result">
                        <CardHeader>
                            <CardTitle
                                >Multiplicador - Lei das Médias</CardTitle
                            >
                            <CardDescription>
                                Compra de {{ result.multiplier.quotas }} cotas
                                em {{ result.multiplier.horizon_years }} anos.
                            </CardDescription>
                        </CardHeader>
                        <CardContent
                            class="grid grid-cols-1 gap-3 md:grid-cols-3"
                        >
                            <div class="rounded-lg border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Chance (1 cota)
                                </p>
                                <p class="text-2xl font-bold">
                                    {{
                                        result.multiplier.single_quota_horizon_probability_percentage.toFixed(
                                            1,
                                        )
                                    }}%
                                </p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Chance (>= 1 contemplação)
                                </p>
                                <p class="text-2xl font-bold">
                                    {{
                                        result.multiplier.at_least_one_contemplation_probability_percentage.toFixed(
                                            1,
                                        )
                                    }}%
                                </p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Contemplações esperadas
                                </p>
                                <p class="text-2xl font-bold">
                                    {{
                                        result.multiplier.expected_contemplations.toFixed(
                                            2,
                                        )
                                    }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="result">
                        <CardHeader>
                            <CardTitle>
                                Simulador de Alavancagem Patrimonial
                            </CardTitle>
                            <CardDescription>
                                Curva de evolução do Projeto Milionário com
                                blocos de giro rápido e rendimento sobre o
                                cheio.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Cotas ativas
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            result.leverage.portfolio
                                                .quota_count
                                        }}
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        {{
                                            result.leverage.portfolio
                                                .block1_quota_count
                                        }}
                                        giro rápido /
                                        {{
                                            result.leverage.portfolio
                                                .block2_quota_count
                                        }}
                                        cheio
                                    </p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Saída mensal efetiva
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatCurrency(
                                                result.leverage.portfolio
                                                    .effective_monthly_outflow,
                                            )
                                        }}
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        Parcela por cota:
                                        {{
                                            formatCurrency(
                                                result.leverage.portfolio
                                                    .quota_installment_value,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Crédito por cota
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatCurrency(
                                                result.leverage.portfolio
                                                    .quota_credit_value,
                                            )
                                        }}
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        Meta:
                                        {{
                                            formatCurrency(
                                                result.leverage.inputs
                                                    .target_patrimony,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <p class="mb-3 text-sm font-semibold">
                                    Curva bola de neve patrimonial
                                </p>
                                <div
                                    v-if="leverageChart"
                                    class="rounded-lg border bg-slate-50 p-3 dark:bg-slate-950"
                                >
                                    <svg
                                        :viewBox="`0 0 ${leverageChart.width} ${leverageChart.height}`"
                                        class="h-64 w-full"
                                        role="img"
                                        aria-label="Evolução patrimonial projetada"
                                    >
                                        <path
                                            :d="leverageChart.projectedAreaPath"
                                            fill="rgba(16, 185, 129, 0.18)"
                                        />
                                        <path
                                            :d="leverageChart.moneyOutLinePath"
                                            fill="none"
                                            stroke="rgb(71 85 105)"
                                            stroke-width="3"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            :d="leverageChart.projectedLinePath"
                                            fill="none"
                                            stroke="rgb(5 150 105)"
                                            stroke-width="3"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <div
                                        class="mt-3 grid grid-cols-1 gap-2 text-xs sm:grid-cols-3"
                                    >
                                        <div
                                            v-if="
                                                result.leverage
                                                    .snowball_curve[0]
                                            "
                                            class="rounded-md border p-2"
                                        >
                                            <p class="text-muted-foreground">
                                                Início
                                            </p>
                                            <p class="font-medium">
                                                Mês
                                                {{
                                                    result.leverage
                                                        .snowball_curve[0].month
                                                }}
                                                • Patrimônio
                                                {{
                                                    formatCurrency(
                                                        result.leverage
                                                            .snowball_curve[0]
                                                            .projected_patrimony,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            v-if="
                                                result.leverage.snowball_curve[
                                                    Math.floor(
                                                        result.leverage
                                                            .snowball_curve
                                                            .length / 2,
                                                    )
                                                ]
                                            "
                                            class="rounded-md border p-2"
                                        >
                                            <p class="text-muted-foreground">
                                                Meio
                                            </p>
                                            <p class="font-medium">
                                                Mês
                                                {{
                                                    result.leverage
                                                        .snowball_curve[
                                                        Math.floor(
                                                            result.leverage
                                                                .snowball_curve
                                                                .length / 2,
                                                        )
                                                    ].month
                                                }}
                                                • Patrimônio
                                                {{
                                                    formatCurrency(
                                                        result.leverage
                                                            .snowball_curve[
                                                            Math.floor(
                                                                result.leverage
                                                                    .snowball_curve
                                                                    .length / 2,
                                                            )
                                                        ].projected_patrimony,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div
                                            v-if="
                                                result.leverage.snowball_curve[
                                                    result.leverage
                                                        .snowball_curve.length -
                                                        1
                                                ]
                                            "
                                            class="rounded-md border p-2"
                                        >
                                            <p class="text-muted-foreground">
                                                Final
                                            </p>
                                            <p class="font-medium">
                                                Mês
                                                {{
                                                    result.leverage
                                                        .snowball_curve[
                                                        result.leverage
                                                            .snowball_curve
                                                            .length - 1
                                                    ].month
                                                }}
                                                • Patrimônio
                                                {{
                                                    formatCurrency(
                                                        result.leverage
                                                            .snowball_curve[
                                                            result.leverage
                                                                .snowball_curve
                                                                .length - 1
                                                        ].projected_patrimony,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="mt-3 flex flex-wrap gap-4 text-xs"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <span
                                                class="inline-block h-2 w-4 rounded-sm bg-emerald-600"
                                            />
                                            Patrimônio projetado
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <span
                                                class="inline-block h-2 w-4 rounded-sm bg-slate-600"
                                            />
                                            Dinheiro pago
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div
                        v-if="result"
                        class="grid grid-cols-1 gap-6 lg:grid-cols-2"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>Ponto de virada e marco</CardTitle>
                                <CardDescription>
                                    Momento em que o patrimônio projetado supera
                                    o dinheiro pago e mês da meta financeira.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Cruzamento patrimônio x aporte
                                    </p>
                                    <p class="text-2xl font-bold">
                                        <span
                                            v-if="
                                                result.leverage.milestone
                                                    .crossing_month
                                            "
                                        >
                                            Mês
                                            {{
                                                result.leverage.milestone
                                                    .crossing_month
                                            }}
                                        </span>
                                        <span v-else>Não atingido</span>
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        <span
                                            v-if="
                                                result.leverage.milestone
                                                    .crossing_year
                                            "
                                        >
                                            Ano
                                            {{
                                                formatDecimal(
                                                    result.leverage.milestone
                                                        .crossing_year,
                                                )
                                            }}
                                        </span>
                                    </p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Meta de patrimônio
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatCurrency(
                                                result.leverage.milestone
                                                    .target_patrimony,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs"
                                    >
                                        <span
                                            v-if="
                                                result.leverage.milestone
                                                    .million_month
                                            "
                                        >
                                            Alcançada no mês
                                            {{
                                                result.leverage.milestone
                                                    .million_month
                                            }}
                                            (ano
                                            {{
                                                formatDecimal(
                                                    result.leverage.milestone
                                                        .million_year ?? 0,
                                                )
                                            }})
                                        </span>
                                        <span v-else>
                                            Meta não atingida dentro do
                                            horizonte atual.
                                        </span>
                                    </p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Patrimônio líquido final
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatCurrency(
                                                result.leverage.milestone
                                                    .final_net_patrimony,
                                            )
                                        }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Benchmark de mercado</CardTitle>
                                <CardDescription>
                                    Comparativo entre estratégia via consórcio,
                                    Selic e referência CDI.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Patrimônio projetado (consórcio)
                                    </p>
                                    <p
                                        class="text-2xl font-bold text-emerald-700"
                                    >
                                        {{
                                            formatCurrency(
                                                result.leverage.benchmark
                                                    .consortium_expected_value,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Acúmulo na Selic
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatCurrency(
                                                result.leverage.benchmark
                                                    .selic_final_value,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs"
                                    >
                                        Resultado relativo:
                                        {{
                                            (
                                                result.leverage.benchmark
                                                    .vs_selic_ratio ?? 0
                                            ).toFixed(2)
                                        }}x
                                    </p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Acúmulo CDI de referência
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatCurrency(
                                                result.leverage.benchmark
                                                    .cdi_reference_final_value,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs"
                                    >
                                        Resultado relativo:
                                        {{
                                            (
                                                result.leverage.benchmark
                                                    .vs_cdi_ratio ?? 0
                                            ).toFixed(2)
                                        }}x
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card v-if="result">
                        <CardHeader>
                            <CardTitle>Premissas da simulação</CardTitle>
                            <CardDescription>
                                Parâmetros usados pelo motor estatístico para
                                esta execução.
                            </CardDescription>
                        </CardHeader>
                        <CardContent
                            class="grid grid-cols-1 gap-3 md:grid-cols-3"
                        >
                            <div class="rounded-lg border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Estrutura do grupo
                                </p>
                                <p class="text-sm font-medium">
                                    {{
                                        result.leverage.group_assumptions
                                            .participants
                                    }}
                                    participantes,
                                    {{
                                        result.leverage.group_assumptions
                                            .draws_per_month
                                    }}
                                    sorteios/mês
                                </p>
                                <p class="text-muted-foreground mt-1 text-xs">
                                    Duração:
                                    {{
                                        result.leverage.group_assumptions
                                            .duration_months
                                    }}
                                    meses • contemplação média:
                                    {{
                                        result.leverage.group_assumptions
                                            .average_contemplation_months
                                    }}
                                    meses
                                </p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Taxas econômicas
                                </p>
                                <p class="text-sm font-medium">
                                    INCC anual:
                                    {{
                                        (
                                            result.leverage
                                                .financial_assumptions
                                                .incc_annual_rate * 100
                                        ).toFixed(2)
                                    }}%
                                </p>
                                <p class="text-muted-foreground mt-1 text-xs">
                                    CDI mensal:
                                    {{
                                        (
                                            result.leverage
                                                .financial_assumptions
                                                .cdi_monthly_rate * 100
                                        ).toFixed(2)
                                    }}% • Selic anual:
                                    {{
                                        (
                                            result.leverage
                                                .financial_assumptions
                                                .selic_annual_rate * 100
                                        ).toFixed(2)
                                    }}%
                                </p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Reprodutibilidade
                                </p>
                                <p class="text-sm font-medium">
                                    Seed:
                                    {{
                                        result.leverage.inputs.seed ??
                                        'aleatória'
                                    }}
                                </p>
                                <p class="text-muted-foreground mt-1 text-xs">
                                    Fonte:
                                    {{
                                        result.leverage.simulation.random_source
                                    }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

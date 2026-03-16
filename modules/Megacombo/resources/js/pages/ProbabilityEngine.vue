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
import { Head } from '@inertiajs/vue3';
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
}

const props = defineProps<{ defaults: Defaults }>();

const title = 'Motor de Análise de Probabilidades';

const form = ref({
    openMarket: { ...props.defaults.openMarket },
    exclusiveGroup: { ...props.defaults.exclusiveGroup },
    snowball: { ...props.defaults.snowball },
    multiplier: { ...props.defaults.multiplier },
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
            { title: 'Megacombo', href: route('dashboard') },
            { title: 'Motor de Probabilidades' },
        ]"
    >
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">
                    Motor de Análise de Probabilidades de Grupos
                </h1>
                <p class="text-muted-foreground mt-1">
                    Painel interativo para comprovar matematicamente a
                    superioridade do grupo fechado e reduzir a objeção de sorte.
                </p>
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
                </div>
            </div>
        </div>
    </AppLayout>
</template>

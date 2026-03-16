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
    creditValue: number;
    installmentValue: number;
    remainingInstallments: number;
    desiredRatePercent: number;
    propertyValue: number;
    bankMonthlyPayment: number;
    bankTermYears: number;
}

interface Constraints {
    desiredRatePercentMin: number;
    desiredRatePercentMax: number;
}

interface SimulationResponse {
    pricing: {
        present_value_debt: number;
        estimated_agio: number;
        agio_range: {
            conservative: number;
            base: number;
            optimistic: number;
        };
    };
    results: {
        comparison: {
            consortium: {
                monthly_payment: number;
                term_months: number;
                term_years: number;
                total_cost: number;
            };
            bank_financing: {
                monthly_payment: number | null;
                term_months: number | null;
                term_years: number | null;
                total_cost: number | null;
            };
            economy: {
                monthly_saving: number | null;
                term_saving_months: number | null;
                total_saving: number | null;
                saving_percentage: number | null;
                property_value: number | null;
            };
        };
    };
    formula: {
        name: string;
        expression: string;
        assumptions: string[];
    };
}

const props = defineProps<{
    defaults: Defaults;
    constraints: Constraints;
}>();

const title = 'Calculadora de Custo Financeiro';

const form = ref({
    creditValue: props.defaults.creditValue,
    installmentValue: props.defaults.installmentValue,
    remainingInstallments: props.defaults.remainingInstallments,
    desiredRatePercent: props.defaults.desiredRatePercent,
    propertyValue: props.defaults.propertyValue,
    bankMonthlyPayment: props.defaults.bankMonthlyPayment,
    bankTermYears: props.defaults.bankTermYears,
});

const sampleScenario = {
    creditValue: 500000,
    installmentValue: 3200,
    remainingInstallments: 216,
    desiredRatePercent: 0.75,
    propertyValue: 500000,
    bankMonthlyPayment: 4200,
    bankTermYears: 30,
};

const isLoading = ref(false);
const isSaving = ref(false);
const errorMessage = ref<string | null>(null);
const saveMessage = ref<string | null>(null);
const result = ref<SimulationResponse | null>(null);
const history = ref<
    Array<{
        id: number;
        title: string | null;
        inputs: {
            credit_value: number;
            installment_value: number;
            remaining_installments: number;
            discount_rate_monthly: number;
            property_value?: number;
            bank_monthly_payment?: number;
            bank_term_months?: number;
        };
        result_snapshot: SimulationResponse;
        created_at: string;
    }>
>([]);

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 2,
});

const rateRangeLabel = computed(
    () =>
        `${props.constraints.desiredRatePercentMin.toFixed(2)}% a ${props.constraints.desiredRatePercentMax.toFixed(2)}% a.m.`,
);

const toNumber = (value: string | number): number => {
    if (typeof value === 'number') {
        return value;
    }

    return Number(value.replace(',', '.'));
};

const getCsrfToken = (): string =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const loadHistory = async () => {
    try {
        const response = await fetch(
            route('api.megacombo.financial-simulations.index'),
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as {
            items?: typeof history.value;
        };
        history.value = payload.items ?? [];
    } catch {
        // Silent fail for history loading; it should not block simulation.
    }
};

const useSampleScenario = () => {
    form.value = { ...sampleScenario };
    errorMessage.value = null;
};

const exportComparisonPdf = () => {
    if (!result.value) {
        return;
    }

    const reportWindow = window.open(
        '',
        '_blank',
        'noopener,noreferrer,width=900,height=700',
    );

    if (!reportWindow) {
        errorMessage.value = 'Nao foi possivel abrir a janela de exportacao.';
        return;
    }

    const comparison = result.value.results.comparison;
    const now = new Date().toLocaleString('pt-BR');

    reportWindow.document.write(`
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title>Comparativo Financeiro Megacombo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        h1 { margin-bottom: 4px; }
        .muted { color: #6b7280; font-size: 12px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
        .card { border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; }
        .label { color: #6b7280; font-size: 12px; }
        .value { font-size: 22px; font-weight: 700; margin-top: 4px; }
        .summary { border: 1px solid #86efac; background: #f0fdf4; border-radius: 8px; padding: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px 0; font-size: 14px; }
    </style>
</head>
<body>
    <h1>Comparativo Financeiro</h1>
    <div class="muted">Gerado em ${now}</div>

    <div class="grid">
        <div class="card">
            <div class="label">Carta contemplada - Parcela</div>
            <div class="value">${currency.format(comparison.consortium.monthly_payment)}/mês</div>
            <table>
                <tr><td>Prazo</td><td>${comparison.consortium.term_years} anos</td></tr>
                <tr><td>Custo total</td><td>${currency.format(comparison.consortium.total_cost)}</td></tr>
            </table>
        </div>
        <div class="card">
            <div class="label">Financiamento bancário - Parcela</div>
            <div class="value">${currency.format(comparison.bank_financing.monthly_payment ?? 0)}/mês</div>
            <table>
                <tr><td>Prazo</td><td>${comparison.bank_financing.term_years ?? 0} anos</td></tr>
                <tr><td>Custo total</td><td>${currency.format(comparison.bank_financing.total_cost ?? 0)}</td></tr>
            </table>
        </div>
    </div>

    <div class="summary">
        <strong>Economia com carta contemplada</strong>
        <table>
            <tr><td>Economia mensal</td><td>${currency.format(comparison.economy.monthly_saving ?? 0)}</td></tr>
            <tr><td>Economia de prazo</td><td>${comparison.economy.term_saving_months ?? 0} meses</td></tr>
            <tr><td>Economia total</td><td>${currency.format(comparison.economy.total_saving ?? 0)}</td></tr>
            <tr><td>Redução percentual</td><td>${comparison.economy.saving_percentage ?? 0}%</td></tr>
        </table>
    </div>
</body>
</html>
`);
    reportWindow.document.close();
    reportWindow.focus();
    reportWindow.print();
};

const saveSimulation = async () => {
    if (!result.value) {
        return;
    }

    isSaving.value = true;
    saveMessage.value = null;

    try {
        const response = await fetch(
            route('api.megacombo.financial-simulations.store'),
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
                    title: `Simulacao ${new Date().toLocaleDateString('pt-BR')}`,
                    inputs: {
                        credit_value: form.value.creditValue,
                        installment_value: form.value.installmentValue,
                        remaining_installments:
                            form.value.remainingInstallments,
                        discount_rate_monthly:
                            form.value.desiredRatePercent / 100,
                        property_value: form.value.propertyValue,
                        bank_monthly_payment: form.value.bankMonthlyPayment,
                        bank_term_months: Math.round(
                            form.value.bankTermYears * 12,
                        ),
                    },
                    result_snapshot: result.value,
                }),
            },
        );

        if (!response.ok) {
            saveMessage.value = 'Nao foi possivel salvar a simulacao.';
            return;
        }

        saveMessage.value = 'Simulacao salva no historico.';
        await loadHistory();
    } catch {
        saveMessage.value = 'Erro ao salvar simulacao.';
    } finally {
        isSaving.value = false;
    }
};

const runSimulation = async () => {
    errorMessage.value = null;

    const creditValue = toNumber(form.value.creditValue);
    const installmentValue = toNumber(form.value.installmentValue);
    const remainingInstallments = Math.round(
        toNumber(form.value.remainingInstallments),
    );
    const desiredRatePercent = toNumber(form.value.desiredRatePercent);
    const propertyValue = toNumber(form.value.propertyValue);
    const bankMonthlyPayment = toNumber(form.value.bankMonthlyPayment);
    const bankTermYears = toNumber(form.value.bankTermYears);

    if (
        [
            creditValue,
            installmentValue,
            remainingInstallments,
            desiredRatePercent,
            propertyValue,
            bankMonthlyPayment,
            bankTermYears,
        ].some((item) => Number.isNaN(item))
    ) {
        errorMessage.value =
            'Preencha os campos numericos com valores validos.';
        return;
    }

    if (
        desiredRatePercent < props.constraints.desiredRatePercentMin ||
        desiredRatePercent > props.constraints.desiredRatePercentMax
    ) {
        errorMessage.value = `A taxa de custo financeiro deve ficar entre ${rateRangeLabel.value}.`;
        return;
    }

    isLoading.value = true;

    try {
        const response = await fetch(route('api.megacombo.simulate.price'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                credit_value: creditValue,
                installment_value: installmentValue,
                remaining_installments: remainingInstallments,
                discount_rate_monthly: desiredRatePercent / 100,
                property_value: propertyValue,
                bank_monthly_payment: bankMonthlyPayment,
                bank_term_months: Math.round(bankTermYears * 12),
            }),
        });

        if (!response.ok) {
            let payload: Record<string, unknown> | null = null;

            try {
                payload = (await response.json()) as Record<string, unknown>;
            } catch {
                payload = null;
            }

            if (response.status === 419) {
                errorMessage.value =
                    'Sessao expirada. Atualize a pagina e tente novamente.';
                return;
            }

            const errors = payload?.errors as
                | Record<string, string[]>
                | undefined;
            const firstError = errors ? Object.values(errors)[0] : null;

            errorMessage.value =
                Array.isArray(firstError) && firstError.length > 0
                    ? String(firstError[0])
                    : 'Nao foi possivel calcular com os dados informados.';
            return;
        }

        result.value = (await response.json()) as SimulationResponse;
    } catch {
        errorMessage.value =
            'Erro de comunicacao ao consultar o motor de calculo.';
    } finally {
        isLoading.value = false;
    }
};

void runSimulation();
void loadHistory();
</script>

<template>
    <AppLayout
        :title="title"
        :breadcrumbs="[
            { title: 'Megacombo', href: route('dashboard') },
            { title: 'Calculadora de Custo Financeiro' },
        ]"
    >
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">
                    Calculadora de Custo Financeiro e Comparador de Cenários
                </h1>
                <p class="text-muted-foreground mt-1">
                    Cálculo de precificação de carta contemplada com fórmula de
                    Valor Presente e comparação lado a lado contra financiamento
                    bancário.
                </p>
            </div>

            <Alert>
                <AlertTitle
                    >Precisão irrefutável contra simulação
                    equivocada</AlertTitle
                >
                <AlertDescription>
                    Esta calculadora usa PV da HP 12C para precificar a dívida
                    remanescente. Não utiliza divisão simplista de taxa
                    administrativa pelo prazo.
                </AlertDescription>
            </Alert>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
                <Card class="xl:col-span-2">
                    <CardHeader>
                        <CardTitle>Entradas da simulação</CardTitle>
                        <CardDescription>
                            Faixa de custo financeiro desejado:
                            {{ rateRangeLabel }}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Button
                            class="w-full"
                            variant="outline"
                            @click="useSampleScenario"
                        >
                            Usar cenário exemplo (R$ 500 mil)
                        </Button>

                        <div class="space-y-2">
                            <Label for="credit">Valor do crédito (R$)</Label>
                            <Input
                                id="credit"
                                v-model="form.creditValue"
                                type="number"
                                min="1"
                                step="1000"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-2">
                                <Label for="pmt">Parcela atual (PMT)</Label>
                                <Input
                                    id="pmt"
                                    v-model="form.installmentValue"
                                    type="number"
                                    min="1"
                                    step="0.01"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="n">Parcelas restantes (n)</Label>
                                <Input
                                    id="n"
                                    v-model="form.remainingInstallments"
                                    type="number"
                                    min="1"
                                    step="1"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="rate"
                                >Custo financeiro desejado (% a.m.)</Label
                            >
                            <Input
                                id="rate"
                                v-model="form.desiredRatePercent"
                                type="number"
                                min="0.6"
                                max="0.85"
                                step="0.01"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="property">Valor do imóvel (R$)</Label>
                            <Input
                                id="property"
                                v-model="form.propertyValue"
                                type="number"
                                min="1"
                                step="1000"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-2">
                                <Label for="bankMonthly"
                                    >Financiamento bancário (R$/mês)</Label
                                >
                                <Input
                                    id="bankMonthly"
                                    v-model="form.bankMonthlyPayment"
                                    type="number"
                                    min="1"
                                    step="0.01"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="bankYears"
                                    >Prazo financiamento (anos)</Label
                                >
                                <Input
                                    id="bankYears"
                                    v-model="form.bankTermYears"
                                    type="number"
                                    min="1"
                                    step="1"
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
                                    ? 'Calculando...'
                                    : 'Calcular cenário financeiro'
                            }}
                        </Button>

                        <p v-if="errorMessage" class="text-sm text-red-600">
                            {{ errorMessage }}
                        </p>
                    </CardContent>
                </Card>

                <div class="space-y-6 xl:col-span-3">
                    <Card v-if="result">
                        <CardHeader>
                            <CardTitle
                                >Precificação da carta contemplada</CardTitle
                            >
                            <CardDescription>{{
                                result.formula.name
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        PV da dívida
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            currency.format(
                                                result.pricing
                                                    .present_value_debt,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="rounded-lg border p-3">
                                    <p class="text-muted-foreground text-xs">
                                        Ágio/Lucro estimado
                                    </p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            currency.format(
                                                result.pricing.estimated_agio,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-lg border p-3">
                                <p class="text-muted-foreground text-xs">
                                    Faixa de ágio por sensibilidade de taxa
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <Badge variant="outline"
                                        >Conservador:
                                        {{
                                            currency.format(
                                                result.pricing.agio_range
                                                    .conservative,
                                            )
                                        }}</Badge
                                    >
                                    <Badge variant="outline"
                                        >Base:
                                        {{
                                            currency.format(
                                                result.pricing.agio_range.base,
                                            )
                                        }}</Badge
                                    >
                                    <Badge variant="outline"
                                        >Otimista:
                                        {{
                                            currency.format(
                                                result.pricing.agio_range
                                                    .optimistic,
                                            )
                                        }}</Badge
                                    >
                                </div>
                            </div>

                            <div
                                class="bg-muted/40 rounded-lg border p-3 text-xs"
                            >
                                <p class="font-medium">Fórmula:</p>
                                <p>{{ result.formula.expression }}</p>
                                <p class="mt-1">
                                    Hipóteses:
                                    {{ result.formula.assumptions.join(' | ') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="result">
                        <CardHeader>
                            <CardTitle>Comparador visual de cenários</CardTitle>
                            <CardDescription>
                                Compra de imóvel com carta contemplada versus
                                financiamento bancário.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="rounded-lg border p-4">
                                    <p class="text-sm font-medium">
                                        Carta contemplada
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs"
                                    >
                                        {{
                                            currency.format(form.propertyValue)
                                        }}
                                        de imóvel
                                    </p>
                                    <p class="mt-3 text-xl font-semibold">
                                        {{
                                            currency.format(
                                                result.results.comparison
                                                    .consortium.monthly_payment,
                                            )
                                        }}/mês
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        {{
                                            result.results.comparison.consortium
                                                .term_years
                                        }}
                                        anos
                                    </p>
                                    <p class="mt-3 text-sm">
                                        Custo total:
                                        <strong>{{
                                            currency.format(
                                                result.results.comparison
                                                    .consortium.total_cost,
                                            )
                                        }}</strong>
                                    </p>
                                </div>

                                <div class="rounded-lg border p-4">
                                    <p class="text-sm font-medium">
                                        Financiamento bancário
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs"
                                    >
                                        Referência de mercado
                                    </p>
                                    <p class="mt-3 text-xl font-semibold">
                                        {{
                                            currency.format(
                                                result.results.comparison
                                                    .bank_financing
                                                    .monthly_payment ?? 0,
                                            )
                                        }}/mês
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        {{
                                            result.results.comparison
                                                .bank_financing.term_years ?? 0
                                        }}
                                        anos
                                    </p>
                                    <p class="mt-3 text-sm">
                                        Custo total:
                                        <strong>{{
                                            currency.format(
                                                result.results.comparison
                                                    .bank_financing
                                                    .total_cost ?? 0,
                                            )
                                        }}</strong>
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-4 rounded-lg border border-emerald-500/40 bg-emerald-50/40 p-4 dark:bg-emerald-900/10"
                            >
                                <p class="text-sm font-medium">
                                    Economia gerada pela carta contemplada
                                </p>
                                <div
                                    class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3"
                                >
                                    <p class="text-sm">
                                        Mensal:
                                        <strong>{{
                                            currency.format(
                                                result.results.comparison
                                                    .economy.monthly_saving ??
                                                    0,
                                            )
                                        }}</strong>
                                    </p>
                                    <p class="text-sm">
                                        Prazo:
                                        <strong
                                            >{{
                                                result.results.comparison
                                                    .economy
                                                    .term_saving_months ?? 0
                                            }}
                                            meses</strong
                                        >
                                    </p>
                                    <p class="text-sm">
                                        Total:
                                        <strong>{{
                                            currency.format(
                                                result.results.comparison
                                                    .economy.total_saving ?? 0,
                                            )
                                        }}</strong>
                                    </p>
                                </div>
                                <p class="text-muted-foreground mt-2 text-xs">
                                    Redução percentual estimada:
                                    {{
                                        result.results.comparison.economy
                                            .saving_percentage ?? 0
                                    }}%
                                </p>
                            </div>

                            <div
                                class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2"
                            >
                                <Button
                                    :disabled="isSaving"
                                    variant="outline"
                                    @click="saveSimulation"
                                >
                                    {{
                                        isSaving
                                            ? 'Salvando...'
                                            : 'Salvar simulação'
                                    }}
                                </Button>
                                <Button @click="exportComparisonPdf"
                                    >Exportar comparativo em PDF</Button
                                >
                            </div>

                            <p
                                v-if="saveMessage"
                                class="text-muted-foreground mt-2 text-sm"
                            >
                                {{ saveMessage }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle
                                >Histórico das últimas simulações</CardTitle
                            >
                            <CardDescription
                                >Registro por usuário para consulta comercial
                                rápida.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <div
                                v-if="history.length === 0"
                                class="text-muted-foreground text-sm"
                            >
                                Nenhuma simulação salva ainda.
                            </div>
                            <div
                                v-for="item in history"
                                :key="item.id"
                                class="rounded-lg border p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <p class="font-medium">
                                        {{
                                            item.title ??
                                            `Simulação #${item.id}`
                                        }}
                                    </p>
                                    <Badge variant="outline">{{
                                        item.created_at
                                    }}</Badge>
                                </div>
                                <p class="text-muted-foreground mt-1 text-xs">
                                    Crédito
                                    {{
                                        currency.format(
                                            item.inputs.credit_value,
                                        )
                                    }}
                                    | Parcela
                                    {{
                                        currency.format(
                                            item.inputs.installment_value,
                                        )
                                    }}
                                    | n={{ item.inputs.remaining_installments }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

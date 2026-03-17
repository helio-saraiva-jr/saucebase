<?php

namespace Modules\Megacombo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Megacombo\Models\Lead;
use Modules\Megacombo\Models\Quota;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController
{
    public function postSaleCenter(): Response
    {
        $alerts = DB::table('megacombo_alert_logs')
            ->leftJoin('megacombo_quotas', 'megacombo_alert_logs.quota_id', '=', 'megacombo_quotas.id')
            ->leftJoin('megacombo_leads', 'megacombo_quotas.lead_id', '=', 'megacombo_leads.id')
            ->select([
                'megacombo_alert_logs.id',
                'megacombo_alert_logs.event_type',
                'megacombo_alert_logs.status',
                'megacombo_alert_logs.payload',
                'megacombo_alert_logs.created_at',
                'megacombo_quotas.group_code',
                'megacombo_quotas.quota_number',
                'megacombo_quotas.credit_value',
                'megacombo_leads.name as client_name',
            ])
            ->orderByDesc('megacombo_alert_logs.id')
            ->limit(12)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'event_type' => $row->event_type,
                    'status' => $row->status,
                    'created_at' => $row->created_at,
                    'group_code' => $row->group_code,
                    'quota_number' => $row->quota_number,
                    'credit_value' => $row->credit_value,
                    'client_name' => $row->client_name,
                    'payload' => $row->payload,
                ];
            })
            ->toArray();

        $priorityAlert = collect($alerts)->firstWhere('event_type', 'contemplated_detected');

        return Inertia::render('Megacombo::PostSaleCenter', [
            'kpis' => [
                'queuedAlerts' => DB::table('megacombo_alert_logs')->where('status', 'queued')->count(),
                'processedAlerts' => DB::table('megacombo_alert_logs')->where('status', 'processed')->count(),
                'contemplatedQuotas' => Quota::query()->whereNotNull('contemplated_at')->count(),
                'monthlyQueueDemand' => 30000000,
            ],
            'priorityAlert' => $priorityAlert,
            'alerts' => $alerts,
            'workflow' => [
                [
                    'title' => 'Acionar mesa Mais Valor Consorcios',
                    'description' => 'Precificar a carta contemplada com custo financeiro alvo e margem operacional.',
                ],
                [
                    'title' => 'Buscar comprador em fila de espera',
                    'description' => 'Executar oferta ativa na demanda estimada de R$ 30M/mes para reduzir tempo de venda.',
                ],
                [
                    'title' => 'Garantia juridica da operacao',
                    'description' => 'Liberar assinatura somente com valor total na conta juridica e compliance concluido.',
                ],
                [
                    'title' => 'Reinvestimento do lucro',
                    'description' => 'Sugerir reinvestimento em novas cotas para manter efeito de alavancagem patrimonial.',
                ],
            ],
        ]);
    }

    public function clientPortal(Request $request): Response
    {
        $clientUser = $request->user();

        $quotasQuery = Quota::query()
            ->leftJoin('megacombo_leads', 'megacombo_quotas.lead_id', '=', 'megacombo_leads.id')
            ->select([
                'megacombo_quotas.id',
                'megacombo_quotas.group_code',
                'megacombo_quotas.quota_number',
                'megacombo_quotas.credit_value',
                'megacombo_quotas.installment_value',
                'megacombo_quotas.remaining_installments',
                'megacombo_quotas.status',
                'megacombo_leads.name as client_name',
                'megacombo_leads.risk_profile as risk_profile',
                'megacombo_leads.email as client_email',
            ])
            ->orderByDesc('megacombo_quotas.id');

        if (is_string($clientUser?->email) && $clientUser->email !== '') {
            $quotasQuery->where('megacombo_leads.email', $clientUser->email);
        }

        $rawQuotas = $quotasQuery->get();

        if ($rawQuotas->isEmpty()) {
            $rawQuotas = Quota::query()
                ->leftJoin('megacombo_leads', 'megacombo_quotas.lead_id', '=', 'megacombo_leads.id')
                ->select([
                    'megacombo_quotas.id',
                    'megacombo_quotas.group_code',
                    'megacombo_quotas.quota_number',
                    'megacombo_quotas.status',
                    'megacombo_leads.name as client_name',
                    'megacombo_leads.risk_profile as risk_profile',
                ])
                ->orderByDesc('megacombo_quotas.id')
                ->limit(1)
                ->get();
        }

        // Business rule: each quota has fixed contractual values.
        $quotaCreditValue = 100000.0;
        $quotaInstallmentValue = 644.02;
        $quotaTermMonths = 216;

        $quotas = $rawQuotas
            ->map(function ($quota) use ($quotaCreditValue, $quotaInstallmentValue, $quotaTermMonths, $clientUser) {
                return [
                    'id' => $quota->id,
                    'group_code' => $quota->group_code,
                    'quota_number' => $quota->quota_number,
                    'credit_value' => round($quotaCreditValue, 2),
                    'installment_value' => round($quotaInstallmentValue, 2),
                    'remaining_installments' => $quotaTermMonths,
                    'status' => $quota->status ?? 'active',
                    'client_name' => $quota->client_name ?? $clientUser?->name ?? 'Cliente Megacombo',
                ];
            })
            ->values();

        $quotaCount = max($quotas->count(), 1);

        $scenarioProfiles = [
            'conservador' => [
                'label' => 'Conservador',
                'incc_monthly_rate' => 0.0032,
                'projected_agio_rate' => 0.04,
            ],
            'equilibrado' => [
                'label' => 'Equilibrado',
                'incc_monthly_rate' => 0.0040,
                'projected_agio_rate' => 0.08,
            ],
            'arrojado' => [
                'label' => 'Arrojado',
                'incc_monthly_rate' => 0.0048,
                'projected_agio_rate' => 0.12,
            ],
        ];

        $requestedProfile = trim((string) $request->query('profile', ''));
        $activeProfileKey = $this->resolveScenarioProfileKey(
            $requestedProfile !== '' ? $requestedProfile : (string) ($rawQuotas->first()->risk_profile ?? ''),
            array_keys($scenarioProfiles)
        );
        $activeScenario = $scenarioProfiles[$activeProfileKey];

        $creditValue = $quotaCreditValue * $quotaCount;
        $installmentValue = $quotaInstallmentValue * $quotaCount;
        $remainingInstallments = $quotaTermMonths;
        $clientName = (string) ($quotas->first()['client_name'] ?? $clientUser?->name ?? 'Cliente Megacombo');
        $assembliesCount = $quotas
            ->pluck('group_code')
            ->filter(fn ($groupCode) => is_string($groupCode) && $groupCode !== '')
            ->unique()
            ->count();
        $inccMonthlyRate = (float) $activeScenario['incc_monthly_rate'];
        $selicReference = $this->resolveSelicAnnualRate();
        $selicAnnualRate = $selicReference['annual_rate'];
        $selicMonthlyRate = pow(1 + $selicAnnualRate, 1 / 12) - 1;
        $projectedAgioRate = (float) $activeScenario['projected_agio_rate'];
        $years = max((int) ceil($remainingInstallments / 12), 1);

        $evolution = [];
        $turningPointTimeline = [];
        $totalDebtAtStart = $installmentValue * $remainingInstallments;

        for ($year = 1; $year <= $years; $year++) {
            $months = min($year * 12, $remainingInstallments);
            $paid = $installmentValue * $months;
            $correctedCredit = $creditValue * pow(1 + $inccMonthlyRate, $months);
            $outstandingDebt = max($totalDebtAtStart - $paid, 0.0);
            $totalObligation = $paid + $outstandingDebt;
            $turningEdge = $correctedCredit - $totalObligation;

            $evolution[] = [
                'label' => 'Ano ' . $year,
                'months' => $months,
                'paid_total' => round($paid, 2),
                'corrected_credit' => round($correctedCredit, 2),
                'patrimony_gap' => round($correctedCredit - $paid, 2),
            ];

            $turningPointTimeline[] = [
                'label' => 'Ano ' . $year,
                'year' => $year,
                'months' => $months,
                'credit_value' => round($correctedCredit, 2),
                'outstanding_debt' => round($outstandingDebt, 2),
                'paid_total' => round($paid, 2),
                'total_obligation' => round($totalObligation, 2),
                'turning_edge' => round($turningEdge, 2),
                'is_turning_point' => $turningEdge >= 0,
            ];
        }

        $shadowPortfolio = [];

        for ($year = 1; $year <= $years; $year++) {
            $months = min($year * 12, $remainingInstallments);
            $consorcioCorrectedCredit = $creditValue * pow(1 + $inccMonthlyRate, $months);
            $consorcioProjectedSale = $consorcioCorrectedCredit * (1 + $projectedAgioRate);

            $selicProjection = $selicMonthlyRate > 0
                ? $installmentValue * ((pow(1 + $selicMonthlyRate, $months) - 1) / $selicMonthlyRate)
                : $installmentValue * $months;

            $edge = $consorcioProjectedSale - $selicProjection;

            $shadowPortfolio[] = [
                'label' => 'Ano ' . $year,
                'months' => $months,
                'consorcio_value' => round($consorcioProjectedSale, 2),
                'selic_value' => round($selicProjection, 2),
                'edge_value' => round($edge, 2),
            ];
        }

        $shadowLastPoint = $shadowPortfolio[count($shadowPortfolio) - 1] ?? null;
        $turningPoint = collect($turningPointTimeline)->first(fn ($point) => $point['is_turning_point'] === true);
        $turningPointLast = $turningPointTimeline[count($turningPointTimeline) - 1] ?? null;
        $turningPointMarginPercent =
            is_array($turningPointLast) && $turningPointLast['total_obligation'] > 0
                ? (($turningPointLast['credit_value'] - $turningPointLast['total_obligation']) / $turningPointLast['total_obligation']) * 100
                : 0.0;
        $edgePercent = $shadowLastPoint && $shadowLastPoint['selic_value'] > 0
            ? (($shadowLastPoint['consorcio_value'] - $shadowLastPoint['selic_value']) / $shadowLastPoint['selic_value']) * 100
            : 0.0;

        $winningStreakYears = 0;
        foreach ($shadowPortfolio as $point) {
            if ($point['edge_value'] <= 0) {
                break;
            }
            $winningStreakYears++;
        }

        $dailyInccRate = pow(1 + $inccMonthlyRate, 1 / 30) - 1;
        $dailySelicRate = pow(1 + $selicMonthlyRate, 1 / 30) - 1;

        $level = 'Em aquecimento';
        if ($edgePercent >= 20) {
            $level = 'Nivel elite';
        } elseif ($edgePercent >= 10) {
            $level = 'Nivel avancado';
        } elseif ($edgePercent >= 0) {
            $level = 'Nivel consistente';
        }

        $whatsappNumber = preg_replace('/\D+/', '', (string) env('WHATSAPP_SUPPORT_NUMBER', '5511999999999'));
        $acquireMoreMessage = rawurlencode('Ola, quero adquirir mais cotas de consorcio e falar com o especialista.');
        $acquireMoreUrl = $whatsappNumber !== ''
            ? "https://wa.me/{$whatsappNumber}?text={$acquireMoreMessage}"
            : null;

        return Inertia::render('Megacombo::ClientPortal', [
            'portfolio' => [
                'client_name' => $clientName,
                'quota_count' => $quotaCount,
                'assemblies_count' => $assembliesCount,
                'quota_credit_value' => round($quotaCreditValue, 2),
                'quota_installment_value' => round($quotaInstallmentValue, 2),
                'quota_term_months' => $quotaTermMonths,
                'credit_value_total' => round($creditValue, 2),
                'installment_value_total' => round($installmentValue, 2),
            ],
            'quotas' => $quotas,
            'acquireMoreAction' => [
                'label' => 'Adquirir mais cotas',
                'url' => $acquireMoreUrl,
                'enabled' => $acquireMoreUrl !== null,
            ],
            'inccMonthlyRate' => $inccMonthlyRate,
            'selicAnnualRate' => $selicAnnualRate,
            'selicSource' => [
                'provider' => $selicReference['provider'],
                'series' => $selicReference['series'],
                'reference_date' => $selicReference['reference_date'],
                'is_fallback' => $selicReference['is_fallback'],
            ],
            'scenario' => [
                'active' => $activeProfileKey,
                'options' => collect($scenarioProfiles)
                    ->map(fn (array $config, string $key) => [
                        'key' => $key,
                        'label' => $config['label'],
                        'incc_monthly_rate' => $config['incc_monthly_rate'],
                        'projected_agio_rate' => $config['projected_agio_rate'],
                    ])
                    ->values()
                    ->all(),
            ],
            'projectedAgioRate' => $projectedAgioRate,
            'evolution' => $evolution,
            'turningPoint' => [
                'timeline' => $turningPointTimeline,
                'summary' => [
                    'found' => is_array($turningPoint),
                    'year' => is_array($turningPoint) ? $turningPoint['year'] : null,
                    'months' => is_array($turningPoint) ? $turningPoint['months'] : null,
                    'credit_value' => is_array($turningPoint) ? $turningPoint['credit_value'] : null,
                    'total_obligation' => is_array($turningPoint) ? $turningPoint['total_obligation'] : null,
                    'turning_edge' => is_array($turningPoint) ? $turningPoint['turning_edge'] : null,
                    'latest_margin_percent' => round($turningPointMarginPercent, 2),
                ],
            ],
            'shadowPortfolio' => $shadowPortfolio,
            'shadowSummary' => [
                'generated_at' => now()->toIso8601String(),
                'horizon_months' => $shadowLastPoint['months'] ?? 0,
                'consorcio_value' => $shadowLastPoint['consorcio_value'] ?? 0.0,
                'selic_value' => $shadowLastPoint['selic_value'] ?? 0.0,
                'edge_value' => $shadowLastPoint['edge_value'] ?? 0.0,
                'edge_percent' => round($edgePercent, 2),
            ],
            'gamification' => [
                'level' => $level,
                'winning_streak_years' => $winningStreakYears,
                'daily_growth_consorcio' => round($creditValue * $dailyInccRate, 2),
                'daily_growth_selic' => round(($shadowLastPoint['selic_value'] ?? 0.0) * $dailySelicRate, 2),
            ],
        ]);
    }

    /**
     * Resolve annual Selic rate from BACEN SGS (free public API).
     * Falls back to a stable default when the API is unavailable.
     *
     * @return array{annual_rate: float, provider: string, series: string, reference_date: string|null, is_fallback: bool}
     */
    private function resolveSelicAnnualRate(): array
    {
        $fallback = [
            'annual_rate' => 0.1175,
            'provider' => 'BACEN SGS',
            'series' => '11',
            'reference_date' => null,
            'is_fallback' => true,
        ];

        $cached = Cache::remember('megacombo.selic.sgs11.latest', now()->addHours(12), function () {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://api.bcb.gov.br/dados/serie/bcdata.sgs.11/dados', [
                    'formato' => 'json',
                    'dataInicial' => now()->subDays(30)->format('d/m/Y'),
                    'dataFinal' => now()->format('d/m/Y'),
                ]);

            if (! $response->successful()) {
                return null;
            }

            $latest = collect($response->json())
                ->filter(fn ($row) => isset($row['valor']) && is_string($row['valor']))
                ->last();

            if (! is_array($latest) || ! isset($latest['valor'])) {
                return null;
            }

            $ratePercent = (float) str_replace(',', '.', (string) $latest['valor']);

            if ($ratePercent <= 0) {
                return null;
            }

            return [
                'annual_rate' => $ratePercent / 100,
                'provider' => 'BACEN SGS',
                'series' => '11',
                'reference_date' => isset($latest['data']) ? (string) $latest['data'] : null,
                'is_fallback' => false,
            ];
        });

        if (! is_array($cached) || ! isset($cached['annual_rate'])) {
            return $fallback;
        }

        return [
            'annual_rate' => (float) $cached['annual_rate'],
            'provider' => (string) ($cached['provider'] ?? $fallback['provider']),
            'series' => (string) ($cached['series'] ?? $fallback['series']),
            'reference_date' => isset($cached['reference_date']) ? (string) $cached['reference_date'] : null,
            'is_fallback' => (bool) ($cached['is_fallback'] ?? false),
        ];
    }

    /**
     * @param array<int, string> $allowedKeys
     */
    private function resolveScenarioProfileKey(string $rawProfile, array $allowedKeys): string
    {
        $normalized = mb_strtolower(trim($rawProfile));

        $aliases = [
            'conservador' => 'conservador',
            'conservative' => 'conservador',
            'equilibrado' => 'equilibrado',
            'balanced' => 'equilibrado',
            'moderado' => 'equilibrado',
            'arrojado' => 'arrojado',
            'agressivo' => 'arrojado',
            'aggressive' => 'arrojado',
        ];

        $resolved = $aliases[$normalized] ?? 'equilibrado';

        if (! in_array($resolved, $allowedKeys, true)) {
            return 'equilibrado';
        }

        return $resolved;
    }

    public function probabilityEngine(): Response
    {
        return Inertia::render('Megacombo::ProbabilityEngine', [
            'audience' => 'representative',
            'quickLinks' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Calculadora financeira', 'url' => route('megacombo.financial-calculator')],
            ],
            'defaults' => [
                'openMarket' => [
                    'participants' => 3000,
                    'durationMonths' => 200,
                    'drawsPerMonth' => 1,
                ],
                'exclusiveGroup' => [
                    'participants' => 650,
                    'durationMonths' => 216,
                    'drawsPerMonth' => 2,
                ],
                'snowball' => [
                    'baseYearsWithoutBids' => 9,
                    'targetReductionRate' => 0.3333,
                    'freeBidsPerYear' => 6,
                ],
                'multiplier' => [
                    'quotas' => 5,
                    'horizonYears' => 3,
                ],
                'leverage' => [
                    'monthlyCapacity' => 6500,
                    'targetPatrimony' => 1000000,
                    'seed' => null,
                ],
            ],
        ]);
    }

    public function clientProbabilityEngine(): Response
    {
        return Inertia::render('Megacombo::ProbabilityEngine', [
            'audience' => 'client',
            'quickLinks' => [
                ['label' => 'Portal do cliente', 'url' => route('megacombo.client-portal')],
                ['label' => 'Calculadora financeira', 'url' => route('megacombo.client-financial-calculator')],
            ],
            'defaults' => [
                'openMarket' => [
                    'participants' => 3000,
                    'durationMonths' => 200,
                    'drawsPerMonth' => 1,
                ],
                'exclusiveGroup' => [
                    'participants' => 650,
                    'durationMonths' => 216,
                    'drawsPerMonth' => 2,
                ],
                'snowball' => [
                    'baseYearsWithoutBids' => 9,
                    'targetReductionRate' => 0.3333,
                    'freeBidsPerYear' => 6,
                ],
                'multiplier' => [
                    'quotas' => 5,
                    'horizonYears' => 3,
                ],
                'leverage' => [
                    'monthlyCapacity' => 6500,
                    'targetPatrimony' => 1000000,
                    'seed' => null,
                ],
            ],
        ]);
    }

    public function financialCalculator(): Response
    {
        return Inertia::render('Megacombo::FinancialCalculator', [
            'audience' => 'representative',
            'quickLinks' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Probabilidades', 'url' => route('megacombo.probability-engine')],
            ],
            'defaults' => [
                'creditValue' => 500000,
                'installmentValue' => 3200,
                'remainingInstallments' => 216,
                'desiredRatePercent' => 0.75,
                'propertyValue' => 500000,
                'bankMonthlyPayment' => 4200,
                'bankTermYears' => 30,
            ],
            'constraints' => [
                'desiredRatePercentMin' => 0.60,
                'desiredRatePercentMax' => 0.85,
            ],
        ]);
    }

    public function clientFinancialCalculator(): Response
    {
        return Inertia::render('Megacombo::FinancialCalculator', [
            'audience' => 'client',
            'quickLinks' => [
                ['label' => 'Portal do cliente', 'url' => route('megacombo.client-portal')],
                ['label' => 'Motor de probabilidades', 'url' => route('megacombo.client-probability-engine')],
            ],
            'defaults' => [
                'creditValue' => 500000,
                'installmentValue' => 3200,
                'remainingInstallments' => 216,
                'desiredRatePercent' => 0.75,
                'propertyValue' => 500000,
                'bankMonthlyPayment' => 4200,
                'bankTermYears' => 30,
            ],
            'constraints' => [
                'desiredRatePercentMin' => 0.60,
                'desiredRatePercentMax' => 0.85,
            ],
        ]);
    }

    public function aiSpecialist(Request $request): Response
    {
        $isClient = $request->user()?->hasRole('user') === true;

        return Inertia::render('Megacombo::AiSpecialist', [
            'audience' => $isClient ? 'client' : 'representative',
            'quickLinks' => $isClient
                ? [
                    ['label' => 'Dashboard', 'url' => route('megacombo.client-portal')],
                    ['label' => 'Calculadora financeira', 'url' => route('megacombo.client-financial-calculator')],
                    ['label' => 'Motor de probabilidades', 'url' => route('megacombo.client-probability-engine')],
                ]
                : [
                    ['label' => 'Dashboard', 'url' => route('dashboard')],
                    ['label' => 'Calculadora financeira', 'url' => route('megacombo.financial-calculator')],
                    ['label' => 'Motor de probabilidades', 'url' => route('megacombo.probability-engine')],
                ],
            'suggestions' => [
                'Qual o melhor cenário para acelerar a contemplação sem comprometer caixa?',
                'Como comparar minha carteira atual de cotas com uma estratégia alternativa?',
                'Quais ajustes de perfil reduzem risco e preservam valorização?',
                'Como explicar o ponto de virada para um cliente em 2 minutos?',
            ],
        ]);
    }

    public function operation(): Response
    {
        $contingencyQuota = Quota::query()
            ->whereNotNull('contemplated_at')
            ->latest('contemplated_at')
            ->first(['id', 'group_code', 'quota_number', 'credit_value', 'contemplated_at']);

        $recentAlerts = DB::table('megacombo_alert_logs')
            ->leftJoin('megacombo_quotas', 'megacombo_alert_logs.quota_id', '=', 'megacombo_quotas.id')
            ->select([
                'megacombo_alert_logs.id',
                'megacombo_alert_logs.event_type',
                'megacombo_alert_logs.status',
                'megacombo_alert_logs.created_at',
                'megacombo_quotas.group_code',
                'megacombo_quotas.quota_number',
            ])
            ->orderByDesc('megacombo_alert_logs.id')
            ->limit(8)
            ->get();

        return Inertia::render('Megacombo::Operation', [
            'kpis' => [
                'activeQuotas' => Quota::query()->whereNull('contemplated_at')->count(),
                'contemplatedQuotas' => Quota::query()->whereNotNull('contemplated_at')->count(),
                'openLeads' => Lead::query()->count(),
            ],
            'contingencyQuota' => $contingencyQuota,
            'recentAlerts' => $recentAlerts,
        ]);
    }

    public function newAction(): Response
    {
        $priorityLeads = Lead::query()
            ->orderByDesc('fit_score')
            ->limit(5)
            ->get(['id', 'name', 'objective', 'fit_score', 'recommended_flow']);

        return Inertia::render('Megacombo::NewAction', [
            'priorityLeads' => $priorityLeads,
            'channels' => [
                ['id' => 'whatsapp', 'label' => 'WhatsApp'],
                ['id' => 'telefone', 'label' => 'Telefone'],
                ['id' => 'email', 'label' => 'Email'],
            ],
            'campaignTemplates' => [
                ['id' => 'reativacao', 'name' => 'Reativacao de cota contemplada'],
                ['id' => 'educacao', 'name' => 'Educacao financeira para novo lead'],
                ['id' => 'urgencia', 'name' => 'Oferta com janela de oportunidade'],
            ],
        ]);
    }

    public function pipeline(): Response
    {
        $leads = Lead::query()
            ->latest()
            ->limit(20)
            ->get(['id', 'name', 'objective', 'fit_score', 'recommended_flow', 'created_at']);

        $flows = $leads
            ->groupBy(fn (Lead $lead) => $lead->recommended_flow ?: 'nao-definido')
            ->map(fn ($group) => $group->count())
            ->toArray();

        return Inertia::render('Megacombo::Pipeline', [
            'leads' => $leads,
            'flowSummary' => $flows,
        ]);
    }
}

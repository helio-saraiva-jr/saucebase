<?php

namespace Modules\Megacombo\Http\Controllers;

use Illuminate\Support\Facades\DB;
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

    public function clientPortal(): Response
    {
        $quota = Quota::query()
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
            ])
            ->latest('megacombo_quotas.id')
            ->first();

        $creditValue = $quota ? (float) $quota->credit_value : 500000.0;
        $installmentValue = $quota ? (float) $quota->installment_value : 3200.0;
        $remainingInstallments = $quota ? (int) $quota->remaining_installments : 216;
        $inccMonthlyRate = 0.004;
        $years = max((int) ceil($remainingInstallments / 12), 1);

        $evolution = [];

        for ($year = 1; $year <= $years; $year++) {
            $months = min($year * 12, $remainingInstallments);
            $paid = $installmentValue * $months;
            $correctedCredit = $creditValue * pow(1 + $inccMonthlyRate, $months);

            $evolution[] = [
                'label' => 'Ano ' . $year,
                'months' => $months,
                'paid_total' => round($paid, 2),
                'corrected_credit' => round($correctedCredit, 2),
                'patrimony_gap' => round($correctedCredit - $paid, 2),
            ];
        }

        return Inertia::render('Megacombo::ClientPortal', [
            'quota' => [
                'id' => $quota?->id,
                'group_code' => $quota?->group_code,
                'quota_number' => $quota?->quota_number,
                'credit_value' => round($creditValue, 2),
                'installment_value' => round($installmentValue, 2),
                'remaining_installments' => $remainingInstallments,
                'status' => $quota?->status ?? 'active',
                'client_name' => $quota?->client_name ?? 'Cliente Megacombo',
            ],
            'inccMonthlyRate' => $inccMonthlyRate,
            'evolution' => $evolution,
        ]);
    }

    public function probabilityEngine(): Response
    {
        return Inertia::render('Megacombo::ProbabilityEngine', [
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
            ],
        ]);
    }

    public function financialCalculator(): Response
    {
        return Inertia::render('Megacombo::FinancialCalculator', [
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

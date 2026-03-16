<?php

namespace Modules\Megacombo\Http\Controllers;

use Modules\Megacombo\Models\Lead;
use Modules\Megacombo\Models\Quota;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __invoke(): Response
    {
        $recentLeads = Lead::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'objective', 'fit_score', 'recommended_flow'])
            ->toArray();

        return Inertia::render('Megacombo::Dashboard', [
            'defaultFinancialScenario' => [
                'creditValue' => 100000,
                'installmentValue' => 644.02,
                'remainingInstallments' => 215,
                'discountRateMonthly' => 0.0065,
            ],
            'defaultProbabilityScenario' => [
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
            ],
            'kpis' => [
                'leads' => Lead::query()->count(),
                'quotas' => Quota::query()->count(),
                'contemplatedQuotas' => Quota::query()->whereNotNull('contemplated_at')->count(),
            ],
            'recentLeads' => $recentLeads,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Megacombo\Models\Lead;
use Modules\Megacombo\Models\Quota;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole('user')) {
            return redirect()->route('megacombo.client-portal');
        }

        $allowedPeriods = [7, 30, 90];
        $periodDays = (int) $request->integer('period', 30);

        if (!in_array($periodDays, $allowedPeriods, true)) {
            $periodDays = 30;
        }

        $sinceDate = now()->subDays($periodDays);

        $leadsQuery = Lead::query()->where('created_at', '>=', $sinceDate);
        $quotasQuery = Quota::query()->where('created_at', '>=', $sinceDate);

        $recentLeads = (clone $leadsQuery)
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'objective', 'fit_score', 'recommended_flow'])
            ->toArray();

        $recentAlerts = DB::table('megacombo_alert_logs')
            ->leftJoin('megacombo_quotas', 'megacombo_alert_logs.quota_id', '=', 'megacombo_quotas.id')
            ->where('megacombo_alert_logs.created_at', '>=', $sinceDate)
            ->select([
                'megacombo_alert_logs.id',
                'megacombo_alert_logs.event_type',
                'megacombo_alert_logs.status',
                'megacombo_alert_logs.created_at',
                'megacombo_quotas.group_code',
                'megacombo_quotas.quota_number',
            ])
            ->orderByDesc('megacombo_alert_logs.id')
            ->limit(6)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'event_type' => $row->event_type,
                    'status' => $row->status,
                    'created_at' => $row->created_at,
                    'group_code' => $row->group_code,
                    'quota_number' => $row->quota_number,
                ];
            })
            ->toArray();

        $avgFitScore = (float) (clone $leadsQuery)->avg('fit_score');
        $totalCredit = (float) (clone $quotasQuery)->sum('credit_value');
        $activeQuotas = (clone $quotasQuery)->whereNull('contemplated_at')->count();
        $contemplatedQuotas = (clone $quotasQuery)->whereNotNull('contemplated_at')->count();

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
                'leads' => (clone $leadsQuery)->count(),
                'quotas' => (clone $quotasQuery)->count(),
                'contemplatedQuotas' => $contemplatedQuotas,
                'activeQuotas' => $activeQuotas,
                'avgFitScore' => round($avgFitScore, 1),
                'totalCredit' => round($totalCredit, 2),
            ],
            'periodDays' => $periodDays,
            'periodOptions' => $allowedPeriods,
            'recentLeads' => $recentLeads,
            'recentAlerts' => $recentAlerts,
        ]);
    }
}

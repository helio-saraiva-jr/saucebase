<?php

namespace Modules\Megacombo\Services;

class FinancialSimulationService
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function simulatePrice(array $input): array
    {
        $creditValue = (float) $input['credit_value'];
        $installmentValue = (float) $input['installment_value'];
        $remainingInstallments = (int) $input['remaining_installments'];
        $rate = (float) $input['discount_rate_monthly'];
        $propertyValue = isset($input['property_value']) ? (float) $input['property_value'] : null;

        $pv = $this->presentValue($installmentValue, $remainingInstallments, $rate);
        $estimatedAgio = $creditValue - $pv;

        $conservativePv = $this->presentValue($installmentValue, $remainingInstallments, max($rate - 0.001, 0.0));
        $optimisticPv = $this->presentValue($installmentValue, $remainingInstallments, $rate + 0.001);

        $bankMonthly = isset($input['bank_monthly_payment']) ? (float) $input['bank_monthly_payment'] : null;
        $bankTerm = isset($input['bank_term_months']) ? (int) $input['bank_term_months'] : null;

        $consortiumTotal = $installmentValue * $remainingInstallments + max($estimatedAgio, 0.0);
        $bankTotal = $bankMonthly !== null && $bankTerm !== null ? $bankMonthly * $bankTerm : null;
        $totalSaving = $bankTotal !== null ? $bankTotal - $consortiumTotal : null;
        $monthlySaving = $bankMonthly !== null ? $bankMonthly - $installmentValue : null;
        $termSavingMonths = $bankTerm !== null ? $bankTerm - $remainingInstallments : null;
        $savingPercentage = $bankTotal !== null && $bankTotal > 0
            ? ($totalSaving / $bankTotal) * 100
            : null;

        return [
            'inputs' => [
                'credit_value' => $creditValue,
                'installment_value' => $installmentValue,
                'remaining_installments' => $remainingInstallments,
                'discount_rate_monthly' => $rate,
                'property_value' => $propertyValue,
                'bank_monthly_payment' => $bankMonthly,
                'bank_term_months' => $bankTerm,
            ],
            'pricing' => [
                'present_value_debt' => round($pv, 2),
                'estimated_agio' => round($estimatedAgio, 2),
                'agio_formula_description' => 'Agio = Valor do Credito - PV da divida remanescente',
                'agio_range' => [
                    'conservative' => round($creditValue - $conservativePv, 2),
                    'base' => round($estimatedAgio, 2),
                    'optimistic' => round($creditValue - $optimisticPv, 2),
                ],
            ],
            'results' => [
                'comparison' => [
                    'consortium' => [
                        'monthly_payment' => round($installmentValue, 2),
                        'term_months' => $remainingInstallments,
                        'term_years' => round($remainingInstallments / 12, 1),
                        'total_cost' => round($consortiumTotal, 2),
                    ],
                    'bank_financing' => [
                        'monthly_payment' => $bankMonthly !== null ? round($bankMonthly, 2) : null,
                        'term_months' => $bankTerm,
                        'term_years' => $bankTerm !== null ? round($bankTerm / 12, 1) : null,
                        'total_cost' => $bankTotal !== null ? round($bankTotal, 2) : null,
                    ],
                    'economy' => [
                        'monthly_saving' => $monthlySaving !== null ? round($monthlySaving, 2) : null,
                        'term_saving_months' => $termSavingMonths,
                        'total_saving' => $totalSaving !== null ? round($totalSaving, 2) : null,
                        'saving_percentage' => $savingPercentage !== null ? round($savingPercentage, 2) : null,
                        'property_value' => $propertyValue !== null ? round($propertyValue, 2) : null,
                    ],
                ],
            ],
            'formula' => [
                'name' => 'Valor Presente de anuidade (HP 12C)',
                'expression' => 'PV = PMT * (1 - (1+i)^(-n)) / i',
                'assumptions' => [
                    'FV = 0',
                    'Pagamentos mensais constantes',
                    'Taxa i entre 0,60% e 0,85% ao mes',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function simulateProbability(array $input): array
    {
        $open = $this->calculateProbabilityScenario($input['open_market']);
        $exclusive = $this->calculateProbabilityScenario($input['exclusive_group']);

        $snowball = $this->calculateSnowballEffect($input, $exclusive);
        $multiplier = $this->calculateLawOfAverages($input, $exclusive);
        $leverage = $this->calculateLeverageSimulator($input, $exclusive);

        return [
            'open_market' => $open,
            'exclusive_group' => $exclusive,
            'delta' => [
                'percentage_points' => round($exclusive['probability_percentage'] - $open['probability_percentage'], 2),
                'multiple' => $open['probability_percentage'] > 0
                    ? round($exclusive['probability_percentage'] / $open['probability_percentage'], 2)
                    : null,
            ],
            'snowball' => $snowball,
            'multiplier' => $multiplier,
            'leverage' => $leverage,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function triageLead(array $input): array
    {
        $objective = (string) $input['objective'];
        $urgency = (int) $input['urgency_months'];
        $capital = (float) $input['initial_capital'];
        $risk = (string) $input['risk_profile'];

        $segment = 'investidor';
        if ($objective === 'aquisicao_urgente' || $urgency <= 12) {
            $segment = 'uso_credito_rapido';
        } elseif ($objective === 'uso_credito') {
            $segment = 'uso_credito_planejado';
        }

        $score = 50;
        $score += $capital >= 50000 ? 20 : 5;
        $score += $urgency > 24 ? 15 : 0;
        $score += $risk === 'arrojado' ? 15 : ($risk === 'moderado' ? 10 : 5);

        return [
            'segment' => $segment,
            'fit_score' => min($score, 100),
            'recommended_flow' => $segment === 'investidor' ? 'megacombo_investidor' : 'fluxo_uso_credito',
            'next_actions' => [
                'Agendar reuniao consultiva de 45 minutos',
                'Apresentar simulacao financeira personalizada',
                'Validar estrategia de curto e medio prazo',
            ],
        ];
    }

    private function presentValue(float $payment, int $periods, float $rate): float
    {
        if ($rate <= 0) {
            return $payment * $periods;
        }

        return $payment * (1 - pow(1 + $rate, -$periods)) / $rate;
    }

    /**
     * @param array<string, mixed> $scenario
     * @return array<string, mixed>
     */
    private function calculateProbabilityScenario(array $scenario): array
    {
        $participants = (int) $scenario['participants'];
        $duration = (int) $scenario['duration_months'];
        $draws = (int) $scenario['draws_per_month'];
        $extra = isset($scenario['extra_draws_per_month']) ? (int) $scenario['extra_draws_per_month'] : 0;

        $totalContemplations = ($draws + $extra) * $duration;
        $probability = min($totalContemplations / max($participants, 1), 1);

        return [
            'participants' => $participants,
            'duration_months' => $duration,
            'draws_per_month' => $draws,
            'extra_draws_per_month' => $extra,
            'total_contemplations' => $totalContemplations,
            'probability' => round($probability, 4),
            'probability_percentage' => round($probability * 100, 2),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $exclusive
     * @return array<string, mixed>
     */
    private function calculateSnowballEffect(array $input, array $exclusive): array
    {
        $baseYears = isset($input['snowball']['base_years_without_bids'])
            ? (float) $input['snowball']['base_years_without_bids']
            : 9.0;

        $targetReductionRate = isset($input['snowball']['target_reduction_rate'])
            ? (float) $input['snowball']['target_reduction_rate']
            : 0.3333;

        $freeBidsPerYear = isset($input['snowball']['free_bids_per_year'])
            ? (int) $input['snowball']['free_bids_per_year']
            : 6;

        $intensityFactor = min($freeBidsPerYear / 6, 1);
        $effectiveReductionRate = $targetReductionRate * $intensityFactor;
        $reducedYears = $baseYears * (1 - $effectiveReductionRate);

        return [
            'base_years_without_bids' => round($baseYears, 2),
            'estimated_years_with_bids' => round($reducedYears, 2),
            'time_saved_years' => round($baseYears - $reducedYears, 2),
            'free_bids_per_year' => $freeBidsPerYear,
            'reduction_rate_percentage' => round($effectiveReductionRate * 100, 2),
            'exclusive_probability_percentage' => $exclusive['probability_percentage'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $exclusive
     * @return array<string, mixed>
     */
    private function calculateLawOfAverages(array $input, array $exclusive): array
    {
        $quotas = isset($input['multiplier']['quotas'])
            ? (int) $input['multiplier']['quotas']
            : 5;

        $horizonYears = isset($input['multiplier']['horizon_years'])
            ? (int) $input['multiplier']['horizon_years']
            : 3;

        $monthlyChance = ((int) $exclusive['draws_per_month'] + (int) $exclusive['extra_draws_per_month'])
            / max((int) $exclusive['participants'], 1);
        $months = $horizonYears * 12;

        $singleQuotaHorizonChance = 1 - pow(1 - min($monthlyChance, 1), $months);
        $atLeastOneContemplationChance = 1 - pow(1 - $singleQuotaHorizonChance, $quotas);
        $expectedContemplations = $quotas * $singleQuotaHorizonChance;

        return [
            'quotas' => $quotas,
            'horizon_years' => $horizonYears,
            'single_quota_horizon_probability_percentage' => round($singleQuotaHorizonChance * 100, 2),
            'at_least_one_contemplation_probability_percentage' => round($atLeastOneContemplationChance * 100, 2),
            'expected_contemplations' => round($expectedContemplations, 2),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $exclusive
     * @return array<string, mixed>
     */
    private function calculateLeverageSimulator(array $input, array $exclusive): array
    {
        $monthlyCapacity = isset($input['leverage']['monthly_capacity'])
            ? (float) $input['leverage']['monthly_capacity']
            : 6500.0;

        $targetPatrimony = isset($input['leverage']['target_patrimony'])
            ? (float) $input['leverage']['target_patrimony']
            : 1000000.0;

        $seedInput = $input['leverage']['seed'] ?? null;
        $seed = is_numeric($seedInput)
            ? max((int) $seedInput, 1)
            : null;

        $fixedQuotaCredit = 100000.0;
        $fixedInstallment = 644.02;
        $groupDurationMonths = 216;
        $avgContemplationMonths = 72;
        $groupParticipants = 650;
        $groupDrawsPerMonth = 3;
        $pvRateMin = 0.0065;
        $pvRateMax = 0.0080;
        $cdiMonthlyRate = 0.01;
        $inccAnnualRate = 0.063;
        $selicAnnualRate = 0.1175;
        $selicMonthlyRate = pow(1 + $selicAnnualRate, 1 / 12) - 1;

        $seedState = $seed;
        $nextUnitRandom = function () use (&$seedState): float {
            if ($seedState === null) {
                return mt_rand() / mt_getrandmax();
            }

            $seedState = (int) (($seedState * 1103515245 + 12345) % 2147483648);

            return $seedState / 2147483648;
        };

        $randomMonth = function (int $min, int $max) use ($seed, $nextUnitRandom): int {
            if ($seed === null) {
                return random_int($min, $max);
            }

            $span = ($max - $min) + 1;
            $offset = min((int) floor($nextUnitRandom() * $span), $span - 1);

            return $min + $offset;
        };

        $randomRate = function (float $min, float $max) use ($seed, $nextUnitRandom): float {
            if ($seed === null) {
                return $min + (($max - $min) * (mt_rand() / mt_getrandmax()));
            }

            return $min + (($max - $min) * $nextUnitRandom());
        };

        $quotaCount = (int) floor($monthlyCapacity / $fixedInstallment);
        $quotaCount = max($quotaCount, 1);
        $effectiveMonthlyOutflow = $quotaCount * $fixedInstallment;

        $block1Count = (int) ceil($quotaCount / 2);
        $block2Count = $quotaCount - $block1Count;

        $block1Events = [];
        for ($i = 0; $i < $block1Count; $i++) {
            $contemplatedMonth = $randomMonth(1, $avgContemplationMonths);
            $rate = $randomRate($pvRateMin, $pvRateMax);
            $remainingMonths = max($groupDurationMonths - $contemplatedMonth, 0);
            $pvDebt = $this->presentValue($fixedInstallment, $remainingMonths, $rate);
            $agio = max($fixedQuotaCredit - $pvDebt, 0.0);

            $block1Events[] = [
                'contemplated_month' => $contemplatedMonth,
                'discount_rate_monthly' => round($rate, 5),
                'remaining_months' => $remainingMonths,
                'present_value_debt' => round($pvDebt, 2),
                'agio' => round($agio, 2),
            ];
        }

        usort($block1Events, fn (array $a, array $b) => $a['contemplated_month'] <=> $b['contemplated_month']);

        $block2Events = [];
        for ($i = 0; $i < $block2Count; $i++) {
            $contemplatedMonth = $randomMonth($avgContemplationMonths + 1, $groupDurationMonths);
            $correctedCredit = $fixedQuotaCredit * pow(1 + $inccAnnualRate, $contemplatedMonth / 12);

            $block2Events[] = [
                'contemplated_month' => $contemplatedMonth,
                'corrected_credit' => round($correctedCredit, 2),
            ];
        }

        usort($block2Events, fn (array $a, array $b) => $a['contemplated_month'] <=> $b['contemplated_month']);

        $timeline = [];
        $reinvestedBalance = 0.0;
        $crossingMonth = null;
        $millionMonth = null;

        for ($month = 1; $month <= $groupDurationMonths; $month++) {
            $moneyOut = $effectiveMonthlyOutflow * $month;

            foreach ($block1Events as $event) {
                if ($event['contemplated_month'] === $month) {
                    $reinvestedBalance += (float) $event['agio'];
                }
            }

            $reinvestedBalance *= (1 + $cdiMonthlyRate);

            $block2Patrimony = 0.0;
            foreach ($block2Events as $event) {
                if ($event['contemplated_month'] > $month) {
                    continue;
                }

                $monthsSinceContemplation = $month - (int) $event['contemplated_month'];
                $correctedCredit = (float) $event['corrected_credit'];
                $block2Patrimony += $correctedCredit * pow(1 + $cdiMonthlyRate, $monthsSinceContemplation);
            }

            $totalPatrimony = $reinvestedBalance + $block2Patrimony;
            $netPatrimony = $totalPatrimony - $moneyOut;

            if ($crossingMonth === null && $totalPatrimony >= $moneyOut) {
                $crossingMonth = $month;
            }

            if ($millionMonth === null && $netPatrimony >= $targetPatrimony) {
                $millionMonth = $month;
            }

            if ($month % 6 === 0 || $month === 1 || $month === $groupDurationMonths) {
                $timeline[] = [
                    'month' => $month,
                    'year' => round($month / 12, 2),
                    'money_out' => round($moneyOut, 2),
                    'projected_patrimony' => round($totalPatrimony, 2),
                    'net_patrimony' => round($netPatrimony, 2),
                ];
            }
        }

        $selicBenchmarkFinal = $selicMonthlyRate > 0
            ? $effectiveMonthlyOutflow * ((pow(1 + $selicMonthlyRate, $groupDurationMonths) - 1) / $selicMonthlyRate)
            : $effectiveMonthlyOutflow * $groupDurationMonths;

        $cdiBenchmarkFinal = $effectiveMonthlyOutflow * ((pow(1 + $cdiMonthlyRate, $groupDurationMonths) - 1) / $cdiMonthlyRate);
        $finalPoint = $timeline[count($timeline) - 1] ?? ['projected_patrimony' => 0.0, 'net_patrimony' => 0.0];
        $consortiumFinal = (float) $finalPoint['projected_patrimony'];

        return [
            'inputs' => [
                'monthly_capacity' => round($monthlyCapacity, 2),
                'target_patrimony' => round($targetPatrimony, 2),
                'seed' => $seed,
            ],
            'group_assumptions' => [
                'participants' => $groupParticipants,
                'duration_months' => $groupDurationMonths,
                'draws_per_month' => $groupDrawsPerMonth,
                'average_contemplation_months' => $avgContemplationMonths,
            ],
            'financial_assumptions' => [
                'pv_discount_rate_min_monthly' => round($pvRateMin, 5),
                'pv_discount_rate_max_monthly' => round($pvRateMax, 5),
                'incc_annual_rate' => round($inccAnnualRate, 4),
                'cdi_monthly_rate' => round($cdiMonthlyRate, 4),
                'selic_annual_rate' => round($selicAnnualRate, 4),
                'selic_monthly_rate' => round($selicMonthlyRate, 6),
            ],
            'simulation' => [
                'is_reproducible' => $seed !== null,
                'random_source' => $seed !== null ? 'seeded-lcg' : 'secure-random',
            ],
            'portfolio' => [
                'quota_count' => $quotaCount,
                'block1_quota_count' => $block1Count,
                'block2_quota_count' => $block2Count,
                'quota_credit_value' => round($fixedQuotaCredit, 2),
                'quota_installment_value' => round($fixedInstallment, 2),
                'effective_monthly_outflow' => round($effectiveMonthlyOutflow, 2),
            ],
            'blocks' => [
                'block1' => [
                    'name' => 'Giro Rapido',
                    'contemplations' => $block1Events,
                    'total_agio' => round(array_sum(array_column($block1Events, 'agio')), 2),
                    'reinvestment_rate_monthly' => round($cdiMonthlyRate, 4),
                ],
                'block2' => [
                    'name' => 'Rendimento sobre o Cheio',
                    'contemplations' => $block2Events,
                    'incc_annual_rate' => round($inccAnnualRate, 4),
                    'yield_rate_monthly' => round($cdiMonthlyRate, 4),
                ],
            ],
            'snowball_curve' => $timeline,
            'milestone' => [
                'target_patrimony' => round($targetPatrimony, 2),
                'crossing_month' => $crossingMonth,
                'crossing_year' => $crossingMonth !== null ? round($crossingMonth / 12, 2) : null,
                'million_month' => $millionMonth,
                'million_year' => $millionMonth !== null ? round($millionMonth / 12, 2) : null,
                'final_net_patrimony' => round((float) $finalPoint['net_patrimony'], 2),
            ],
            'benchmark' => [
                'selic_final_value' => round($selicBenchmarkFinal, 2),
                'consortium_expected_value' => round($consortiumFinal, 2),
                'cdi_reference_final_value' => round($cdiBenchmarkFinal, 2),
                'vs_selic_ratio' => $selicBenchmarkFinal > 0 ? round($consortiumFinal / $selicBenchmarkFinal, 2) : null,
                'vs_cdi_ratio' => $cdiBenchmarkFinal > 0 ? round($consortiumFinal / $cdiBenchmarkFinal, 2) : null,
            ],
        ];
    }
}

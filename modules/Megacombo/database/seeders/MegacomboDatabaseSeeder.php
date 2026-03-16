<?php

namespace Modules\Megacombo\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Megacombo\Models\Lead;
use Modules\Megacombo\Models\Quota;

class MegacomboDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            if (Lead::query()->exists()) {
                return;
            }

            $leads = collect([
                [
                    'name' => 'Carlos Mendes',
                    'email' => 'carlos.mendes@example.com',
                    'phone' => '(11) 98888-0101',
                    'objective' => 'investimento',
                    'urgency_months' => 36,
                    'initial_capital' => 25000,
                    'risk_profile' => 'moderado',
                    'fit_score' => 82,
                    'recommended_flow' => 'megacombo_investidor',
                ],
                [
                    'name' => 'Fernanda Alves',
                    'email' => 'fernanda.alves@example.com',
                    'phone' => '(11) 97777-0202',
                    'objective' => 'uso_credito',
                    'urgency_months' => 12,
                    'initial_capital' => 40000,
                    'risk_profile' => 'conservador',
                    'fit_score' => 74,
                    'recommended_flow' => 'fluxo_uso_credito',
                ],
                [
                    'name' => 'Rafael Costa',
                    'email' => 'rafael.costa@example.com',
                    'phone' => '(11) 96666-0303',
                    'objective' => 'diversificacao',
                    'urgency_months' => 48,
                    'initial_capital' => 60000,
                    'risk_profile' => 'arrojado',
                    'fit_score' => 91,
                    'recommended_flow' => 'megacombo_investidor',
                ],
            ])->map(fn (array $lead) => Lead::query()->create($lead));

            $quotas = [
                [
                    'lead' => $leads[0],
                    'group_code' => '1984',
                    'quota_number' => '613',
                    'credit_value' => 100000,
                    'installment_value' => 644.02,
                    'remaining_installments' => 210,
                    'status' => 'active',
                    'contemplated_at' => now()->subDays(2),
                ],
                [
                    'lead' => $leads[1],
                    'group_code' => '1984',
                    'quota_number' => '204',
                    'credit_value' => 120000,
                    'installment_value' => 771.50,
                    'remaining_installments' => 214,
                    'status' => 'active',
                    'contemplated_at' => null,
                ],
                [
                    'lead' => $leads[2],
                    'group_code' => '2001',
                    'quota_number' => '099',
                    'credit_value' => 150000,
                    'installment_value' => 963.20,
                    'remaining_installments' => 205,
                    'status' => 'active',
                    'contemplated_at' => now()->subDays(10),
                ],
            ];

            foreach ($quotas as $quotaData) {
                $lead = $quotaData['lead'];
                unset($quotaData['lead']);

                $quota = Quota::query()->create([
                    'lead_id' => $lead->id,
                    ...$quotaData,
                ]);

                DB::table('megacombo_alert_logs')->insert([
                    'quota_id' => $quota->id,
                    'event_type' => $quota->contemplated_at ? 'contemplated_detected' : 'quota_monitored',
                    'payload' => json_encode([
                        'group_code' => $quota->group_code,
                        'quota_number' => $quota->quota_number,
                        'credit_value' => (float) $quota->credit_value,
                    ], JSON_THROW_ON_ERROR),
                    'status' => 'processed',
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}

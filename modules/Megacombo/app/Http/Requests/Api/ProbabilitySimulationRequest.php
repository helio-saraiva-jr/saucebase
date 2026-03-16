<?php

namespace Modules\Megacombo\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProbabilitySimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'open_market.participants' => ['required', 'integer', 'min:1'],
            'open_market.duration_months' => ['required', 'integer', 'min:1'],
            'open_market.draws_per_month' => ['required', 'integer', 'min:1'],
            'open_market.extra_draws_per_month' => ['nullable', 'integer', 'min:0'],

            'exclusive_group.participants' => ['required', 'integer', 'min:1'],
            'exclusive_group.duration_months' => ['required', 'integer', 'min:1'],
            'exclusive_group.draws_per_month' => ['required', 'integer', 'min:1'],
            'exclusive_group.extra_draws_per_month' => ['nullable', 'integer', 'min:0'],

            'snowball.base_years_without_bids' => ['nullable', 'numeric', 'min:1', 'max:40'],
            'snowball.target_reduction_rate' => ['nullable', 'numeric', 'min:0', 'max:0.8'],
            'snowball.free_bids_per_year' => ['nullable', 'integer', 'min:0', 'max:24'],

            'multiplier.quotas' => ['nullable', 'integer', 'min:1', 'max:20'],
            'multiplier.horizon_years' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}

<?php

namespace Modules\Megacombo\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PriceSimulationRequest extends FormRequest
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
            'credit_value' => ['required', 'numeric', 'min:1'],
            'installment_value' => ['required', 'numeric', 'min:0.01'],
            'remaining_installments' => ['required', 'integer', 'min:1'],
            'discount_rate_monthly' => ['required', 'numeric', 'min:0.006', 'max:0.0085'],
            'property_value' => ['nullable', 'numeric', 'min:1'],
            'bank_monthly_payment' => ['nullable', 'numeric', 'min:0'],
            'bank_term_months' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

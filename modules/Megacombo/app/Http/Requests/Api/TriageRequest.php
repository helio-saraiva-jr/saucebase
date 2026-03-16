<?php

namespace Modules\Megacombo\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TriageRequest extends FormRequest
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
            'objective' => ['required', 'in:investimento,uso_credito,aquisicao_urgente,diversificacao'],
            'urgency_months' => ['required', 'integer', 'min:0', 'max:240'],
            'initial_capital' => ['required', 'numeric', 'min:0'],
            'risk_profile' => ['required', 'in:conservador,moderado,arrojado'],
        ];
    }
}

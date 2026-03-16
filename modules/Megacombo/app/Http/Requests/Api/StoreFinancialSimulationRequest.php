<?php

namespace Modules\Megacombo\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialSimulationRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:120'],
            'inputs' => ['required', 'array'],
            'result_snapshot' => ['required', 'array'],
        ];
    }
}

<?php

namespace Modules\Megacombo\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'megacombo_leads';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'objective',
        'urgency_months',
        'initial_capital',
        'risk_profile',
        'fit_score',
        'recommended_flow',
    ];

    protected function casts(): array
    {
        return [
            'initial_capital' => 'decimal:2',
            'fit_score' => 'integer',
            'urgency_months' => 'integer',
        ];
    }
}

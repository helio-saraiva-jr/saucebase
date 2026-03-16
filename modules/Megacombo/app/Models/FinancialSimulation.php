<?php

namespace Modules\Megacombo\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialSimulation extends Model
{
    use HasFactory;

    protected $table = 'megacombo_financial_simulations';

    protected $fillable = [
        'user_id',
        'title',
        'inputs',
        'result_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'result_snapshot' => 'array',
        ];
    }
}

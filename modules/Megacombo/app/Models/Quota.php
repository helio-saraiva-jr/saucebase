<?php

namespace Modules\Megacombo\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quota extends Model
{
    use HasFactory;

    protected $table = 'megacombo_quotas';

    protected $fillable = [
        'lead_id',
        'group_code',
        'quota_number',
        'credit_value',
        'installment_value',
        'remaining_installments',
        'status',
        'contemplated_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_value' => 'decimal:2',
            'installment_value' => 'decimal:2',
            'remaining_installments' => 'integer',
            'contemplated_at' => 'datetime',
        ];
    }
}

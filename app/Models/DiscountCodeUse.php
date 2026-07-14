<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DiscountCodeUse extends Model
{
    use HasUuids;

    protected $fillable = [
        'discount_code_id', 'user_id', 'order_id', 'amount_discounted', 'used_at',
    ];

    protected $casts = [
        'amount_discounted' => 'decimal:2',
        'used_at' => 'datetime',
    ];
}

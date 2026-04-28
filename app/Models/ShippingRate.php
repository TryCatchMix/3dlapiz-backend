<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['country_code', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (ShippingRate $rate) {
            $rate->country_code = strtoupper($rate->country_code);
        });
    }
}

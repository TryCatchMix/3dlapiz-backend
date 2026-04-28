<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';


    protected $fillable = [
        'name',
        'description',
        'price',
        'youtube_url',
        'unpainted_price',
        'stock',

    ];

    protected $casts = [
    'price' => 'decimal:2',
    'unpainted_price' => 'decimal:2',
];

public function priceForVariant(string $variant): float
{
    if ($variant === 'painted') {
        return (float) $this->price;
    }
    if ($variant === 'unpainted') {
        if ($this->unpainted_price === null) {
            throw new \InvalidArgumentException('Este producto no tiene variante sin pintar.');
        }
        return (float) $this->unpainted_price;
    }
    throw new \InvalidArgumentException('Variante no válida.');
}

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'payment_status',
        'stripe_session_id',
        'payment_intent',
        'tracking_number',
        'shipping_carrier',
        'shipped_at',
        'total',
        'shipping_info',
        'shipping_method',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'shipping_info' => 'array',
        'shipping_method' => 'array',
        'shipped_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                do {
                    $candidate = strtoupper(Str::random(8));
                } while (self::where('order_number', $candidate)->exists());
                $order->order_number = $candidate;
            }
        });
    }

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con los items del pedido
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope para filtrar por usuario
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'percentage', 'expires_at', 'min_order_amount', 'stripe_coupon_id',
        'max_uses', 'max_uses_per_user', 'active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'min_order_amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function uses()
    {
        return $this->hasMany(DiscountCodeUse::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isMaxUsesReached(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    public function usesByUser(string $userId): int
    {
        return $this->uses()->where('user_id', $userId)->count();
    }

    public function isMaxUsesPerUserReached(string $userId): bool
    {
        return $this->max_uses_per_user !== null
            && $this->usesByUser($userId) >= $this->max_uses_per_user;
    }
}

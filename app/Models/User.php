<?php

namespace App\Models;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasUuids, HasFactory, Notifiable, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';
    public const ROLE_USER = 'user';

    public const ROLE_ENUM = [self::ROLE_ADMIN, self::ROLE_STAFF, self::ROLE_USER];

    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone_country_code',
        'phone_number',
        'street',
        'city',
        'state',
        'postal_code',
        'country_code',
        'is_active',
        'role',
        'last_login_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'deleted_at'
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
            'role' => 'string',
            'phone_verified_at' => 'datetime',
            'last_profile_change' => 'datetime',
            'profile_change_reset_date' => 'datetime',
            'phone_verified' => 'boolean',
        ];
    }

    const MAX_PROFILE_CHANGES_PER_MONTH = 5;
    const PROFILE_CHANGE_RESET_DAYS = 30;

    /**
     * Relationships
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Accessors
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getFormattedPhoneAttribute(): ?string
    {
        if (!$this->phone_number) return null;
        return "{$this->phone_country_code} {$this->phone_number}";
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function canChangeProfile(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->profile_change_reset_date) {
            return true;
        }

        if (now()->gt($this->profile_change_reset_date)) {
            $this->update([
                'profile_changes_count' => 0,
                'profile_change_reset_date' => now()->addDays(self::PROFILE_CHANGE_RESET_DAYS),
            ]);
            return true;
        }

        return $this->profile_changes_count < self::MAX_PROFILE_CHANGES_PER_MONTH;
    }

    public function incrementProfileChanges(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $resetDate = $this->profile_change_reset_date ?: now()->addDays(self::PROFILE_CHANGE_RESET_DAYS);

        $this->update([
            'profile_changes_count' => $this->profile_changes_count + 1,
            'last_profile_change' => now(),
            'profile_change_reset_date' => $resetDate,
        ]);
    }

    public function getRemainingProfileChanges(): int
    {
        if ($this->isAdmin()) {
            return 999;
        }

        if (!$this->canChangeProfile()) {
            return 0;
        }

        return self::MAX_PROFILE_CHANGES_PER_MONTH - $this->profile_changes_count;
    }

    /**
     * Validation rules
     */
    public static function validationRules(): array
    {
        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:12|confirmed',
            'phone_country_code' => 'required_with:phone_number|string|max:5',
            'phone_number' => 'nullable|string|max:20',
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|size:2',
            'role' => 'sometimes|in:' . implode(',', self::ROLE_ENUM)
        ];
    }

    /**
     * Validation rules para actualización de perfil
     */
    public static function profileUpdateRules($userId = null): array
    {
        return [
            'first_name' => 'sometimes|string|max:50|min:2',
            'last_name' => 'sometimes|string|max:50|min:2',
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'phone_country_code' => 'required_with:phone_number|string|max:5',
            'phone_number' => 'nullable|string|max:20|regex:/^[0-9\s\-\+\(\)]+$/',
            'street' => 'nullable|string|max:255|min:5',
            'city' => 'nullable|string|max:100|min:2',
            'state' => 'nullable|string|max:100|min:2',
            'postal_code' => 'nullable|string|max:20|min:3',
            'country_code' => 'nullable|string|size:2|uppercase',
        ];
    }

    /**
     * Mutators
     */
    public function setPhoneNumberAttribute($value): void
    {
        $this->attributes['phone_number'] = preg_replace('/[^0-9]/', '', $value);
    }

    public function setCountryCodeAttribute($value): void
    {
        $this->attributes['country_code'] = strtoupper($value);
    }

    public function setPostalCodeAttribute($value): void
    {
        $this->attributes['postal_code'] = strtoupper(trim($value));
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmail());
    }
}

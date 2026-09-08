<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Models\Concerns\CausesActivity;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, CausesActivity;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'created_by', 'last_login_at',
        'mobile_login_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // Auto-generate mobile_login_token on creation (UUID, permanent — never changes).
    // Used by Api\AuthController::verify() to let staff sign into the mobile app;
    // distinct from Customer::portal_token, which is the customer-facing portal link.
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->mobile_login_token = Str::uuid()->toString();
        });
    }

    // Relationships
    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function staffMobileLoginTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StaffMobileLoginToken::class);
    }

    // Role helpers
    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isAccountant(): bool { return $this->role === 'accountant'; }
    public function isManager(): bool    { return $this->role === 'production_manager'; }
    public function isDesigner(): bool   { return $this->role === 'creative_head'; }
    public function isActive(): bool     { return $this->is_active; }

    // Scope: active users only
    public function scopeActive($query) { return $query->where('is_active', true); }
}

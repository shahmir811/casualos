<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Models\Concerns\CausesActivity;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, CausesActivity;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'created_by', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // Relationships
    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    // Role helpers
    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isAccountant(): bool { return $this->role === 'accountant'; }
    public function isManager(): bool    { return $this->role === 'manager'; }
    public function isDesigner(): bool   { return $this->role === 'designer'; }
    public function isActive(): bool     { return $this->is_active; }

    // Scope: active users only
    public function scopeActive($query) { return $query->where('is_active', true); }
}

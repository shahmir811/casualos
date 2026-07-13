<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    public const COUNTRIES = [
        'Australia', 'Canada', 'Pakistan', 'Saudi Arabia', 'UAE', 'UK', 'USA',
    ];

    // Currency symbol shown on piece tags for each destination country
    public const CURRENCY_SYMBOLS = [
        'Australia'    => 'AUS $',
        'Canada'       => 'CAD $',
        'Pakistan'     => 'Rs.',
        'Saudi Arabia' => 'SR',
        'UAE'          => 'AED',
        'UK'           => '£',
        'USA'          => 'US $',
    ];

    protected $fillable = [
        'name', 'city', 'country', 'address', 'contact_number', 'email', 'portal_token',
        'advance_credit_balance', 'created_by',
    ];

    protected $casts = [
        'advance_credit_balance' => 'decimal:2',
    ];

    // Auto-generate portal_token on creation (UUID, permanent — never changes)
    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            $customer->portal_token = Str::uuid()->toString();
        });
    }

    public function hasAdvanceCredit(): bool
    {
        return $this->advance_credit_balance > 0;
    }

    // Relationships
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function ledger(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerLedger::class)->orderBy('created_at');
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

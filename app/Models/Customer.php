<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * Customers authenticate via Sanctum bearer tokens (mobile app), never a
 * password — the customer never sets or knows a password. A token is only
 * issued after proving ownership of the permanent portal_token link *and*
 * confirming the email on file, mirroring CustomerPortalController::verify()
 * for the web PWA. See Api\AuthController.
 *
 * SoftDeletes backs the app's account-deletion flow (Guideline 5.1.1(v)) —
 * deleted_at makes the row invisible to login and every default query, but
 * the row itself is never removed, so orders/ledger entries never lose
 * their customer_id. See Api\AuthController::destroy().
 */
class Customer extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory, Notifiable, HasPushSubscriptions, SoftDeletes;

    public const COUNTRIES = [
        'Australia', 'Bangladesh', 'Canada', 'Kuwait', 'Malaysia', 'Norway',
        'Oman', 'Pakistan', 'Qatar', 'Saudi Arabia', 'UAE', 'UK', 'USA',
    ];

    // Currency symbol shown on piece tags for each destination country
    public const CURRENCY_SYMBOLS = [
        'Australia'    => 'AUS $',
        'Bangladesh'   => 'TK',
        'Canada'       => 'CAD $',
        'Kuwait'       => 'KWD',
        'Malaysia'     => 'RM',
        'Norway'       => 'NOK',
        'Oman'         => 'OMR',
        'Pakistan'     => 'Rs.',
        'Qatar'        => 'QAR',
        'Saudi Arabia' => 'SR',
        'UAE'          => 'AED',
        'UK'           => '£',
        'USA'          => 'US $',
    ];

    protected $fillable = [
        'name', 'city', 'country', 'address', 'contact_number', 'email', 'portal_token',
        'advance_credit_balance', 'created_by', 'app_platform', 'app_last_seen_at',
    ];

    protected $casts = [
        'advance_credit_balance' => 'decimal:2',
        'app_last_seen_at' => 'datetime',
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

    /**
     * Route-model binding (customers.show/edit/ledger/advance-payments)
     * includes soft-deleted customers — staff following a "View Customer"
     * link from an order/ledger/report row must still reach the page
     * instead of a 404, or the whole point of soft-deleting (keeping
     * history visible) is defeated. Never affects AuthController::verify(),
     * which queries directly rather than through route binding, so a
     * deleted customer still can't log back in.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withTrashed()->where($field ?? $this->getRouteKeyName(), $value)->first();
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

    public function advancePayments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdvancePayment::class)->latest('payment_date');
    }

    public function refunds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Refund::class)->latest('refund_date');
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function devices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerDevice::class);
    }

    // Native mobile push (Expo) — separate from pushSubscriptions() (web
    // push, from HasPushSubscriptions), since an Expo token is a single
    // opaque string, structurally unlike a WebPush endpoint+keys subscription.
    public function expoPushTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExpoPushToken::class);
    }
}

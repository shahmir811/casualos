<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's self-submitted signup request from the mobile app, pending
 * admin review. Approving one creates a real Customer row (see rule 5.34);
 * rejecting one never does. Kept as a separate table from `customers` since
 * Customer has no status/approval concept and `created_by` is a required FK.
 */
class CustomerSignupRequest extends Model
{
    protected $fillable = [
        'name', 'contact_number', 'city', 'country', 'address', 'email',
        'status', 'customer_id', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

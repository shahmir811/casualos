<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ProductionAssignment extends Model
{
    use LogsActivity;

    protected $fillable = ['catalogue_id', 'design_id', 'destination', 'stitching_unit', 'naeem_pakki_rate', 'assignment_date', 'logged_by'];

    protected $casts = ['assignment_date' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function goesToNaeemPakki(): bool   { return $this->destination === 'naeem_pakki'; }
    public function goesToStitching(): bool    { return $this->destination === 'stitching_unit'; }

    public function catalogue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionAssignmentItem::class);
    }

    /** Per-design breakdown for Naeem Pakki batch assignments. */
    public function npDesigns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionAssignmentNpDesign::class);
    }

    public function naeemPakkiReturns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NaeemPakkiReturn::class);
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}

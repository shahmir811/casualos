<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class StitchingReturn extends Model
{
    use LogsActivity;

    protected $fillable = ['catalogue_id', 'design_id', 'stitching_unit_id', 'return_date', 'logged_by'];

    protected $casts = ['return_date' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

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
        return $this->hasMany(StitchingReturnItem::class);
    }

    public function stitchingUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StitchingUnit::class);
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}

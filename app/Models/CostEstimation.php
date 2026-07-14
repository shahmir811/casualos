<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CostEstimation extends Model
{
    use LogsActivity;

    protected $fillable = [
        'catalogue_id', 'design_id', 'estimation_date', 'stitched_by', 'production_plan_qty',
        'total_cost', 'per_unit_cost', 'market_rate', 'margin', 'approved_by', 'prepared_by',
    ];

    protected $casts = [
        'estimation_date' => 'date',
        'total_cost'      => 'decimal:2',
        'per_unit_cost'   => 'decimal:2',
        'market_rate'     => 'decimal:2',
        'margin'          => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
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
        return $this->hasMany(CostEstimationItem::class);
    }

    public function preparedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}

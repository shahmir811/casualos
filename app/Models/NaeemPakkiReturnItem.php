<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NaeemPakkiReturnItem extends Model
{
    protected $fillable = ['naeem_pakki_return_id', 'np_design_id', 'quantity'];

    public function return(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NaeemPakkiReturn::class, 'naeem_pakki_return_id');
    }

    public function npDesign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductionAssignmentNpDesign::class, 'np_design_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TarpaiReturn extends Model
{
    use LogsActivity;

    protected $fillable = ['tarpai_send_id', 'return_date', 'logged_by'];

    protected $casts = ['return_date' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function send(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TarpaiSend::class, 'tarpai_send_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TarpaiReturnItem::class, 'tarpai_return_id');
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}

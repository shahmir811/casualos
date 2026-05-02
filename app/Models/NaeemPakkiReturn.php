<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class NaeemPakkiReturn extends Model
{
    use LogsActivity;

    protected $fillable = ['naeem_pakki_send_id', 'return_date', 'quantity', 'logged_by'];

    protected $casts = ['return_date' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function send(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NaeemPakkiSend::class, 'naeem_pakki_send_id');
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}

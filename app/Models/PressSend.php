<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PressSend extends Model
{
    use LogsActivity;

    protected $fillable = ['catalogue_id', 'sent_date', 'logged_by'];

    protected $casts = ['sent_date' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function catalogue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PressSendItem::class);
    }

    public function returns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PressReturn::class);
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function totalSent(): int
    {
        return $this->items->sum('quantity');
    }

    public function totalReturned(): int
    {
        return $this->returns->flatMap->items->sum('quantity');
    }

    public function outstandingPieces(): int
    {
        return max(0, $this->totalSent() - $this->totalReturned());
    }
}

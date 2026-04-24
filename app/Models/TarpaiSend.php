<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TarpaiSend extends Model
{
    use LogsActivity;

    protected $fillable = ['catalogue_id', 'design_id', 'sent_date', 'per_piece_price', 'logged_by'];

    protected $casts = [
        'sent_date'       => 'date',
        'per_piece_price' => 'decimal:2',
    ];

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
        return $this->hasMany(TarpaiSendItem::class);
    }

    public function returns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TarpaiReturn::class);
    }

    public function totalPiecesSent(): int     { return $this->items->sum('quantity'); }
    public function totalPiecesReturned(): int  { return $this->returns->flatMap->items->sum('quantity'); }
    public function outstandingPieces(): int    { return max(0, $this->totalPiecesSent() - $this->totalPiecesReturned()); }
    public function totalCost(): float          { return $this->totalPiecesSent() * $this->per_piece_price; }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}

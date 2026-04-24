<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class NaeemPakkiSend extends Model
{
    use LogsActivity;

    protected $fillable = ['production_assignment_id', 'sent_date', 'per_piece_price', 'logged_by'];

    protected $casts = [
        'sent_date'       => 'date',
        'per_piece_price' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function assignment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductionAssignment::class, 'production_assignment_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NaeemPakkiSendItem::class);
    }

    public function returns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NaeemPakkiReturn::class);
    }

    public function totalPiecesSent(): int
    {
        return $this->items->sum('quantity');
    }

    public function totalPiecesReturned(): int
    {
        return $this->returns->flatMap->items->sum('quantity');
    }

    public function outstandingPieces(): int
    {
        return max(0, $this->totalPiecesSent() - $this->totalPiecesReturned());
    }

    public function totalCost(): float
    {
        return $this->totalPiecesSent() * $this->per_piece_price;
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}

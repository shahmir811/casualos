<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Design extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalogue_id', 'name', 'photo', 'selling_price', 'manufacturing_type', 'sort_order',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
    ];

    public function isInHouse(): bool    { return $this->manufacturing_type === 'in_house'; }
    public function isOutsourced(): bool { return $this->manufacturing_type === 'outsourced'; }

    // Relationships
    public function catalogue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function productionAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductionAssignment::class);
    }
}

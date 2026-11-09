<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function catalogues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Catalogue::class);
    }
}

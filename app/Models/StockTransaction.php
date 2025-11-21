<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StockTransaction extends Model
{
    protected $fillable = [
        'material_id',
        'part_id',
        'transaction_date',
        'shift',
        'transaction_type',
        'quantity',
        'reference_no',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}

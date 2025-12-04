<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'part_code',
        'id_sap',
        'type_line',
        'part_name',
        'product_images',
        'description',
    ];

    protected $casts = [
        'product_images' => 'array', // Cast ke array
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function ngReports(): HasMany
    {
        return $this->hasMany(NgReport::class);
    }
}

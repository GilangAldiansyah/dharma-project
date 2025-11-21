<?php
// app/Models/ProductImage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'part_id',
        'image_path',
        'image_type',
        'description',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}

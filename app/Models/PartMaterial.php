<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartMaterial extends Model
{
    protected $fillable = [
        'part_id',
        'nama_part',
        'material_id',
        'qty_per_unit',
    ];

    protected $attributes = [
        'nama_part' => 'N/A',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}

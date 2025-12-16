<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransaksiMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaksi_id',
        'tanggal',
        'shift',
        'material_id',
        'part_material_id',
        'qty',
        'foto',
        'user_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'foto' => 'array',
        'qty' => 'decimal:2',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function partMaterial()
    {
        return $this->belongsTo(PartMaterial::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

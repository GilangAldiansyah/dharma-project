<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'material_id',
        'nama_material',
        'material_type',
        'satuan',
    ];

    public function partMaterials()
    {
        return $this->hasMany(PartMaterial::class);
    }

    public function transaksi()
    {
        return $this->hasMany(TransaksiMaterial::class);
    }
}

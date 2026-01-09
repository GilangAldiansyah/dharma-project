<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PengembalianMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'pengembalian_id',
        'transaksi_material_id',
        'tanggal_pengembalian',
        'pic',
        'qty_pengembalian',
        'foto',
        'keterangan',
        'user_id',
    ];

    protected $casts = [
        'tanggal_pengembalian' => 'date',
        'foto' => 'array',
        'qty_pengembalian' => 'decimal:2',
    ];

    public function transaksiMaterial()
    {
        return $this->belongsTo(TransaksiMaterial::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValidPengembalianQty(): bool
    {
        $totalPengembalian = static::where('transaksi_material_id', $this->transaksi_material_id)
            ->where('id', '!=', $this->id)
            ->sum('qty_pengembalian');

        return ($totalPengembalian + $this->qty_pengembalian) <= $this->transaksiMaterial->qty;
    }

    public static function getTotalPengembalianQty($transaksiMaterialId): float
    {
        return static::where('transaksi_material_id', $transaksiMaterialId)
            ->sum('qty_pengembalian');
    }

    public static function getSisaPengembalianQty($transaksiMaterialId): float
    {
        $transaksi = TransaksiMaterial::find($transaksiMaterialId);
        if (!$transaksi) {
            return 0;
        }

        $totalPengembalian = static::getTotalPengembalianQty($transaksiMaterialId);
        return $transaksi->qty - $totalPengembalian;
    }
}

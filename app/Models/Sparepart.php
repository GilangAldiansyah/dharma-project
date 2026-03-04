<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Sparepart extends Model
{
    protected $fillable = ['sap_id', 'name', 'satuan', 'stok', 'stok_minimum'];

    protected $casts = [
        'stok'         => 'float',
        'stok_minimum' => 'float',
    ];

    public function histories()
    {
        return $this->hasMany(SparepartHistory::class);
    }

    public function isStokLow(): bool
    {
        return $this->stok <= $this->stok_minimum;
    }

    public function kurangiStok(float $qty, string $reportType = 'pm', int $reportId = null, string $notes = null): void
    {
        $before = $this->stok;
        $this->decrement('stok', $qty);
        $after  = $this->fresh()->stok;

        SparepartHistory::create([
            'sparepart_id' => $this->id,
            'tipe'         => 'keluar',
            'report_type'  => $reportType,
            'report_id'    => $reportId,
            'qty'          => $qty,
            'stok_before'  => $before,
            'stok_after'   => $after,
            'notes'        => $notes,
            'user_id'      => Auth::id(),
        ]);
    }

    public function tambahStok(float $qty, string $notes = null): void
    {
        $before = $this->stok;
        $this->increment('stok', $qty);
        $after  = $this->fresh()->stok;

        SparepartHistory::create([
            'sparepart_id' => $this->id,
            'tipe'         => 'masuk',
            'report_type'  => 'manual',
            'report_id'    => null,
            'qty'          => $qty,
            'stok_before'  => $before,
            'stok_after'   => $after,
            'notes'        => $notes,
            'user_id'      => Auth::id(),
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sparepart extends Model
{
    protected $fillable = ['name', 'satuan', 'stok', 'stok_minimum'];

    protected $casts = [
        'stok'         => 'float',
        'stok_minimum' => 'float',
    ];

    public function isStokLow(): bool
    {
        return $this->stok <= $this->stok_minimum;
    }

    public function kurangiStok(float $qty): void
    {
        $this->decrement('stok', $qty);
    }

    public function tambahStok(float $qty): void
    {
        $this->increment('stok', $qty);
    }
}

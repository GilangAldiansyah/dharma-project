<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfidMaster extends Model
{
    protected $fillable = [
        'rfid_tag',
        'product_id',
        'scan_type',
        'route',
        'address',
        'packaging_type',
        'quantity',
        'operator_name',
        'shift',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'shift' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public static function findByRfid(string $rfidTag): ?self
    {
        return self::where('rfid_tag', $rfidTag)->first();
    }

    public static function isRfidRegistered(string $rfidTag): bool
    {
        return self::where('rfid_tag', $rfidTag)->exists();
    }
}

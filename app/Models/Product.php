<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'line_id',
        'product_code',
        'product_name',
        'customer',
        'current_stock',
    ];

    protected $casts = [
        'current_stock' => 'integer',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function kanbans(): HasMany
    {
        return $this->hasMany(Kanban::class);
    }

    public function incrementStock(int $quantity): void
    {
        $this->increment('current_stock', $quantity);
    }

    public function decrementStock(int $quantity): void
    {
        $this->decrement('current_stock', $quantity);
    }

    public static function generateProductCode(int $lineId): string
    {
        $line = Line::findOrFail($lineId);
        $plantCode = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $line->plant), 0, 3));

        $lastProduct = static::where('line_id', $lineId)
            ->where('product_code', 'like', "P-{$plantCode}-%")
            ->orderByRaw('CAST(SUBSTRING(product_code, LENGTH(product_code) - 2) AS UNSIGNED) DESC')
            ->first();

        if ($lastProduct) {
            preg_match('/P-' . preg_quote($plantCode, '/') . '-(\d+)$/', $lastProduct->product_code, $matches);
            $lastNumber = isset($matches[1]) ? intval($matches[1]) : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "P-{$plantCode}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}

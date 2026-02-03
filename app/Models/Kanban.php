<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Kanban extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfid_tag',
        'product_id',
        'kanban_no',
        'scan_type',
        'route',
        'address',
        'packaging_type',
        'quantity',
        'scanned_at',
        'operator_name',
        'shift',
        'notes',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function line()
    {
        return $this->product->line();
    }

    public static function isRfidUsedToday(string $rfidTag): bool
    {
        return static::where('rfid_tag', $rfidTag)
            ->whereDate('scanned_at', Carbon::today())
            ->exists();
    }

    public static function generateKanbanNo(string $productId): string
    {
        $product = Product::findOrFail($productId);

        $lastKanban = static::where('product_id', $productId)
            ->where('kanban_no', 'like', "KBN-" . $product->product_code . "-%")
            ->orderByRaw('CAST(SUBSTRING(kanban_no, LENGTH(kanban_no) - 2) AS UNSIGNED) DESC')
            ->first();

        if ($lastKanban) {
            preg_match('/KBN-' . preg_quote($product->product_code, '/') . '-(\d+)$/', $lastKanban->kanban_no, $matches);
            $lastNumber = isset($matches[1]) ? intval($matches[1]) : 0;
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "KBN-{$product->product_code}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::created(function (Kanban $kanban) {
            $product = $kanban->product;

            if ($kanban->scan_type === 'in') {
                $product->incrementStock($kanban->quantity);
            } else {
                $product->decrementStock($kanban->quantity);
            }
        });
    }
}

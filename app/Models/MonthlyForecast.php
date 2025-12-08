<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyForecast extends Model
{
    protected $fillable = [
        'sap_no',
        'product_unit',
        'part_name',
        'type',
        'year',
        'month',
        'forecast_qty',
        'working_days',
        'qty_per_day',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'forecast_qty' => 'integer',
        'working_days' => 'integer',
        'qty_per_day' => 'integer',
    ];

    public function calculateQtyPerDay(): float
    {
        if ($this->working_days > 0) {
            return round($this->forecast_qty / $this->working_days, 2);
        }
        return 0;
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($forecast) {
            $forecast->qty_per_day = $forecast->calculateQtyPerDay();
        });
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public static function getCurrentMonthForecast(string $sapNo)
    {
        $now = now();
        return self::where('sap_no', $sapNo)
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->first();
    }

    public static function getMonthForecasts(int $year, int $month)
    {
        return self::where('year', $year)
            ->where('month', $month)
            ->orderBy('type')
            ->orderBy('sap_no')
            ->get();
    }

    public static function existsForMonth(string $sapNo, int $year, int $month): bool
    {
        return self::where('sap_no', $sapNo)
            ->where('year', $year)
            ->where('month', $month)
            ->exists();
    }
}

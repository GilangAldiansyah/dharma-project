<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    public static function getEffectiveDate($datetime = null): Carbon
    {
        $datetime = $datetime ?? now();
        $effectiveDate = $datetime->copy();

        if ($datetime->hour < 7) {
            $effectiveDate->subDay();
        }

        return $effectiveDate->startOfDay();
    }

    public static function getCurrentShift($datetime = null): int
    {
        $datetime = $datetime ?? now();
        $hour = $datetime->hour;

        if ($hour >= 7 && $hour < 16) {
            return 1;
        } elseif ($hour >= 21 || $hour < 5) {
            return 2;
        } else {
            return 3;
        }
    }

    public static function getShiftLabel(int $shift): string
    {
        return match($shift) {
            1 => 'Shift 1',
            2 => 'Shift 2',
            3 => 'Shift 3',
            default => 'N/A',
        };
    }

    public static function isValidPengembalianDate($tanggalPengembalian): bool
    {
        $tanggalPengembalian = Carbon::parse($tanggalPengembalian)->startOfDay();
        $effectiveToday = static::getEffectiveDate()->startOfDay();

        return $tanggalPengembalian->lte($effectiveToday);
    }

    public static function formatDate($date): string
    {
        return Carbon::parse($date)->format('d M Y');
    }

    public static function getAllShifts():array{
        return [
            ['value' => 1, 'label' => 'shift 1'],
            ['value' => 2, 'label' => 'shift 2'],
            ['value' => 3, 'label' => 'shift 3'],
        ];
    }
}

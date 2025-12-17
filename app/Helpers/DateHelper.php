<?php
namespace App\Helpers;
use Carbon\Carbon;

class DateHelper
{
    public static function getEffectiveDate($datetime = null): Carbon
    {
        $datetime = $datetime ?? now();
        $effectiveDate = $datetime->copy();

        // Jika jam sebelum jam 7 pagi, tanggal efektif adalah hari sebelumnya
        // Karena masih bagian dari shift 2 atau shift 3 hari sebelumnya
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
        }
        elseif ($hour >= 21 || $hour < 5) {
            return 2;
        }
        else {
            return 3;
        }
    }

    public static function isValidPengembalianDate($tanggalPengembalian): bool
    {
        $tanggalPengembalian = Carbon::parse($tanggalPengembalian)->startOfDay();
        $effectiveToday = static::getEffectiveDate()->startOfDay();

        // Tanggal pengembalian harus <= tanggal efektif hari ini
        return $tanggalPengembalian->lte($effectiveToday);
    }

    public static function formatDate($date): string
    {
        return Carbon::parse($date)->format('d M Y');
    }
}

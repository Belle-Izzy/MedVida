<?php

namespace App\Support;

use Carbon\Carbon;

class AppointmentSlots
{
    public static function fitsSchedule(string $start, string $end, string $time): bool
    {
        $startMinutes = self::minutes($start);
        $endMinutes = self::minutes($end);
        $timeMinutes = self::minutes($time);
        $offset = $timeMinutes - $startMinutes;

        return $offset >= 0 && $offset % 60 === 0 && $timeMinutes + 60 <= $endMinutes;
    }

    public static function isFuture(string $date, string $time): bool
    {
        return Carbon::parse($date.' '.$time)->gt(now());
    }

    private static function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }
}

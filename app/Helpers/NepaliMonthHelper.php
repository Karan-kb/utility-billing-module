<?php

namespace App\Helpers;

class NepaliMonthHelper
{
    public static function getMonths(): array
    {
        return [
            1 => 'Baishakh',
            2 => 'Jestha',
            3 => 'Ashadh',
            4 => 'Shrawan',
            5 => 'Bhadra',
            6 => 'Ashwin',
            7 => 'Kartik',
            8 => 'Mangsir',
            9 => 'Poush',
            10 => 'Magh',
            11 => 'Falgun',
            12 => 'Chaitra',
        ];
    }

    public static function getMonthName(int $month): string
    {
        $months = self::getMonths();
        return $months[$month] ?? (string)$month;
    }
}
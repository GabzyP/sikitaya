<?php

use Carbon\Carbon;
use Carbon\CarbonInterface;

if (! function_exists('rupiah')) {
    function rupiah(int|float|string|null $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}

if (! function_exists('berat')) {
    function berat(int|float|null $grams): string
    {
        $grams = (float) $grams;

        return $grams >= 1000
            ? rtrim(rtrim(number_format($grams / 1000, 2, ',', '.'), '0'), ',').' kg'
            : number_format($grams, 0, ',', '.').' g';
    }
}

if (! function_exists('tanggal_indonesia')) {
    function tanggal_indonesia(CarbonInterface|string|null $date, bool $withTime = false): string
    {
        if ($date === null) {
            return '-';
        }

        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return $date->locale('id')->isoFormat($withTime ? 'D MMMM YYYY, HH:mm' : 'D MMMM YYYY');
    }
}

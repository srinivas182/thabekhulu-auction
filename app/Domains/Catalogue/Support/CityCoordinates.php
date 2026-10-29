<?php

namespace App\Domains\Catalogue\Support;

/**
 * Approximate centres of the seeded South African cities, so a listing without its own
 * map coordinates still appears on the auctions map (at its city, marked approximate).
 */
final class CityCoordinates
{
    /** @var array<string, array{0: float, 1: float}> */
    public const CITIES = [
        'Johannesburg' => [-26.20, 28.05], 'Pretoria' => [-25.75, 28.19], 'Midrand' => [-25.99, 28.13], 'Centurion' => [-25.86, 28.19],
        'Sandton' => [-26.11, 28.06], 'Soweto' => [-26.27, 27.86], 'Germiston' => [-26.22, 28.17], 'Vereeniging' => [-26.67, 27.93],
        'Cape Town' => [-33.92, 18.42], 'Stellenbosch' => [-33.93, 18.86], 'Paarl' => [-33.73, 18.97], 'George' => [-33.96, 22.46],
        'Knysna' => [-34.04, 23.05], 'Hermanus' => [-34.42, 19.24], 'Worcester' => [-33.65, 19.45],
        'Durban' => [-29.86, 31.02], 'Pietermaritzburg' => [-29.60, 30.38], 'Umhlanga' => [-29.73, 31.08], 'Ballito' => [-29.54, 31.21],
        'Richards Bay' => [-28.78, 32.04], 'Newcastle' => [-27.76, 29.93],
        'Gqeberha' => [-33.96, 25.60], 'East London' => [-33.02, 27.91], 'Mthatha' => [-31.59, 28.78], 'Makhanda' => [-33.31, 26.53],
        'Bloemfontein' => [-29.12, 26.21], 'Welkom' => [-27.98, 26.73], 'Bethlehem' => [-28.23, 28.31], 'Sasolburg' => [-26.81, 27.82],
        'Polokwane' => [-23.90, 29.45], 'Tzaneen' => [-23.83, 30.16], 'Thohoyandou' => [-22.95, 30.48], 'Mokopane' => [-24.19, 29.01],
        'Lephalale' => [-23.67, 27.75],
        'Mbombela' => [-25.47, 30.97], 'Emalahleni' => [-25.87, 29.23], 'Middelburg' => [-25.78, 29.46], 'Secunda' => [-26.52, 29.19],
        'Barberton' => [-25.79, 31.05],
        'Rustenburg' => [-25.67, 27.24], 'Mahikeng' => [-25.86, 25.64], 'Klerksdorp' => [-26.85, 26.67], 'Potchefstroom' => [-26.71, 27.10],
        'Brits' => [-25.63, 27.78],
        'Kimberley' => [-28.74, 24.76], 'Upington' => [-28.45, 21.26], 'Kathu' => [-27.70, 23.05], 'Springbok' => [-29.66, 17.89],
        'Kuruman' => [-27.46, 23.43],
    ];

    /** @return array{0: float, 1: float}|null */
    public static function for(string $city): ?array
    {
        return self::CITIES[$city] ?? null;
    }
}

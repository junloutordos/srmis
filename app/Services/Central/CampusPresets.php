<?php

namespace App\Services\Central;

/**
 * The Office of the Executive Director plus the 16 PSHS campuses.
 * `slug` doubles as the tenant id, the subdomain, and the schema suffix
 * (srmis_oed, srmis_crc, srmis_src, ...).
 */
class CampusPresets
{
    public const CAMPUSES = [
        ['slug' => 'oed',   'code' => 'PSHS-OED',   'name' => 'Office of the Executive Director'],
        ['slug' => 'mc',    'code' => 'PSHS-MC',    'name' => 'Main Campus'],
        ['slug' => 'brc',   'code' => 'PSHS-BRC',   'name' => 'Bicol Region Campus'],
        ['slug' => 'cbzrc', 'code' => 'PSHS-CBZRC', 'name' => 'CALABARZON Region Campus'],
        ['slug' => 'carc',  'code' => 'PSHS-CARC',  'name' => 'Cordillera Administrative Region Campus'],
        ['slug' => 'cvc',   'code' => 'PSHS-CVC',   'name' => 'Cagayan Valley Campus'],
        ['slug' => 'clc',   'code' => 'PSHS-CLC',   'name' => 'Central Luzon Campus'],
        ['slug' => 'cmc',   'code' => 'PSHS-CMC',   'name' => 'Central Mindanao Campus'],
        ['slug' => 'cvisc', 'code' => 'PSHS-CVisC', 'name' => 'Central Visayas Campus'],
        ['slug' => 'crc',   'code' => 'PSHS-CRC',   'name' => 'Caraga Region Campus'],
        ['slug' => 'evc',   'code' => 'PSHS-EVC',   'name' => 'Eastern Visayas Campus'],
        ['slug' => 'irc',   'code' => 'PSHS-IRC',   'name' => 'Ilocos Region Campus'],
        ['slug' => 'mrc',   'code' => 'PSHS-MRC',   'name' => 'MIMAROPA Region Campus'],
        ['slug' => 'smc',   'code' => 'PSHS-SMC',   'name' => 'Southern Mindanao Campus'],
        ['slug' => 'src',   'code' => 'PSHS-SRC',   'name' => 'SOCCSKSARGEN Region Campus'],
        ['slug' => 'wvc',   'code' => 'PSHS-WVC',   'name' => 'Western Visayas Campus'],
        ['slug' => 'zrc',   'code' => 'PSHS-ZRC',   'name' => 'Zamboanga Peninsula Region Campus'],
    ];
}

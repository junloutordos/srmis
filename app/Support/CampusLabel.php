<?php

namespace App\Support;

/**
 * Campus wording for printed forms, derived from the current tenant so one
 * template serves OED and all 16 campuses ("Caraga Region Campus" prints as
 * "CAMPUS: CARAGA REGION"; OED prints as "OFFICE: OFFICE OF THE EXECUTIVE
 * DIRECTOR").
 */
class CampusLabel
{
    /**
     * Location details we know for certain. Everything else prints the campus
     * name alone until an admin fills in the Campus row (Data Management), whose
     * address then takes precedence — never guess an address for an official form.
     */
    private const PLACES = [
        'crc' => ['city' => 'Butuan City', 'address' => 'Ampayon, Butuan City, Agusan del Norte'],
    ];

    /** @return array{label: string, value: string}|null  null when there is no tenant (central plane, tests) */
    public static function formLine(): ?array
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return self::fromTenant($tenant?->getTenantKey(), $tenant?->name);
    }

    /** @return array{label: string, value: string}|null */
    public static function fromTenant(?string $id, ?string $name): ?array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        if ($id === 'oed') {
            return ['label' => 'OFFICE', 'value' => mb_strtoupper($name)];
        }

        return [
            'label' => 'CAMPUS',
            'value' => mb_strtoupper(trim(preg_replace('/\s+Campus$/i', '', $name))),
        ];
    }

    /** "CARAGA REGION CAMPUS IN BUTUAN CITY" / "OFFICE OF THE EXECUTIVE DIRECTOR" / null without a tenant. */
    public static function title(bool $upper = true): ?string
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return self::titleFor($tenant?->getTenantKey(), $tenant?->name, $upper);
    }

    public static function titleFor(?string $id, ?string $name, bool $upper = true): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $city = self::PLACES[$id]['city'] ?? null;
        $title = $city ? "{$name} in {$city}" : $name;

        return $upper ? mb_strtoupper($title) : $title;
    }

    /** Street address line: the tenant's Campus row if filled in, else what we know for certain. */
    public static function address(): ?string
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        if (! $tenant) {
            return null;
        }

        try {
            $row = \App\Models\Campus::query()->value('address');
        } catch (\Throwable) {
            $row = null;
        }

        return self::addressFor($tenant->getTenantKey(), $row);
    }

    public static function addressFor(?string $id, ?string $campusRowAddress): ?string
    {
        $row = trim((string) $campusRowAddress);

        return $row !== '' ? $row : (self::PLACES[$id]['address'] ?? null);
    }
}

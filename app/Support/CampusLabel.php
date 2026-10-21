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
}

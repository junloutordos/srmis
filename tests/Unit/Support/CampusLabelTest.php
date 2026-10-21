<?php

namespace Tests\Unit\Support;

use App\Support\CampusLabel;
use PHPUnit\Framework\TestCase;

class CampusLabelTest extends TestCase
{
    public function test_campus_name_drops_the_trailing_campus_word_and_uppercases(): void
    {
        $this->assertSame(['label' => 'CAMPUS', 'value' => 'CARAGA REGION'], CampusLabel::fromTenant('crc', 'Caraga Region Campus'));
        $this->assertSame(['label' => 'CAMPUS', 'value' => 'BICOL REGION'], CampusLabel::fromTenant('brc', 'Bicol Region Campus'));
        $this->assertSame(['label' => 'CAMPUS', 'value' => 'MAIN'], CampusLabel::fromTenant('mc', 'Main Campus'));
        $this->assertSame(['label' => 'CAMPUS', 'value' => 'CENTRAL VISAYAS'], CampusLabel::fromTenant('cvisc', 'Central Visayas campus'));
    }

    public function test_oed_is_labelled_office_not_campus(): void
    {
        $this->assertSame(
            ['label' => 'OFFICE', 'value' => 'OFFICE OF THE EXECUTIVE DIRECTOR'],
            CampusLabel::fromTenant('oed', 'Office of the Executive Director')
        );
    }

    public function test_no_tenant_or_blank_name_yields_no_line(): void
    {
        $this->assertNull(CampusLabel::fromTenant(null, null));
        $this->assertNull(CampusLabel::fromTenant('crc', '   '));
    }
}

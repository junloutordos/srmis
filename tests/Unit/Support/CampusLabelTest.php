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

    public function test_title_keeps_the_known_caraga_location_and_names_other_campuses_plainly(): void
    {
        $this->assertSame('CARAGA REGION CAMPUS IN BUTUAN CITY', CampusLabel::titleFor('crc', 'Caraga Region Campus'));
        $this->assertSame('Caraga Region Campus in Butuan City', CampusLabel::titleFor('crc', 'Caraga Region Campus', false));
        $this->assertSame('BICOL REGION CAMPUS', CampusLabel::titleFor('brc', 'Bicol Region Campus'));
        $this->assertSame('OFFICE OF THE EXECUTIVE DIRECTOR', CampusLabel::titleFor('oed', 'Office of the Executive Director'));
        $this->assertNull(CampusLabel::titleFor(null, null));
    }

    public function test_address_prefers_the_campus_row_then_known_data_then_nothing(): void
    {
        $this->assertSame('1 Main St, Naga City', CampusLabel::addressFor('brc', '1 Main St, Naga City'));
        $this->assertSame('Ampayon, Butuan City, Agusan del Norte', CampusLabel::addressFor('crc', null));
        $this->assertSame('Ampayon, Butuan City, Agusan del Norte', CampusLabel::addressFor('crc', '  '));
        $this->assertNull(CampusLabel::addressFor('brc', null));
    }
}

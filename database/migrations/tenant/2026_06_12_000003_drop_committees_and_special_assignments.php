<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The Committees and Special Assignments reference pages were removed from
 * SRMIS (they only fed CRCMIS's Performance Management module, which was
 * never extracted). Drop their tables from already-provisioned schemas;
 * fresh tenants no longer create them (removed from the baseline SQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('committee_user');
        Schema::dropIfExists('committees');
        Schema::dropIfExists('special_assignment_user');
        Schema::dropIfExists('special_assignments');
    }

    public function down(): void
    {
        // Intentionally irreversible — the tables only existed for the
        // unextracted PM module and held no SRMIS data.
    }
};

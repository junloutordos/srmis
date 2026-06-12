<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the full SRMIS tenant schema (54 tables) for a campus.
 *
 * The DDL was extracted verbatim from the CRCMIS monolith's migration
 * history (database/schema/srmis-tenant-tables.sql) so module code ported from
 * the monolith finds exactly the columns it expects. New tenant-side
 * changes should be added as regular incremental migrations in this
 * directory after this baseline.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('schema/srmis-tenant-tables.sql'));

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
                if ($statement !== '') {
                    DB::unprepared($statement . ';');
                }
            }
        } finally {
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        $sql = file_get_contents(database_path('schema/srmis-tenant-tables.sql'));

        preg_match_all('/CREATE TABLE `([^`]+)`/', $sql, $m);

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($m[1] as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }
};

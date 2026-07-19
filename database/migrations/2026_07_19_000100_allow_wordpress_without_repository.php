<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE projects MODIFY repository VARCHAR(255) NULL');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE projects ALTER COLUMN repository DROP NOT NULL');
        }
    }

    public function down(): void
    {
        DB::table('projects')->whereNull('repository')->update(['repository' => 'https://github.com/example/wordpress.git']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE projects MODIFY repository VARCHAR(255) NOT NULL');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE projects ALTER COLUMN repository SET NOT NULL');
        }
    }
};

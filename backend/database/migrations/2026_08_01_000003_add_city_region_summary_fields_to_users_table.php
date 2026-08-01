<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'registered_region_name')) {
                $table->string('registered_region_name', 100)->nullable()->after('registered_country_name');
            }

            if (! Schema::hasColumn('users', 'registered_city_name')) {
                $table->string('registered_city_name', 100)->nullable()->after('registered_region_name');
            }

            if (! Schema::hasColumn('users', 'last_seen_region_name')) {
                $table->string('last_seen_region_name', 100)->nullable()->after('last_seen_country_name');
            }

            if (! Schema::hasColumn('users', 'last_seen_city_name')) {
                $table->string('last_seen_city_name', 100)->nullable()->after('last_seen_region_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'registered_city_name',
                'registered_region_name',
                'last_seen_city_name',
                'last_seen_region_name',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

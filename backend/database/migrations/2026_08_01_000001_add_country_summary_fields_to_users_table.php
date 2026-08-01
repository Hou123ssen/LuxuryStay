<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'registered_country_code')) {
                $table->string('registered_country_code', 2)->nullable();
            }

            if (! Schema::hasColumn('users', 'registered_country_name')) {
                $table->string('registered_country_name', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'last_seen_country_code')) {
                $table->string('last_seen_country_code', 2)->nullable();
            }

            if (! Schema::hasColumn('users', 'last_seen_country_name')) {
                $table->string('last_seen_country_name', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'registered_country_code',
                'registered_country_name',
                'last_seen_country_code',
                'last_seen_country_name',
                'last_seen_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

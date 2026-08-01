<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_events')) {
            return;
        }

        Schema::table('analytics_events', function (Blueprint $table) {
            if (! Schema::hasColumn('analytics_events', 'region_name')) {
                $table->string('region_name', 100)->nullable()->after('country_source');
            }

            if (! Schema::hasColumn('analytics_events', 'city_name')) {
                $table->string('city_name', 100)->nullable()->after('region_name');
            }
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->index(['city_name', 'occurred_at'], 'analytics_events_city_occurred_index');
            $table->index(['region_name', 'occurred_at'], 'analytics_events_region_occurred_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('analytics_events')) {
            return;
        }

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex('analytics_events_city_occurred_index');
            $table->dropIndex('analytics_events_region_occurred_index');
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            foreach (['city_name', 'region_name'] as $column) {
                if (Schema::hasColumn('analytics_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

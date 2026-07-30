<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedInteger('risk_score')->default(0);
            $table->json('risk_reasons')->nullable();
            $table->string('ip_hash')->nullable();
            $table->string('user_agent_hash')->nullable();

            $table->index('risk_score');
            $table->index(['status', 'risk_score']);
            $table->index('ip_hash');
            $table->index(['property_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['risk_score']);
            $table->dropIndex(['status', 'risk_score']);
            $table->dropIndex(['ip_hash']);
            $table->dropIndex(['property_id', 'created_at']);
            $table->dropColumn(['risk_score', 'risk_reasons', 'ip_hash', 'user_agent_hash']);
        });
    }
};

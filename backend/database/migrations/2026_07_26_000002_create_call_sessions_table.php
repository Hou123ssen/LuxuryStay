<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('started_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider');
            $table->string('domain');
            $table->string('room_name')->unique();
            $table->string('status')->default('active');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->after('cancelled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('cancellation_actor')->nullable()->after('cancelled_by_user_id');
            $table->text('cancellation_reason')->nullable()->after('cancellation_actor');

            $table->index(['status', 'start_date']);
            $table->index('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'start_date']);
            $table->dropIndex(['cancelled_at']);
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn([
                'cancelled_at',
                'cancellation_actor',
                'cancellation_reason',
            ]);
        });
    }
};

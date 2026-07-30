<?php

use App\Models\Review;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('status')->default(Review::STATUS_PUBLISHED);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['property_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        DB::table('reviews')
            ->whereNull('published_at')
            ->update([
                'status' => Review::STATUS_PUBLISHED,
                'published_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['moderated_by']);
            $table->dropIndex(['property_id', 'status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['status', 'published_at', 'moderated_at', 'moderated_by']);
        });
    }
};

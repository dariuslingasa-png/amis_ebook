<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ebooks', function (Blueprint $table) {
            // Add cover image path column for pre-generated WebP covers
            if (! Schema::hasColumn('ebooks', 'cover_image_path')) {
                $table->string('cover_image_path')->nullable()->after('file_path');
            }

            // Add individual indexes for optimized queries
            // (compound index on [status, grade_level] already exists from initial migration)
            $table->index('grade_level', 'ebooks_grade_level_index');
            $table->index('status', 'ebooks_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebooks', function (Blueprint $table) {
            $table->dropIndex('ebooks_grade_level_index');
            $table->dropIndex('ebooks_status_index');

            if (Schema::hasColumn('ebooks', 'cover_image_path')) {
                $table->dropColumn('cover_image_path');
            }
        });
    }
};

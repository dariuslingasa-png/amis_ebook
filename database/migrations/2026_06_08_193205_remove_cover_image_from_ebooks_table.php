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
            if (Schema::hasColumn('ebooks', 'cover_image')) {
                $table->dropColumn('cover_image');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebooks', function (Blueprint $table) {
            if (! Schema::hasColumn('ebooks', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('file_path');
            }
        });
    }
};

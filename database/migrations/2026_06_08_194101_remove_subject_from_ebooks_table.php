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
            try {
                $table->dropIndex(['status', 'grade_level', 'subject']);
            } catch (Throwable $e) {
                //
            }

            if (Schema::hasColumn('ebooks', 'subject')) {
                $table->dropColumn('subject');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebooks', function (Blueprint $table) {
            if (! Schema::hasColumn('ebooks', 'subject')) {
                $table->string('subject', 150)->nullable()->after('grade_level');
            }
        });
    }
};

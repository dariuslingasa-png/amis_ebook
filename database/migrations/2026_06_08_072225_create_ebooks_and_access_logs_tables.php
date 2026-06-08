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
        Schema::create('ebooks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('grade_level', 100)->nullable(); // e.g. "Grade 4", "Kinder 1"
            $table->string('file_path'); // private storage path
            $table->boolean('is_downloadable')->default(false);
            $table->string('status', 50)->default('published'); // draft / published
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['status', 'grade_level']);
        });

        Schema::create('ebook_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebook_id')->constrained('ebooks')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('action'); // e.g. view, download
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ebook_id', 'user_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebook_access_logs');
        Schema::dropIfExists('ebooks');
    }
};

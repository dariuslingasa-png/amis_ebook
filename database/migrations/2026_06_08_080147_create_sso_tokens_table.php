<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('source_portal', 50)->default('amis_admin'); // which portal issued this
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['token', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_tokens');
    }
};

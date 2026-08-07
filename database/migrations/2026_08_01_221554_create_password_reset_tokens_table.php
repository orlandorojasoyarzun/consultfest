<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Password reset tokens storage.
 *
 * Mirrors the structure Laravel's built-in broker uses, but we don't bind to
 * the default `User` model — this is consumed by our PasswordResetController
 * which talks to the Subscriber model directly.
 *
 * Token is stored as a bcrypt hash so a DB compromise doesn't leak
 * single-use tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};

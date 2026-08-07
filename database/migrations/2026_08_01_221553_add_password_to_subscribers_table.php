<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `password` column to subscribers.
 *
 * Nullable because accounts created via Google OAuth don't have a password
 * — they authenticate only through Google. Form-driven registration always
 * sets one, and "olvidé mi password" can set one for OAuth users later.
 *
 * The `hashed` cast on the model handles bcrypt automatically, so we never
 * store plain text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('production_company');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};

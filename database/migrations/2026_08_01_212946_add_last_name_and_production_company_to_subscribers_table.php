<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds two optional fields to the subscribers table that the explicit
 * registration form (/register) collects but the OAuth flow leaves unset:
 *
 *  - last_name: apellido. Optional — filmmakers commonly register with just
 *    a first name or a pseudonym.
 *  - production_company: nombre de la productora. Optional — many users
 *    are independent / first-time without one.
 *
 * Both columns are nullable so existing Subscriber rows (created via Google
 * OAuth or the old SubscriberForm) survive without backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('name');
            $table->string('production_company')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['last_name', 'production_company']);
        });
    }
};

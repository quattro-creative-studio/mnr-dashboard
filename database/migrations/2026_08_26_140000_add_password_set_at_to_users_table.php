<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a user last chose their own password.
 *
 * Needed because an invited administrator already has a password -- a random
 * one nobody knows -- so the column alone cannot tell an account waiting for
 * its invitation from an active one. Without this, the users list has no way
 * to say which is which, and "resend invitation" reads the same on both.
 *
 * email_verified_at was deliberately left alone: it exists (Laravel ships it)
 * and is unused here, but its name means something specific and reusing it
 * would mislead the next reader.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');
        });

        // Every account that exists at this point demonstrably has a working
        // password, so backfill rather than showing the whole production
        // import as "invitation pending".
        DB::table('users')->update(['password_set_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};

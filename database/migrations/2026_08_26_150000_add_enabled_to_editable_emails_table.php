<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an administrator switch a scheduled mail off for the current edition.
 *
 * Until now the only way to stop a mail was to move its date out of the way,
 * which works by accident: the send gate is an exact-day match, so a date in
 * the past simply never comes round again. That hides the intent (a stale date
 * and a deliberately silenced mail look identical) and destroys the date the
 * next edition will want back.
 *
 * Defaults to true so an imported production database keeps sending exactly
 * what it sends today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editable_emails', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('subject');
        });
    }

    public function down(): void
    {
        Schema::table('editable_emails', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};

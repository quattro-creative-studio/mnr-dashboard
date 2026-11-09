<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a quiz schedule its own reminder instead of waiting for an admin to
 * click "Envoyer rappel".
 *
 * reminder_sent_at is the idempotency mark for quiz:send-reminders: the
 * command runs every minute, and without it every run after reminder_at would
 * mail the same classes again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dateTime('reminder_at')->nullable()->after('closes_at');
            $table->dateTime('reminder_sent_at')->nullable()->after('reminder_at');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['reminder_at', 'reminder_sent_at']);
        });
    }
};

<?php

use App\EditableDate;
use App\EditableEmail;
use Illuminate\Database\Migrations\Migration;

/**
 * Gives every installation the running order production has.
 *
 * sort_order was added in 2023 with a default of 0 and never populated by a
 * migration: production's ordering was typed straight into the database, so a
 * fresh install -- staging included -- listed the mails in whatever order the
 * key happened to sort in, with the whole calendar collapsed onto rank 0.
 *
 * Only sort_order is written. Subject and body are the client's editorial
 * content, edited from /admin/emails and rewritten every edition; a migration
 * that set them would silently overwrite the current year's text the next time
 * it ran on a machine that already had it. sort_order is safe precisely
 * because no screen lets an administrator change it.
 *
 * The ranks come from production. Running this there is a no-op.
 */
return new class extends Migration
{
    /**
     * The contest year, in the order the mails go out.
     */
    private const LIVE = [
        'teacher_confirmation' => 0,
        'newsletter_start' => 1,
        'new_educational_tool' => 2,
        'invite_party' => 3,
        'final' => 4,
        'invite_party_reminder' => 5,
        'invite_party_informations' => 6,
        'invite_party_reminder_second' => 7,
        'invite_party_j_2' => 8,
        'end_year_communication_email' => 9,
    ];

    /**
     * Sent by an action rather than by the calendar, so they have no place in
     * the running order -- but they are live, and belong before the dormant
     * ones. Absent from production; other installations may still have them.
     */
    private const TRANSACTIONAL = [
        'follow_up_3_no' => 10,
        'party_confirmation_no' => 11,
        'party_group_reminder' => 12,
        'final_certificat' => 13,
    ];

    /**
     * The retired follow-up family and the unused newsletters. Kept -- they are
     * toggled back on between editions -- but pushed past everything live so
     * they never sit between two mails that actually go out.
     */
    private const DORMANT = [
        'follow_up_1' => 100,
        'follow_up_1_reminder' => 101,
        'follow_up_1_yes' => 102,
        'follow_up_1_no' => 103,
        'follow_up_2' => 104,
        'follow_up_2_reminder' => 105,
        'follow_up_2_yes' => 106,
        'follow_up_2_no' => 107,
        'follow_up_3' => 108,
        'follow_up_3_reminder' => 109,
        'newsletter_encouragement' => 110,
        'newsletter_1' => 111,
        'newsletter_2' => 112,
    ];

    public function up(): void
    {
        foreach (self::LIVE + self::TRANSACTIONAL + self::DORMANT as $key => $order) {
            EditableEmail::query()->where('key', $key)->update(['sort_order' => $order]);
        }

        // The two dates no mail hangs off. They are shown in their own block on
        // /admin/emails, ordered by this column.
        EditableDate::query()
            ->where('key', EditableDate::TEACHER_INSCRIPTION_START)
            ->update(['sort_order' => 0]);

        EditableDate::query()
            ->where('key', EditableDate::TEACHER_INSCRIPTION_END)
            ->update(['sort_order' => 1]);
    }

    /**
     * Nothing to restore: the previous values were an accident of the 2023
     * default, and reversing to "everything is 0" would only recreate the
     * arbitrary ordering this migration exists to remove.
     */
    public function down(): void
    {
    }
};

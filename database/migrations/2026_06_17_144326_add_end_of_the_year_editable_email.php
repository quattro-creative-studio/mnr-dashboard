<?php

use App\EditableDate;
use App\EditableEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEndOfTheYearEditableEmail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        EditableEmail::updateOrCreate([
            'key' => 'end_year_communication_email',
        ], [
            'title' => "Message de fin d'année",
            'subject' => "Mission Nichtrauchen | Votre avis sur les quiz mensuels du concours Mission Nichtrauchen",
            'text' => '',
            'sort_order' => 9,
        ]);

        EditableDate::updateOrCreate([
            'key' => 'END_YEAR_COMMUNICATION_EMAIL',
        ], [
            'label' => "Message de fin d'année",
            'description' => null,
            'value' => Carbon::now()->addYears(10),
            'sort_order' => 10,
        ]);

        DB::table('editable_date_editable_email')
        ->updateOrInsert([
            'editable_email_key' => 'end_year_communication_email',
        ], [
            'editable_date_key' => 'END_YEAR_COMMUNICATION_EMAIL',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        EditableEmail::where('key', 'final_year_communication_email')->delete();
        EditableDate::where('key', 'END_YEAR_COMMUNICATION_EMAIL')->delete();
    }
}

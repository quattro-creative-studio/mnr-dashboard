<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel 8 added a uuid column to failed_jobs and uses it to identify a failed
 * job for `queue:retry`. This table predates that (created 2018), so the column
 * is missing and the framework's own tooling cannot address individual jobs.
 *
 * Every mail in this application is queued, so failed_jobs is where mail
 * failures land -- this is the table you look at when a teacher says they never
 * received something.
 */
class AddUuidToFailedJobsTable extends Migration
{
    public function up()
    {
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->string('uuid')->after('id')->nullable()->unique();
        });
    }

    public function down()
    {
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
}

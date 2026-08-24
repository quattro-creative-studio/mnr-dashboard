<?php

namespace App\Console\Commands;

use App\Quiz;
use Illuminate\Console\Command;

class QuizUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quiz:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates quiz states if their closing time has been reached.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // now(), not CURRENT_TIMESTAMP. closes_at is written by PHP in the
        // application timezone, so comparing it against the database server's
        // clock asks two different clocks the same question. They agree only
        // by accident: MySQL on a Forge box runs in UTC while this application
        // runs Europe/Luxembourg, which closes every quiz one hour late in
        // winter and two in summer -- silently, since a quiz closing at the
        // wrong hour looks exactly like a quiz closing.
        //
        // Quiz::validate() already used now() for the same decision, so this
        // also removes a genuine disagreement inside the codebase.
        Quiz::query()
            ->where('closes_at', '<=', now())
            ->where('state', '=', Quiz::STATE_RUNNING)
            ->update([
                'state' => Quiz::STATE_CLOSED
            ]);

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\QueueProbeJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QueueTest extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:test {--mail= : Faire aussi envoyer un message par le worker}
                                       {--wait=20 : Secondes d\'attente avant abandon}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify a queue worker is actually consuming jobs';

    /**
     * Execute the console command.
     *
     * deploy:check can only report that the queue is empty, which is equally
     * true of a healthy worker and of no worker at all. This puts a job in and
     * waits for it to come out, which is the only way to tell the two apart.
     *
     * @return int
     */
    public function handle() {
        $token = (string) Str::uuid();
        $recipient = $this->option('mail');
        $wait = max(1, (int) $this->option('wait'));

        $this->line('Connexion : <info>'.config('queue.default').'</info>');

        if (config('queue.default') === 'sync') {
            $this->warn('La connexion est "sync" : le job va s\'exécuter ici même, '
                .'ce qui ne prouve rien sur un worker.');
        }

        QueueProbeJob::dispatch($token, $recipient);
        $this->line('Job déposé, jeton '.$token);

        $key = QueueProbeJob::cacheKey($token);
        $startedAt = microtime(true);

        $bar = $this->output->createProgressBar($wait);
        $bar->start();

        for ($second = 0; $second < $wait; $second++) {
            if (($result = Cache::get($key)) !== null) {
                $bar->finish();
                $this->newLine(2);

                return $this->reportSuccess($result, microtime(true) - $startedAt, $recipient);
            }

            sleep(1);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->error(sprintf('Aucun worker n\'a pris le job en %d secondes.', $wait));
        $this->line('');
        $this->line('Causes probables, par ordre de fréquence :');
        $this->line('  1. Aucun worker ne tourne (Forge > onglet Queue > New Worker).');
        $this->line('  2. Le worker écoute une autre connexion ou une autre file que <info>'
            .config('queue.default').' / '.config('queue.connections.'.config('queue.default').'.queue', 'default').'</info>.');
        $this->line('  3. Le worker tourne encore l\'ancien code : ajouter '
            .'<info>php artisan queue:restart</info> au script de déploiement.');

        return 1;
    }

    private function reportSuccess(array $result, float $elapsed, ?string $recipient): int {
        $this->info(sprintf('Job traité par un worker en %.1f s.', $elapsed));

        if ($recipient === null) {
            $this->line('Le worker consomme la file. Pour vérifier aussi l\'envoi : '
                .'<info>php artisan queue:test --mail=vous@exemple.lu</info>');

            return 0;
        }

        if ($result['mail'] === 'envoyé') {
            $this->info('Message accepté par le serveur SMTP depuis le worker.');

            if (config('mail.always_to')) {
                $this->warn('MAIL_ALWAYS_TO est défini : le message est parti vers '
                    .config('mail.always_to').', pas vers '.$recipient.'.');
            }

            return 0;
        }

        // The worker is alive but cannot send -- the failure this command
        // exists to separate from a dead worker.
        $this->error('Le worker tourne mais l\'envoi a échoué :');
        $this->error('  '.$result['mail']);

        return 1;
    }
}

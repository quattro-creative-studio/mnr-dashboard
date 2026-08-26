<?php

namespace App\Console\Commands;

use App\Quiz;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Answers the pre-production checklist instead of asking a human to remember it.
 *
 * Every item here shares one property: when it is wrong, nothing crashes.
 * The site serves pages, the admin saves an email, the queue accepts a job --
 * and the mail silently never arrives, or arrives with links nobody can open.
 * That is exactly the class of failure a human checklist is worst at catching,
 * because there is nothing to notice.
 *
 * Run it after every deploy. Non-zero exit means do not hand the site over.
 */
class DeployCheck extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the deployed environment before handing the site over';

    const OK = 'OK';
    const WARN = 'ATTENTION';
    const FAIL = 'ÉCHEC';

    /** @var array */
    private $rows = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        $this->checkConfigurationCache();
        $this->checkEnvironment();
        $this->checkApplicationKey();
        $this->checkPublicUrl();
        $this->checkDatabase();
        $this->checkMail();
        $this->checkQueue();
        $this->checkScheduler();
        $this->checkStorage();
        $this->checkSessions();
        $this->reportQuizThreshold();

        $this->table(['Contrôle', 'État', 'Détail'], $this->rows);

        $failures = count(array_filter($this->rows, function ($row) {
            return $row[1] === self::FAIL;
        }));

        if ($failures > 0) {
            $this->error(sprintf('%d contrôle(s) en échec. Ne pas livrer en l\'état.', $failures));

            return 1;
        }

        $this->info('Tous les contrôles bloquants sont passés.');

        return 0;
    }

    private function add(string $label, string $state, string $detail): void {
        $this->rows[] = [$label, $state, $detail];
    }

    /**
     * A stale config cache makes every other check here answer the wrong
     * question.
     *
     * `php artisan config:cache` compiles .env into bootstrap/cache/config.php
     * and the application then never reads .env again. Edit .env afterwards --
     * to fix a URL, to paste mail credentials -- and nothing changes, with no
     * warning anywhere. Forge caches config on every deploy, so any .env edit
     * made through the Forge UI between deploys leaves exactly this state.
     *
     * Without this check the symptom is baffling: deploy:check reports
     * APP_URL as http:// while .env plainly says https://, and the hunt starts
     * in the wrong place. Run first, so the diagnosis comes before the
     * consequences.
     *
     * @return void
     */
    private function checkConfigurationCache(): void {
        if (! file_exists($this->laravel->getCachedConfigPath())) {
            // Not an error: Forge caches config on deploy, but an uncached
            // application simply reads .env and cannot be stale.
            $this->add('Cache de configuration', self::OK, 'absent — .env lu directement');

            return;
        }

        $envPath = base_path('.env');

        if (! is_readable($envPath)) {
            $this->add('Cache de configuration', self::WARN, '.env illisible, fraîcheur invérifiable');

            return;
        }

        // Compared by hand rather than by reloading Dotenv: loading it here
        // would mutate the environment this very command is judging.
        $raw = [];
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $raw[trim($key)] = trim(trim($value), "\"'");
        }

        $watched = [
            'APP_ENV' => 'app.env',
            'APP_URL' => 'app.url',
            'MAIL_HOST' => 'mail.host',
            'MAIL_USERNAME' => 'mail.username',
            'MAIL_FROM_ADDRESS' => 'mail.from.address',
            'DB_DATABASE' => 'database.connections.mysql.database',
        ];

        $stale = [];

        foreach ($watched as $envKey => $configKey) {
            if (! array_key_exists($envKey, $raw)) {
                continue;
            }

            // Skip interpolated values; resolving them means running Dotenv.
            if (strpos($raw[$envKey], '${') !== false) {
                continue;
            }

            if ((string) config($configKey) !== $raw[$envKey]) {
                $stale[] = $envKey;
            }
        }

        $this->add(
            'Cache de configuration',
            $stale ? self::FAIL : self::OK,
            $stale
                ? 'PÉRIMÉ — .env et cache divergent sur '.implode(', ', $stale)
                    .'. Lancer: php artisan config:clear && php artisan config:cache'
                : 'à jour'
        );
    }

    private function checkEnvironment(): void {
        $env = config('app.env');
        $this->add('APP_ENV', $env === 'production' ? self::OK : self::WARN, $env);

        // Debug pages print the whole environment, database credentials and the
        // mail password included, to anyone who triggers an error.
        $this->add(
            'APP_DEBUG',
            config('app.debug') ? self::FAIL : self::OK,
            config('app.debug') ? 'activé — expose la configuration entière' : 'désactivé'
        );
    }

    private function checkApplicationKey(): void {
        $key = config('app.key');

        // This application encrypts nothing of its own -- no Crypt::, no
        // encrypted casts, no signed routes -- so a regenerated key costs
        // sessions and cookies, not data. Copy it anyway; a mass logout in the
        // middle of the registration window is its own kind of incident.
        $this->add(
            'APP_KEY',
            $key ? self::OK : self::FAIL,
            $key ? 'définie (à copier depuis l\'ancien serveur, jamais régénérer)' : 'absente'
        );
    }

    private function checkPublicUrl(): void {
        $url = (string) config('app.url');

        // Mails are built by queue workers, which have no HTTP request at all:
        // Laravel fabricates one from APP_URL at boot. So APP_URL alone decides
        // the scheme of every certificate, party and quiz link ever emailed.
        // fastcgi_param only settles links generated during a web request.
        $secure = strpos($url, 'https://') === 0;
        $local = preg_match('/localhost|\.test$|\.local$|127\.0\.0\.1/', $url) === 1;

        $this->add(
            'APP_URL',
            $secure && ! $local ? self::OK : self::FAIL,
            $url.($secure ? '' : ' — les liens des mails partiront en http://')
        );

        $this->add(
            'APP_URL sans barre finale',
            substr($url, -1) === '/' ? self::WARN : self::OK,
            substr($url, -1) === '/' ? 'produit des doubles barres dans les liens' : 'correcte'
        );
    }

    private function checkDatabase(): void {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->add('Base de données', self::FAIL, 'connexion impossible : '.$e->getMessage());

            return;
        }

        $this->add('Base de données', self::OK, 'connectée à '.DB::connection()->getDatabaseName());

        try {
            $ran = DB::table('migrations')->count();
            $files = count(glob(database_path('migrations/*.php')));

            $this->add(
                'Migrations',
                $ran >= $files ? self::OK : self::FAIL,
                sprintf('%d appliquée(s) pour %d fichier(s)', $ran, $files)
            );
        } catch (Throwable $e) {
            $this->add('Migrations', self::FAIL, 'table migrations illisible');
        }
    }

    private function checkMail(): void {
        $driver = config('mail.driver') ?: config('mail.default');

        $this->add(
            'Transport mail',
            in_array($driver, ['log', 'array', 'null'], true) ? self::FAIL : self::OK,
            $driver.'@'.config('mail.host')
        );

        $missing = array_keys(array_filter([
            'MAIL_USERNAME' => ! config('mail.username'),
            'MAIL_PASSWORD' => ! config('mail.password'),
            'MAIL_FROM_ADDRESS' => ! config('mail.from.address'),
        ]));

        $this->add(
            'Identifiants mail',
            $missing ? self::FAIL : self::OK,
            $missing ? 'manquant(s) : '.implode(', ', $missing) : 'complets'
        );

        $this->add(
            'TLS exigé',
            config('mail.require_tls') ? self::OK : self::WARN,
            config('mail.require_tls') ? 'oui' : 'non — la clé API peut partir en clair'
        );

        // Set on a developer machine to keep test mail away from teachers. In
        // production it would divert every message the contest depends on.
        $this->add(
            'MAIL_ALWAYS_TO',
            config('mail.always_to') ? self::FAIL : self::OK,
            config('mail.always_to')
                ? 'tout le courrier serait détourné vers '.config('mail.always_to')
                : 'non défini'
        );
    }

    private function checkQueue(): void {
        $connection = config('queue.default');

        // Nothing in this application sends mail directly; everything is
        // ->queue()d. No worker means no mail, with no error anywhere.
        $this->add(
            'Connexion de queue',
            $connection === 'sync' ? self::WARN : self::OK,
            $connection.($connection === 'sync' ? ' — envoi synchrone, pas de worker' : '')
        );

        if ($connection !== 'database') {
            return;
        }

        try {
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $stalled = $oldest !== null && $oldest < now()->subMinutes(5)->getTimestamp();

            $this->add(
                'Worker de queue',
                $stalled ? self::FAIL : self::OK,
                $stalled
                    ? sprintf('%d job(s) en attente, le plus ancien depuis %s — worker arrêté ?',
                        $pending, now()->createFromTimestamp($oldest)->diffForHumans())
                    : sprintf('%d job(s) en attente, aucun bloqué', $pending)
            );

            $failed = DB::table('failed_jobs')->count();
            $this->add(
                'Jobs en échec',
                $failed > 0 ? self::WARN : self::OK,
                $failed > 0 ? $failed.' — table failed_jobs à examiner' : 'aucun'
            );
        } catch (Throwable $e) {
            $this->add('Worker de queue', self::WARN, 'tables jobs illisibles');
        }
    }

    private function checkScheduler(): void {
        // quiz:update runs every minute. A quiz still open well past its
        // closing time is the one symptom of a dead scheduler this application
        // produces on its own, without extra machinery.
        try {
            $overdue = Quiz::query()
                ->where('state', Quiz::STATE_RUNNING)
                ->where('closes_at', '<', now()->subMinutes(5))
                ->count();
        } catch (Throwable $e) {
            $this->add('Ordonnanceur', self::WARN, 'table quizzes illisible');

            return;
        }

        $this->add(
            'Ordonnanceur',
            $overdue > 0 ? self::FAIL : self::OK,
            $overdue > 0
                ? $overdue.' quiz dépassé(s) et toujours ouvert(s) — quiz:update ne tourne pas'
                : 'aucun quiz en retard (absence de symptôme, pas une preuve)'
        );
    }

    private function checkStorage(): void {
        foreach (['certificats', 'documents', 'quiz-maker-hooks'] as $directory) {
            try {
                $probe = $directory.'/.deploy-check';
                Storage::put($probe, 'ok');
                $written = Storage::get($probe) === 'ok';
                Storage::delete($probe);
            } catch (Throwable $e) {
                $written = false;
            }

            $this->add(
                'storage/app/'.$directory,
                $written ? self::OK : self::FAIL,
                $written ? 'accessible en écriture' : 'écriture impossible'
            );
        }
    }

    private function checkSessions(): void {
        $driver = config('session.driver');

        $this->add(
            'Sessions',
            $driver === 'array' ? self::FAIL : self::OK,
            $driver.($driver === 'array' ? ' — aucune session ne survit à la requête' : '')
        );
    }

    private function reportQuizThreshold(): void {
        $minimum = config('app.minimum_required_quiz_responses');

        // No right answer to assert: it must match the number of quizzes
        // planned for this edition. Reported so it is seen, not guessed.
        $this->add(
            'Quiz requis (fête, certificat)',
            $minimum > 0 ? self::OK : self::WARN,
            $minimum.($minimum > 0 ? '' : ' — toutes les classes deviennent éligibles')
        );
    }
}

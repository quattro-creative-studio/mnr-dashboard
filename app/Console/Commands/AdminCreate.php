<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminCreate extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create {email? : Adresse de connexion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an administrator account';

    /**
     * Execute the console command.
     *
     * The password is asked for interactively rather than passed as an
     * argument: a command line ends up in the shell history and in the process
     * list, where any other user on the box can read it.
     *
     * @return int
     */
    public function handle() {
        $email = $this->argument('email') ?: $this->ask('Adresse email');

        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email|unique:users,email',
        ], [
            'email.unique' => 'Un compte existe déjà avec cette adresse.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return 1;
        }

        $password = $this->secret('Mot de passe (masqué)');

        if ($password === null || strlen($password) < 8) {
            $this->error('Le mot de passe doit faire au moins 8 caractères.');

            return 1;
        }

        if ($password !== $this->secret('Confirmer le mot de passe')) {
            $this->error('Les deux saisies diffèrent.');

            return 1;
        }

        // teacher_id stays null on purpose. RedirectIfAuthenticated tests for a
        // teacher record BEFORE it tests the admin type, so an administrator
        // carrying one would be sent to the teacher section and never reach
        // the admin area at all.
        $admin = User::create([
            'email' => $email,
            'password' => Hash::make($password),
            'type' => User::TYPE_ADMIN,
        ]);

        $this->info('Administrateur créé : '.$admin->email);
        $this->line('Connexion : '.route('login'));

        return 0;
    }
}

<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run() {
        $this->call(SchoolSeeder::class);

        $pass = \Illuminate\Support\Str::random();
        echo "Generated password for gilles@apps.lu: $pass" . PHP_EOL;
        \App\User::createUser('gilles@apps.lu', $pass, \App\User::TYPE_ADMIN);
    }
}

<?php

namespace Database\Seeders;
class TestDataSeeder extends \Illuminate\Database\Seeder {

    public function run() {
        \App\User::factory()->count(20)->create();
        \App\SchoolClass::factory()->count(40)->create();
    }

}

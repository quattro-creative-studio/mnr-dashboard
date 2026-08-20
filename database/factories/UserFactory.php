<?php

use Faker\Generator as Faker;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(App\SchoolClass::class, function (Faker $faker) {
    return [
        'name' => $faker->numberBetween(5,9) . $faker->randomLetter .  $faker->randomLetter . $faker->numberBetween(1,6),
        'students' => $faker->numberBetween(11,25),
        'school_id' => \App\School::query()->inRandomOrder()->first()->id,
        'teacher_id' => \App\Teacher::query()->inRandomOrder()->first()->id,
    ];
});

$factory->define(App\Teacher::class, function (Faker $faker) {
    return [
        'first_name' => $faker->firstName,
        'last_name' => $faker->lastName,
        'phone' => $faker->phoneNumber,
        'salutation_id' => \App\Salutation::query()->inRandomOrder()->first()->id,
    ];
});

$factory->define(App\User::class, function (Faker $faker) {
    return [
        'email' => $faker->unique()->safeEmail,
        'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
        'remember_token' => Str::random(10),
        'type' => \App\User::TYPE_TEACHER,
        'teacher_id' => factory(\App\Teacher::class)->create()->id,
    ];
});

<?php

namespace Database\Factories;

use App\Salutation;
use App\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition()
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'phone' => $this->faker->phoneNumber,
            'salutation_id' => Salutation::query()->inRandomOrder()->first()->id,
        ];
    }
}

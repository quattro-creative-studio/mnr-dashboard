<?php

namespace Database\Factories;

use App\School;
use App\SchoolClass;
use App\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition()
    {
        return [
            'name' => $this->faker->numberBetween(5, 9)
                .$this->faker->randomLetter
                .$this->faker->randomLetter
                .$this->faker->numberBetween(1, 6),
            'students' => $this->faker->numberBetween(11, 25),
            'school_id' => School::query()->inRandomOrder()->first()->id,
            'teacher_id' => Teacher::query()->inRandomOrder()->first()->id,
        ];
    }
}

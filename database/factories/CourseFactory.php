<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(3),
            'author_id' => Author::factory(),
            'type' => fake()->randomElement(['lifestyle', 'professional']),
            'price' => fake()->randomFloat(2, 9.99, 99.99),
            'item_code' => $this->faker->unique()->bothify('KURS-###'),
        ];
    }
}

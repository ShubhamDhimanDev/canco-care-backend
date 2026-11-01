<?php

namespace Database\Factories;

use App\Enums\StoryStatus;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Story>
 */
class StoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'status' => StoryStatus::Published,
            'published_at' => now()->subDay(),
        ];
    }

    /**
     * Indicate that the story is an unpublished draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoryStatus::Draft,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the story is published but scheduled for the future.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoryStatus::Published,
            'published_at' => now()->addDay(),
        ]);
    }
}

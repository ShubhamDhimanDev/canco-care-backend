<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * A published event that starts in two weeks.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);
        $start = now()->addDays(14)->setTime(18, 0);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(3),
            'timezone' => 'America/Vancouver',
            'is_all_day' => false,
            'location_name' => 'Community Hall',
            'city' => 'Kelowna',
            'province' => 'BC',
            'status' => EventStatus::Published,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Draft, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now()->addDay()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Cancelled]);
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(10)->addHours(3),
            'published_at' => now()->subDays(30),
        ]);
    }
}

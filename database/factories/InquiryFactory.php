<?php

namespace Database\Factories;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => (string) Str::ulid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+14035551234',
            'message' => fake()->paragraph(),
            'consent_at' => now(),
            'status' => InquiryStatus::New,
        ];
    }

    public function status(InquiryStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

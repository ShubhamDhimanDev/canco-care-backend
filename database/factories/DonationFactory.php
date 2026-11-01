<?php

namespace Database\Factories;

use App\Enums\DonationStatus;
use App\Models\Donation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    /**
     * A pending $50 donation from a BC donor, taken through the (fake) test gateway.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => (string) Str::uuid(),
            'status' => DonationStatus::Pending,
            'amount_cents' => 5000,
            'currency' => 'CAD',
            'donor_first_name' => fake()->firstName(),
            'donor_last_name' => fake()->lastName(),
            'donor_email' => fake()->unique()->safeEmail(),
            'address_line1' => '123 Main Street',
            'city' => 'Kelowna',
            'province' => 'BC',
            'postal_code' => 'V1X 2A1',
            'country' => 'CA',
            'consent_at' => now(),
            'gateway' => 'fake',
            'is_test' => true,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    /**
     * A paid donation with a receipt.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DonationStatus::Succeeded,
            'paid_at' => now(),
            'gateway_transaction_id' => 'fake_'.Str::lower(Str::random(10)),
            'card_brand' => 'Visa',
            'card_last4' => '4242',
            'receipt_number' => now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'receipt_issued_at' => now(),
        ]);
    }

    /**
     * Taken through a real gateway (counts towards the impact figure).
     */
    public function live(): static
    {
        return $this->state(fn () => ['gateway' => 'global_payments', 'is_test' => false]);
    }
}

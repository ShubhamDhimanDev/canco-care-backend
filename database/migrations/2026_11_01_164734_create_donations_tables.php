<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Donations (docs/plan/08-feature-donations.md, docs/plan/13 §7). Money is integer cents.
     * Card numbers, expiry dates, security codes and payment tokens are NEVER stored: only the
     * gateway's transaction id and the card's brand and last four digits, for display.
     */
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('CAD');

            // The event/campaign the donor chose; the title is copied so a receipt never changes.
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('event_title', 200)->nullable();

            $table->string('donor_first_name', 80);
            $table->string('donor_last_name', 80);
            $table->string('donor_email', 190)->index();
            $table->string('donor_phone', 30)->nullable();
            // A Canadian address is needed for an official tax receipt.
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 100);
            $table->string('province', 2);
            $table->string('postal_code', 10);
            $table->char('country', 2)->default('CA');
            $table->timestamp('consent_at');

            $table->string('gateway', 24);
            // True for the fake gateway and for a provider's sandbox: the receipt says so.
            $table->boolean('is_test')->default(false);
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->string('gateway_response_code', 40)->nullable();
            $table->string('gateway_response_message')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('card_brand', 20)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->string('idempotency_key', 64)->unique();

            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->string('receipt_number', 40)->nullable()->unique();
            $table->timestamp('receipt_issued_at')->nullable();
            $table->timestamp('receipt_sent_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'paid_at']);
        });

        Schema::create('donation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            // Sanitised: never a card number, token or secret.
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_events');
        Schema::dropIfExists('donations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-managed events (docs/plan/06-feature-events.md, docs/plan/02-data-model.md "events").
     * Times are stored in UTC; `timezone` is the IANA zone the organiser means ("6 PM in Vancouver").
     * `show_in_donation_form` puts the event in the donation form's "Support an event" dropdown.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('summary', 300);
            $table->longText('description')->nullable();

            $table->string('image_path')->nullable();
            $table->string('image_alt', 200)->nullable();
            $table->unsignedSmallInteger('image_width')->nullable();
            $table->unsignedSmallInteger('image_height')->nullable();

            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone', 64)->default('America/Vancouver');
            $table->boolean('is_all_day')->default(false);

            $table->string('location_name', 160)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 2)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('online_url')->nullable();
            $table->string('registration_url')->nullable();
            $table->string('cost_note', 120)->nullable();

            $table->string('status', 12)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('show_in_donation_form')->default(false);

            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

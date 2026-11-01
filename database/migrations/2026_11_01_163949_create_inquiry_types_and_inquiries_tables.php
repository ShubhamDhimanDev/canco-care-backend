<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inquiry types are admin data (docs/plan/13 §6): the visitor picks one in a dropdown, and every
     * inquiry of that type is emailed to the type's address. An inquiry keeps a copy of the type's
     * name, so its history survives the type being renamed or deleted.
     */
    public function up(): void
    {
        Schema::create('inquiry_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 60)->unique();
            $table->string('email', 190);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 26)->unique();
            $table->foreignId('inquiry_type_id')->nullable()->constrained('inquiry_types')->nullOnDelete();
            $table->string('type_name', 80)->nullable();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 190)->index();
            $table->string('phone', 30);
            $table->text('message');
            $table->timestamp('consent_at');
            $table->string('source_url')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('status', 12)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->text('admin_notes')->nullable();
            // Where the staff notification went (the type's address, or the fallback list).
            $table->string('notified_to', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('inquiry_types');
    }
};

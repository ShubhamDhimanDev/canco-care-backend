<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit trail: who did what, when, from where, and what changed. Rows are only ever added
     * (there is no updated_at and no admin screen that edits or deletes them).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // The person, plus a snapshot of who they were: the entry must stay readable after the account is deleted.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            // admin (a signed-in person), website (the public site / API), console (artisan, scheduler, queue)
            $table->string('source', 20)->default('admin');
            // created, updated, trashed, restored, deleted, login, logout, login_failed, exported, resent_receipt, ...
            $table->string('event', 40);
            // story, event, inquiry, inquiry_type, media, setting, user, role, donation, auth
            $table->string('auditable_type', 40);
            $table->string('auditable_id', 64)->nullable();
            // A readable name taken at the time ("Spring food drive"), so the list needs no joins.
            $table->string('auditable_label')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('event');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

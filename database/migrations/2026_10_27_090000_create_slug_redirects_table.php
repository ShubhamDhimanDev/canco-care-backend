<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // When a published slug changes, the old URL keeps working via a redirect.
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('redirectable_type', 32);
            $table->unsignedBigInteger('redirectable_id');
            $table->string('old_slug', 220);
            $table->timestamps();

            $table->unique(['redirectable_type', 'old_slug']);
            $table->index(['redirectable_type', 'redirectable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};

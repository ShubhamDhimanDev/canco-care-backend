<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blog upgrade (docs/plan/13 §3): tags, a "feature on the home page" switch, and the SEO /
     * answer-engine fields (quick answer, FAQ, noindex).
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('slug', 60)->unique();
            $table->timestamps();
        });

        Schema::create('story_tag', function (Blueprint $table) {
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['story_id', 'tag_id']);
        });

        Schema::table('stories', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('author_name');
            $table->string('summary', 300)->nullable()->after('excerpt');
            $table->json('faq')->nullable()->after('summary');
            $table->boolean('noindex')->default(false)->after('meta_description');
            $table->index(['is_featured', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropIndex(['is_featured', 'published_at']);
            $table->dropColumn(['is_featured', 'summary', 'faq', 'noindex']);
        });

        Schema::dropIfExists('story_tag');
        Schema::dropIfExists('tags');
    }
};

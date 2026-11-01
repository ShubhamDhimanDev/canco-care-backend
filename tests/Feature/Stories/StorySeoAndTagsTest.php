<?php

use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

function seoInput(array $overrides = []): array
{
    return [
        'title' => 'Winter Coat Drive',
        'body' => '<p>We collected 300 coats for families across Kelowna this winter.</p>',
        'status' => 'published',
        ...$overrides,
    ];
}

describe('tags', function () {
    test('are created from the names an editor typed and attached to the story', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('stories.store'), seoInput(['tags' => ['Make-A-Wish', ' Community  ', 'make a wish', '']]))
            ->assertSessionHasNoErrors();

        $story = Story::firstOrFail();

        // "Make-A-Wish" and "make a wish" make the same address, so they are one tag.
        expect($story->tags->pluck('name')->all())->toBe(['Community', 'Make-A-Wish'])
            ->and(Tag::pluck('slug')->sort()->values()->all())->toBe(['community', 'make-a-wish']);
    });

    test('are replaced on edit and unused tags are kept for reuse', function () {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('stories.store'), seoInput(['tags' => ['One', 'Two']]));
        $story = Story::firstOrFail();

        $this->actingAs($user)->put(route('stories.update', $story), seoInput(['tags' => ['Two', 'Three']]))->assertSessionHasNoErrors();

        expect($story->fresh()->tags->pluck('name')->all())->toBe(['Three', 'Two'])
            ->and(Tag::count())->toBe(3);
    });

    test('are limited to ten per story', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('stories.store'), seoInput(['tags' => array_map(fn (int $i): string => "tag {$i}", range(1, 11))]))
            ->assertSessionHasErrors('tags');
    });
});

describe('answer-engine fields', function () {
    test('quick answer, FAQ and noindex are saved; incomplete FAQ rows are dropped', function () {
        $this->actingAs(User::factory()->create())->post(route('stories.store'), seoInput([
            'summary' => 'Canco collected 300 coats for Kelowna families.',
            'noindex' => '1',
            'faq' => [
                ['question' => ' How many coats? ', 'answer' => '300 coats.'],
                ['question' => '', 'answer' => ''],
            ],
        ]))->assertSessionHasNoErrors();

        $story = Story::firstOrFail();

        expect($story->summary)->toBe('Canco collected 300 coats for Kelowna families.')
            ->and($story->noindex)->toBeTrue()
            ->and($story->faq)->toBe([['question' => 'How many coats?', 'answer' => '300 coats.']]);
    });

    test('a question without an answer is rejected', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('stories.store'), seoInput(['faq' => [['question' => 'Why?', 'answer' => '']]]))
            ->assertSessionHasErrors('faq.0.answer');
    });

    test('the API exposes them with a reading time', function () {
        Story::factory()->create([
            'slug' => 'coats',
            'body' => '<p>'.str_repeat('word ', 450).'</p>',
            'summary' => 'Short answer.',
            'faq' => [['question' => 'Q?', 'answer' => 'A.']],
            'noindex' => true,
        ]);

        $this->getJson('/api/v1/stories/coats')
            ->assertOk()
            ->assertJsonPath('data.summary', 'Short answer.')
            ->assertJsonPath('data.faq.0.question', 'Q?')
            ->assertJsonPath('data.noindex', true)
            ->assertJsonPath('data.reading_minutes', 3);
    });
});

describe('API tag filter and tag list', function () {
    test('?tag= returns only stories with that tag', function () {
        $wish = Tag::create(['name' => 'Make-A-Wish', 'slug' => 'make-a-wish']);
        Story::factory()->create(['slug' => 'with'])->tags()->attach($wish);
        Story::factory()->create(['slug' => 'without']);

        $this->getJson('/api/v1/stories?tag=make-a-wish')->assertJsonPath('data.*.slug', ['with']);
        $this->getJson('/api/v1/stories?tag=nope')->assertJsonCount(0, 'data');
    });

    test('cards carry their tags', function () {
        $wish = Tag::create(['name' => 'Make-A-Wish', 'slug' => 'make-a-wish']);
        Story::factory()->create(['slug' => 'with'])->tags()->attach($wish);

        $this->getJson('/api/v1/stories')->assertJsonPath('data.0.tags', [['slug' => 'make-a-wish', 'name' => 'Make-A-Wish']]);
    });

    test('/tags lists only tags that have a public story', function () {
        $live = Tag::create(['name' => 'Live', 'slug' => 'live']);
        $hidden = Tag::create(['name' => 'Hidden', 'slug' => 'hidden']);
        Tag::create(['name' => 'Empty', 'slug' => 'empty']);
        Story::factory()->create()->tags()->attach($live);
        Story::factory()->draft()->create()->tags()->attach($hidden);

        $this->getJson('/api/v1/tags')->assertOk()->assertExactJson(['data' => [['slug' => 'live', 'name' => 'Live', 'count' => 1]]]);
    });
});

describe('feature on the home page', function () {
    test('the toggle is saved', function () {
        $this->actingAs(User::factory()->create())->post(route('stories.store'), seoInput(['is_featured' => '1']));

        expect(Story::firstOrFail()->is_featured)->toBeTrue();
    });

    test('unticking it on edit turns it off', function () {
        $user = User::factory()->create();
        $story = Story::factory()->create(['is_featured' => true]);

        $this->actingAs($user)->put(route('stories.update', $story), seoInput())->assertSessionHasNoErrors();

        expect($story->fresh()->is_featured)->toBeFalse();
    });

    test('?featured_first=1 puts featured stories before newer ones', function () {
        Story::factory()->create(['slug' => 'oldest-featured', 'is_featured' => true, 'published_at' => now()->subDays(10)]);
        Story::factory()->create(['slug' => 'newest', 'published_at' => now()->subDay()]);
        Story::factory()->create(['slug' => 'middle', 'published_at' => now()->subDays(5)]);

        $this->getJson('/api/v1/stories?featured_first=1&per_page=3')
            ->assertJsonPath('data.*.slug', ['oldest-featured', 'newest', 'middle']);

        $this->getJson('/api/v1/stories?per_page=3')
            ->assertJsonPath('data.*.slug', ['newest', 'middle', 'oldest-featured']);
    });

    test('a featured draft never shows up', function () {
        Story::factory()->draft()->create(['slug' => 'secret', 'is_featured' => true]);

        $this->getJson('/api/v1/stories?featured_first=1')->assertJsonCount(0, 'data');
    });
});

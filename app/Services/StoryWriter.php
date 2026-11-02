<?php

namespace App\Services;

use App\Enums\StoryStatus;
use App\Models\Media;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The single place where an editor's form input becomes a stored story: sanitising,
 * slugs and redirects, publish dates, and the featured image. Used by the admin
 * controller so create and update cannot drift apart.
 */
class StoryWriter
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
        private readonly SlugService $slugs,
        private readonly StoryImageService $images,
        private readonly TagService $tags,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated StoryRequest input
     */
    public function create(array $data, ?UploadedFile $image, User $user): Story
    {
        $story = new Story;
        $story->created_by = $user->id;

        return $this->persist($story, $data, $image, false, $user);
    }

    /**
     * @param  array<string, mixed>  $data  validated StoryRequest input
     */
    public function update(Story $story, array $data, ?UploadedFile $image, bool $removeImage, User $user): Story
    {
        return $this->persist($story, $data, $image, $removeImage, $user);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(Story $story, array $data, ?UploadedFile $image, bool $removeImage, User $user): Story
    {
        $oldSlug = $story->exists ? $story->slug : null;
        $oldImage = $story->featured_image_path;

        $slug = $data['slug'] ?? null;
        if (blank($slug)) {
            // Blank on create → derive from the title; blank on update → keep the current address.
            $slug = $story->exists ? $story->slug : $this->slugs->forTitle((string) $data['title']);
        }

        $status = StoryStatus::from((string) $data['status']);
        $publishedAt = $data['published_at'] ?? null;
        if ($status === StoryStatus::Published && blank($publishedAt)) {
            $publishedAt = $story->published_at ?? now();
        }

        $story->fill([
            'title' => $data['title'],
            'slug' => $slug,
            'excerpt' => $data['excerpt'] ?? null,
            'summary' => filled($data['summary'] ?? null) ? $data['summary'] : null,
            'faq' => $this->faq($data['faq'] ?? null),
            'body' => $this->sanitizer->sanitize((string) $data['body']),
            'status' => $status,
            'published_at' => blank($publishedAt) ? null : $publishedAt,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'noindex' => (bool) ($data['noindex'] ?? false),
            'author_name' => $data['author_name'] ?? null,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'updated_by' => $user->id,
        ]);

        $newImage = null;
        if ($image !== null) {
            $newImage = $this->images->store($image, $user);
        } elseif (filled($data['featured_media_id'] ?? null)) {
            $newImage = $this->images->fromMedia(Media::findOrFail((int) $data['featured_media_id']));
        }

        if ($newImage !== null) {
            $story->fill([
                'featured_image_path' => $newImage['path'],
                'featured_image_width' => $newImage['width'],
                'featured_image_height' => $newImage['height'],
            ]);
        } elseif ($removeImage) {
            $story->fill([
                'featured_image_path' => null,
                'featured_image_alt' => null,
                'featured_image_width' => null,
                'featured_image_height' => null,
            ]);
        }

        if ($story->featured_image_path !== null) {
            $story->featured_image_alt = $data['featured_image_alt'] ?? $story->featured_image_alt;
        }

        DB::transaction(function () use ($story, $oldSlug, $data): void {
            $story->save();
            $this->tags->sync($story, (array) ($data['tags'] ?? []));

            // A story that has had (or was given) a publish date has a public address worth keeping.
            if ($oldSlug !== null && $oldSlug !== $story->slug && $story->published_at !== null) {
                $this->slugs->recordRename($story, $oldSlug);
            }
        });

        if ($oldImage !== null && $oldImage !== $story->featured_image_path) {
            $this->images->delete($oldImage);
        }

        return $story;
    }

    /**
     * Keeps only complete question/answer pairs, trimmed, in the order given.
     *
     * @return list<array{question: string, answer: string}>|null
     */
    private function faq(mixed $rows): ?array
    {
        $clean = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $question = trim((string) ($row['question'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $clean[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $clean === [] ? null : $clean;
    }
}

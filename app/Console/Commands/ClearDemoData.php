<?php

namespace App\Console\Commands;

use App\Jobs\RevalidateFrontend;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Inquiry;
use App\Models\Media;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

#[Signature('demo:clear {--force : Do not ask for confirmation}')]
#[Description('Delete everything the DemoSeeder created (slugs/references starting with demo-, DEMO-, and @example.test accounts)')]
class ClearDemoData extends Command
{
    /**
     * Only rows with the demo markers are touched: the original stories, the home page, settings and
     * topics are never looked at. Pictures still used by a non-demo story or event are kept.
     */
    public function handle(MediaService $media): int
    {
        if (app()->isProduction()) {
            $this->components->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $stories = Story::withTrashed()->where('slug', 'like', 'demo-%');
        $events = Event::withTrashed()->where('slug', 'like', 'demo-%');
        $inquiries = Inquiry::withTrashed()->where('reference', 'like', 'DEMO-%');
        $donations = Donation::query()->where('idempotency_key', 'like', 'demo-%');
        $users = User::query()->where('email', 'like', '%@example.test');
        $roles = Role::query()->where('name', 'like', 'Demo: %');

        $this->table(['What', 'How many'], [
            ['Stories', $stories->count()],
            ['Events', $events->count()],
            ['Inquiries', $inquiries->count()],
            ['Donations', $donations->count()],
            ['Accounts (@example.test)', $users->count()],
            ['Roles ("Demo: …")', $roles->count()],
            ['Demo pictures', Media::query()->where('original_name', 'like', 'demo-%')->count()],
        ]);

        if (! $this->option('force') && ! $this->confirm('Delete all of it permanently?')) {
            return self::SUCCESS;
        }

        // Stories and events first, so the pictures are no longer "in use" when they are removed.
        $tagIds = Tag::query()->whereHas('stories', fn ($query) => $query->where('slug', 'like', 'demo-%'))->pluck('id');

        $stories->get()->each(function (Story $story): void {
            $story->redirects()->delete();
            $story->forceDelete();
        });
        $events->get()->each(function (Event $event): void {
            $event->redirects()->delete();
            $event->forceDelete();
        });
        $inquiries->get()->each->forceDelete();
        $donations->delete();
        $users->get()->each->delete();
        $roles->get()->each->delete();

        $kept = 0;

        foreach (Media::query()->where('original_name', 'like', 'demo-%')->get() as $item) {
            $stillUsed = Story::withTrashed()->where('featured_image_path', $item->path)->exists()
                || Event::withTrashed()->where('image_path', $item->path)->exists();

            if ($stillUsed) {
                $kept++;

                continue;
            }

            $media->delete($item);
        }

        // Tags that only the demo stories used (the original stories' tags stay).
        Tag::query()->whereKey($tagIds)->whereDoesntHave('stories')->delete();

        RevalidateFrontend::fire(['stories', 'events', 'settings']);

        $this->components->info('Demo data removed.'.($kept > 0 ? " {$kept} picture(s) kept because real content still uses them." : ''));

        return self::SUCCESS;
    }
}

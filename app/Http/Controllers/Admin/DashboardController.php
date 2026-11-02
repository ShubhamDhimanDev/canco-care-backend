<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DonationStatus;
use App\Enums\InquiryStatus;
use App\Enums\StoryStatus;
use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Inquiry;
use App\Models\Media;
use App\Models\Story;
use App\Services\Donations\DonationGateways;
use App\Services\Settings;
use App\Support\ImpactFigure;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page after login: a snapshot of the website's content, the inbox and — for
 * administrators only — the money. Donors' details never leave the donations screens, so the
 * recent-gifts list shows a first name and an initial only.
 */
class DashboardController extends Controller
{
    /** Days shown in the donations chart. */
    private const CHART_DAYS = 30;

    public function __invoke(Request $request, Settings $settings): Response
    {
        $isAdmin = (bool) $request->user()?->isAdmin();

        return Inertia::render('dashboard', [
            'stories' => $this->stories(),
            'events' => $this->events(),
            'inquiries' => $this->inquiries(),
            'library' => ['images' => Media::query()->count()],
            'site' => [
                'impact_text' => (new ImpactFigure($settings))->amountText(),
                'donations_open' => $settings->bool('donations.enabled') && DonationGateways::available(),
            ],
            // Money is administrators-only, the same as the Donations screens.
            'donations' => $isAdmin ? $this->donations() : null,
            'system' => $isAdmin ? $this->system() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stories(): array
    {
        $published = Story::query()->where('status', StoryStatus::Published->value);

        return [
            'published' => (clone $published)->where('published_at', '<=', now())->count(),
            'scheduled' => (clone $published)->where('published_at', '>', now())->count(),
            'drafts' => Story::query()->where('status', StoryStatus::Draft->value)->count(),
            'featured' => Story::published()->where('is_featured', true)->count(),
            'recent' => Story::query()
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Story $story): array => [
                    'id' => $story->id,
                    'title' => $story->title,
                    'state' => $story->publicationState(),
                    'updated_at' => $story->updated_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function events(): array
    {
        $upcoming = fn () => Event::query()->visible()->upcoming();

        return [
            'upcoming' => $upcoming()->count(),
            'drafts' => Event::query()->where('status', 'draft')->count(),
            'next' => $upcoming()
                ->orderBy('starts_at')
                ->limit(5)
                ->get()
                ->map(fn (Event $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'state' => $event->publicationState(),
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'timezone' => $event->timezone,
                    'is_all_day' => $event->is_all_day,
                    'place' => $event->isOnline() ? 'Online' : ($event->city ?: $event->location_name),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inquiries(): array
    {
        return [
            'new' => Inquiry::query()->where('status', InquiryStatus::New->value)->count(),
            'last_30_days' => Inquiry::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'recent' => Inquiry::query()
                ->whereNotIn('status', [InquiryStatus::Spam->value, InquiryStatus::Archived->value])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn (Inquiry $inquiry): array => [
                    'id' => $inquiry->id,
                    'name' => $inquiry->fullName(),
                    'topic' => $inquiry->type_name,
                    'status' => $inquiry->status->value,
                    'excerpt' => str($inquiry->message)->squish()->limit(90)->toString(),
                    'created_at' => $inquiry->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function donations(): array
    {
        $zone = Donation::RECEIPT_TIMEZONE;
        $today = CarbonImmutable::now($zone)->startOfDay();
        $net = DB::raw('COALESCE(SUM(amount_cents - refunded_cents), 0)');

        $monthStart = $today->startOfMonth();
        $lastMonthStart = $monthStart->subMonth();

        $between = fn (CarbonImmutable $from, ?CarbonImmutable $to = null): int => (int) Donation::raised()
            ->where('paid_at', '>=', $from->utc())
            ->when($to, fn ($query) => $query->where('paid_at', '<', $to->utc()))
            ->value($net);

        $raised = Donation::raised();
        $gifts = (clone $raised)->count();
        $total = (int) (clone $raised)->value($net);

        // One bar per day, in the charity's time zone; days without a gift are zero.
        $since = $today->subDays(self::CHART_DAYS - 1);
        $perDay = Donation::raised()
            ->where('paid_at', '>=', $since->utc())
            ->get(['paid_at', 'amount_cents', 'refunded_cents'])
            ->groupBy(fn (Donation $donation): string => $donation->paid_at->setTimezone($zone)->toDateString())
            ->map(fn ($group): int => (int) $group->sum(fn (Donation $donation): int => $donation->netCents()));

        $series = [];
        for ($day = $since; $day <= $today; $day = $day->addDay()) {
            $series[] = ['date' => $day->toDateString(), 'cents' => (int) ($perDay[$day->toDateString()] ?? 0)];
        }

        return [
            'this_month_cents' => $between($monthStart),
            'last_month_cents' => $between($lastMonthStart, $monthStart),
            'total_cents' => $total,
            'gifts' => $gifts,
            'average_cents' => $gifts > 0 ? intdiv($total, $gifts) : 0,
            'test_count' => Donation::query()->where('is_test', true)->count(),
            'pending' => Donation::query()->where('status', DonationStatus::Pending->value)->where('created_at', '<', now()->subHour())->count(),
            'failed_week' => Donation::query()->where('status', DonationStatus::Failed->value)->where('created_at', '>=', now()->subDays(7))->count(),
            'chart' => ['days' => self::CHART_DAYS, 'series' => $series],
            'recent' => Donation::query()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (Donation $donation): array => [
                    'id' => $donation->id,
                    'donor' => trim($donation->donor_first_name.' '.mb_substr($donation->donor_last_name, 0, 1)).'.',
                    'amount_cents' => $donation->amount_cents,
                    'status' => $donation->status->value,
                    'is_test' => $donation->is_test,
                    'event' => $donation->event_title,
                    'date' => ($donation->paid_at ?? $donation->created_at)?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Queue health: the website only refreshes after a change when the worker is running.
     *
     * @return array{queue_waiting: int|null, queue_failed: int|null, gateway_problem: string|null}
     */
    private function system(): array
    {
        $count = fn (string $table): ?int => Schema::hasTable($table) ? DB::table($table)->count() : null;

        return [
            'queue_waiting' => config('queue.default') === 'database' ? $count('jobs') : null,
            'queue_failed' => $count('failed_jobs'),
            'gateway_problem' => DonationGateways::problem(),
        ];
    }
}

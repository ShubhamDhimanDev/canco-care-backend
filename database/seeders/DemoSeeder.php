<?php

namespace Database\Seeders;

use App\Enums\DonationStatus;
use App\Enums\EventStatus;
use App\Enums\InquiryStatus;
use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Jobs\RevalidateFrontend;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Models\Media;
use App\Models\Story;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\MediaService;
use App\Services\PermissionSync;
use App\Services\TagService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Sample content for development and demos: stories, events, inquiries (with topics), donations, pictures
 * and two sub-admin accounts. Run it with:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * - Safe to run again: every record has a fixed key (slug, reference, file name), so it is updated, not duplicated.
 * - Never touches the home page, the site settings, the information pages or the inquiry topics.
 * - The demo stories are dated BEFORE the original ones and are not "featured", so the home page's
 *   "Latest Stories" still shows exactly what it showed before. Find them on the Stories page, page 2.
 * - Refuses to run in production. Remove everything again with `php artisan demo:clear`.
 *
 * Everything it creates is recognisable: slugs start with "demo-", inquiry references with "DEMO-", donation
 * keys with "demo-", picture names with "demo-", and the accounts end in @example.test.
 */
class DemoSeeder extends Seeder
{
    // Model events are off on purpose: the website is told once, at the end, instead of after every row.
    use WithoutModelEvents;

    /** @var list<Media> */
    private array $pictures = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must not run in production: it creates fake people and fake donations.');
        }

        $this->pictures = $this->media();
        $this->stories();
        $this->events();
        $this->inquiries();
        $this->donations();
        $this->access();

        // One refresh for everything that was written (the observers were switched off above).
        RevalidateFrontend::fire(['stories', 'events', 'settings']);

        $this->command?->info('Demo data is in place. Remove it with: php artisan demo:clear');
    }

    // ------------------------------------------------------------------ media

    /**
     * Plain coloured placeholder pictures, converted to WebP by the real media service (so the library, the
     * thumbnails and the picker behave exactly as they do with real uploads).
     *
     * @return list<Media>
     */
    private function media(): array
    {
        $palette = [
            ['demo-warm-sunrise.png', [244, 162, 97], [231, 111, 81], 'Warm sunrise'],
            ['demo-deep-ocean.png', [38, 70, 83], [42, 157, 143], 'Deep ocean'],
            ['demo-meadow.png', [138, 177, 125], [74, 124, 89], 'Meadow'],
            ['demo-lavender.png', [157, 129, 192], [96, 76, 149], 'Lavender'],
            ['demo-golden-hour.png', [252, 211, 96], [233, 150, 40], 'Golden hour'],
            ['demo-sky.png', [135, 206, 235], [70, 130, 180], 'Sky'],
            ['demo-rose.png', [240, 128, 128], [190, 70, 100], 'Rose'],
            ['demo-slate.png', [108, 117, 125], [52, 58, 64], 'Slate'],
        ];

        $service = app(MediaService::class);
        $media = [];

        foreach ($palette as [$name, $from, $to, $label]) {
            $existing = Media::query()->where('original_name', $name)->first();

            if ($existing instanceof Media) {
                $media[] = $existing;

                continue;
            }

            $media[] = $service->store(
                $this->placeholder($name, $from, $to, 'DEMO IMAGE'),
                null,
                "Placeholder picture ({$label}) used for demonstration",
            );
        }

        return $media;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $from
     * @param  array{0: int, 1: int, 2: int}  $to
     */
    private function placeholder(string $name, array $from, array $to, string $label): UploadedFile
    {
        $width = 1600;
        $height = 900;
        $image = imagecreatetruecolor($width, $height);

        // A vertical gradient between two colours.
        for ($y = 0; $y < $height; $y++) {
            $t = $y / ($height - 1);
            $colour = imagecolorallocate(
                $image,
                max(0, min(255, (int) round($from[0] + ($to[0] - $from[0]) * $t))),
                max(0, min(255, (int) round($from[1] + ($to[1] - $from[1]) * $t))),
                max(0, min(255, (int) round($from[2] + ($to[2] - $from[2]) * $t))),
            );
            imageline($image, 0, $y, $width, $y, (int) $colour);
        }

        // A few soft circles, so the pictures are told apart at a glance.
        imagealphablending($image, true);
        $seed = crc32($name);
        for ($i = 0; $i < 5; $i++) {
            $x = (int) (($seed >> ($i * 3)) % $width);
            $y = (int) (($seed >> ($i * 2 + 1)) % $height);
            $size = 180 + (($seed >> $i) % 320);
            imagefilledellipse($image, $x, $y, $size, $size, (int) imagecolorallocatealpha($image, 255, 255, 255, 105));
        }

        // The label: GD's built-in font is tiny, so draw it small and scale it up.
        $small = imagecreatetruecolor(strlen($label) * 9 + 4, 18);
        imagefill($small, 0, 0, (int) imagecolorallocate($small, 0, 0, 0));
        imagecolortransparent($small, (int) imagecolorallocate($small, 0, 0, 0));
        imagestring($small, 5, 2, 1, $label, (int) imagecolorallocate($small, 255, 255, 255));
        $scale = 6;
        $labelWidth = imagesx($small) * $scale;
        $labelHeight = imagesy($small) * $scale;
        imagecopyresized($image, $small, (int) (($width - $labelWidth) / 2), (int) (($height - $labelHeight) / 2), 0, 0, $labelWidth, $labelHeight, imagesx($small), imagesy($small));

        $path = (string) tempnam(sys_get_temp_dir(), 'demo');
        imagepng($image, $path);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function picture(int $index): Media
    {
        return $this->pictures[$index % count($this->pictures)];
    }

    // ---------------------------------------------------------------- stories

    private function stories(): void
    {
        $sanitizer = app(HtmlSanitizer::class);
        $tags = app(TagService::class);
        // Older than the original stories (the newest of those is dated 2023-04-25), see the class comment.
        $base = CarbonImmutable::parse('2023-04-20 10:00:00', 'UTC');

        $stories = [
            ['demo-spring-food-drive-fills-the-pantry', 'Spring food drive fills the community pantry', ['Community', 'Food drive'], 0,
                'Neighbours filled more than 40 crates in a single weekend.',
                '<p>Over one weekend, volunteers collected more than four tonnes of non-perishable food for local pantries.</p><h2>How it came together</h2><p>Schools, small businesses and families all took part. Drop-off points opened at six locations across the city.</p><ul><li>42 crates of canned goods</li><li>18 boxes of baby food and formula</li><li>120 volunteer hours</li></ul><blockquote>We never expected this many people to turn up. It shows what a community can do.</blockquote><p>Thank you to everyone who gave their time.</p>',
                ['What is the food used for?', 'It is shared between three local pantries, which hand it out to families every week.']],
            ['demo-back-to-school-supplies-for-300-kids', 'Back-to-school supplies for 300 children', ['Education', 'Community'], 1,
                'Backpacks, notebooks and lunch kits reached 300 children before the first bell.',
                '<p>Every child deserves to start the school year ready to learn. This September, our supporters funded backpacks full of supplies for 300 children.</p><h2>What was in each bag</h2><ul><li>Notebooks, pencils and a geometry set</li><li>A reusable lunch kit</li><li>A new water bottle</li></ul><p>The bags were handed out at three schools with the help of teachers and parent groups.</p>',
                null],
            ['demo-a-wish-comes-true-for-mia', 'A wish comes true for Mia', ['Make-A-Wish', 'Community'], 2,
                'Mia wanted to meet the animals at the zoo, and the whole neighbourhood helped make it happen.',
                '<p>Mia is nine years old and loves giraffes. In partnership with the Make-A-Wish Foundation, we helped arrange a private visit to the zoo for her and her family.</p><p>She fed a giraffe, met the keepers and went home with a stuffed toy she still carries everywhere.</p><h2>Why it matters</h2><p>A wish gives a child something to look forward to, and gives a family a day to simply be together.</p>',
                ['Can anyone apply for a wish?', 'Wishes are granted through the Make-A-Wish Foundation. We support the foundation by raising funds.']],
            ['demo-thank-you-volunteers-2022', 'Thank you to our volunteers', ['Announcements', 'Community'], 3,
                'A look back at what our volunteers did this year.',
                '<p>Canco Cares runs on volunteers. This year they packed boxes, drove deliveries, ran fundraisers and cheered at finish lines.</p><p>We cannot name everyone here, but we can say this: thank you.</p>',
                null],
            ['demo-golden-years-lunch-program', 'A warm lunch for seniors every Thursday', ['Community'], 4,
                'Our new weekly lunch brings seniors together for a meal and good company.',
                '<p>Every Thursday, a community hall fills with the smell of fresh soup. Our lunch program welcomes seniors for a hot meal, a chat and a game of cards.</p><p>Volunteers are always welcome. Get in touch through the contact page if you would like to help.</p>',
                null],
            ['demo-the-year-in-numbers', 'The year in numbers', ['Announcements'], 5,
                'Families helped, meals shared and wishes supported, in one place.',
                '<h2>2022 at a glance</h2><ul><li>300 children received school supplies</li><li>4 tonnes of food collected</li><li>12 community events</li><li>1 very big thank you</li></ul><p>These numbers are the work of hundreds of people. Here is to doing even more next year.</p>',
                null],
            ['demo-draft-new-partnership-announcement', 'Draft: new partnership announcement', ['Announcements'], 6,
                'A draft that is still being written.',
                '<p>This story is a draft. It is visible in the admin, but not on the website.</p>',
                null, 'draft'],
            ['demo-scheduled-spring-gala-save-the-date', 'Save the date: spring gala', ['Announcements'], 7,
                'Scheduled to go live in the future.',
                '<p>This story is scheduled. It will appear on the website when its publish time arrives.</p>',
                null, 'scheduled'],
        ];

        foreach ($stories as $index => $row) {
            [$slug, $title, $tagNames, $pictureIndex, $excerpt, $body, $faq] = $row;
            $mode = $row[7] ?? 'published';
            $media = $this->picture($pictureIndex);

            $story = Story::query()->withTrashed()->firstOrNew(['slug' => $slug]);
            $story->fill([
                'title' => $title,
                'excerpt' => $excerpt,
                'summary' => $excerpt,
                'faq' => $faq === null ? null : [['question' => $faq[0], 'answer' => $faq[1]]],
                'body' => $sanitizer->sanitize($body),
                'featured_image_path' => $media->path,
                'featured_image_alt' => $media->alt,
                'featured_image_width' => $media->width,
                'featured_image_height' => $media->height,
                'status' => $mode === 'draft' ? StoryStatus::Draft : StoryStatus::Published,
                'published_at' => match ($mode) {
                    'draft' => null,
                    'scheduled' => CarbonImmutable::now()->addDays(30),
                    default => $base->subDays($index * 9),
                },
                'author_name' => 'Canco Cares team',
                'is_featured' => false,
                'noindex' => false,
            ]);
            $story->deleted_at = null;
            $story->save();

            $tags->sync($story, $tagNames);
        }
    }

    // ----------------------------------------------------------------- events

    private function events(): void
    {
        $now = CarbonImmutable::now('America/Vancouver');

        // [slug, title, picture, starts, hours, status, extras]
        /** @var list<array{0: string, 1: string, 2: int, 3: CarbonImmutable, 4: int, 5: string, 6: array<string, mixed>}> $events */
        $events = [
            ['demo-charity-golf-tournament', 'Charity golf tournament', 0, $now->addDays(18)->setTime(9, 0), 7, 'published',
                ['location_name' => 'Lakeview Golf Club', 'address_line1' => '100 Fairway Drive', 'city' => 'Kelowna', 'province' => 'BC', 'postal_code' => 'V1Y 1A1',
                    'registration_url' => 'https://example.com/register', 'cost_note' => '$150 per player, lunch included', 'show_in_donation_form' => true, 'is_featured' => true,
                    'summary' => 'A day on the course raising funds for children’s wishes.',
                    'description' => '<p>Join us for 18 holes, lunch and a silent auction. Every dollar raised supports local families.</p><h2>The day</h2><ul><li>9:00 registration</li><li>10:00 shotgun start</li><li>5:00 awards dinner</li></ul>']],
            ['demo-gala-dinner-and-auction', 'Gala dinner and auction', 1, $now->addDays(45)->setTime(18, 30), 4, 'published',
                ['location_name' => 'Grand Ballroom', 'address_line1' => '55 Harbour Road', 'city' => 'Vancouver', 'province' => 'BC', 'postal_code' => 'V6B 1A1',
                    'cost_note' => 'Tickets from $95', 'show_in_donation_form' => true,
                    'summary' => 'An evening of food, music and a live auction for a good cause.',
                    'description' => '<p>Dress up and join us for a three-course dinner, live music and an auction with items donated by local businesses.</p>']],
            ['demo-online-volunteer-info-session', 'Online volunteer information session', 2, $now->addDays(6)->setTime(19, 0), 1, 'published',
                ['online_url' => 'https://example.com/meet', 'cost_note' => 'Free',
                    'summary' => 'Learn how you can help, from your sofa.',
                    'description' => '<p>A relaxed one-hour video call to explain how volunteering works and answer your questions.</p>']],
            ['demo-community-picnic', 'Community picnic', 3, $now->addDays(25)->startOfDay(), 24, 'published',
                ['location_name' => 'Waterfront Park', 'city' => 'Kelowna', 'province' => 'BC', 'is_all_day' => true, 'cost_note' => 'Free, bring a dish to share',
                    'summary' => 'Games, music and a shared lunch for the whole family.',
                    'description' => '<p>Bring a blanket and something to share. There will be games for children and live music all afternoon.</p>']],
            ['demo-winter-coat-drive', 'Winter coat drive (cancelled)', 4, $now->addDays(10)->setTime(10, 0), 5, 'cancelled',
                ['location_name' => 'Community Hall', 'city' => 'Kamloops', 'province' => 'BC',
                    'summary' => 'This drive has been cancelled; we will share a new date soon.',
                    'description' => '<p>Unfortunately we had to cancel this coat drive. Thank you for your understanding.</p>']],
            ['demo-fuel-stop-celebration', 'Fuel stop celebration', 5, $now->subDays(40)->setTime(17, 0), 3, 'published',
                ['location_name' => 'Canco Fuel Stop', 'city' => 'Kelowna', 'province' => 'BC', 'show_in_donation_form' => true,
                    'summary' => 'A recent event, shown under past events.',
                    'description' => '<p>Thank you to everyone who came. Together we raised funds for the children’s wish program.</p>']],
            ['demo-spring-run-for-wishes', 'Spring run for wishes', 6, $now->subDays(120)->setTime(8, 30), 4, 'published',
                ['location_name' => 'Mission Creek Greenway', 'city' => 'Kelowna', 'province' => 'BC',
                    'summary' => 'Our annual fun run, held in spring.',
                    'description' => '<p>Runners and walkers of every pace took part in the 5 km fun run.</p>']],
            ['demo-draft-fall-fundraiser', 'Draft: fall fundraiser', 7, $now->addDays(60)->setTime(18, 0), 3, 'draft',
                ['location_name' => 'To be confirmed', 'city' => 'Kelowna', 'province' => 'BC',
                    'summary' => 'A draft that is still being planned.',
                    'description' => '<p>This event is a draft and is not visible on the website.</p>']],
        ];

        foreach ($events as [$slug, $title, $pictureIndex, $starts, $hours, $status, $extras]) {
            $media = $this->picture($pictureIndex);
            $event = Event::query()->withTrashed()->firstOrNew(['slug' => $slug]);

            $event->fill([
                'title' => $title,
                'summary' => $extras['summary'],
                'description' => app(HtmlSanitizer::class)->sanitize($extras['description']),
                'image_path' => $media->path,
                'image_alt' => $media->alt,
                'image_width' => $media->width,
                'image_height' => $media->height,
                'starts_at' => $starts->utc(),
                'ends_at' => ($extras['is_all_day'] ?? false) ? null : $starts->addHours($hours)->utc(),
                'timezone' => 'America/Vancouver',
                'is_all_day' => $extras['is_all_day'] ?? false,
                'location_name' => $extras['location_name'] ?? null,
                'address_line1' => $extras['address_line1'] ?? null,
                'city' => $extras['city'] ?? null,
                'province' => $extras['province'] ?? null,
                'postal_code' => $extras['postal_code'] ?? null,
                'online_url' => $extras['online_url'] ?? null,
                'registration_url' => $extras['registration_url'] ?? null,
                'cost_note' => $extras['cost_note'] ?? null,
                'status' => match ($status) {
                    'draft' => EventStatus::Draft,
                    'cancelled' => EventStatus::Cancelled,
                    default => EventStatus::Published,
                },
                'published_at' => $status === 'draft' ? null : CarbonImmutable::now()->subDays(60),
                'is_featured' => $extras['is_featured'] ?? false,
                'show_in_donation_form' => $extras['show_in_donation_form'] ?? false,
            ]);
            $event->deleted_at = null;
            $event->save();
        }
    }

    // ------------------------------------------------------------- inquiries

    private function inquiries(): void
    {
        // The topics are live settings (they fill the contact form's dropdown), so use the ones that exist
        // and never create or change any. With no topics at all, the inquiries simply have none.
        $topics = InquiryType::query()->orderBy('sort_order')->orderBy('id')->get();

        $people = [
            ['Priya', 'Sharma', 'priya.sharma@example.test', '+1 604 555 0142', 'Hello! Our company would like to sponsor the spring gala. Who should we talk to about packages?', InquiryStatus::New, 0],
            ['Daniel', 'Okafor', 'daniel.okafor@example.test', '+1 604 555 0173', 'I run a local newspaper and would love to interview someone about your wish program for a feature.', InquiryStatus::New, 1],
            ['Chloe', 'Tremblay', 'chloe.tremblay@example.test', '+1 250 555 0188', 'My class of 25 students would like to volunteer for a day. Is that possible for under-16s?', InquiryStatus::Read, 2],
            ['Marcus', 'Lee', 'marcus.lee@example.test', '+1 250 555 0111', 'Do you have a printable poster for the golf tournament? I would like to put one up at my gym.', InquiryStatus::Replied, 3],
            ['Aisha', 'Khan', 'aisha.khan@example.test', '+1 778 555 0120', 'I made a donation last week but have not received my receipt yet. Can you check?', InquiryStatus::Replied, 4],
            ['Tom', 'Becker', 'tom.becker@example.test', '+1 778 555 0156', 'Thank you for everything you do. My daughter’s wish was granted last year and we will never forget it.', InquiryStatus::Archived, 5],
            ['Win', 'Ner', 'win.ner@example.test', '+1 000 555 0100', 'CONGRATULATIONS you have been selected for a prize, click here now!!!', InquiryStatus::Spam, 6],
            ['Elena', 'Petrova', 'elena.petrova@example.test', '+1 604 555 0199', 'Is the community picnic suitable for people who use wheelchairs? Thank you.', InquiryStatus::New, 7],
            ['Jordan', 'Wells', 'jordan.wells@example.test', '+1 604 555 0134', 'General question: how are donations split between programs?', InquiryStatus::Read, 8],
        ];

        foreach ($people as $index => [$first, $last, $email, $phone, $message, $status, $daysAgo]) {
            $topic = $topics->isEmpty() ? null : $topics[$index % $topics->count()];
            $received = CarbonImmutable::now()->subDays($daysAgo)->subHours($index * 2);

            $inquiry = Inquiry::query()->withTrashed()->firstOrNew(['reference' => sprintf('DEMO-INQ-%03d', $index + 1)]);
            $inquiry->fill([
                'inquiry_type_id' => $topic?->id,
                'type_name' => $topic?->name,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => $phone,
                'message' => $message,
                'consent_at' => $received,
                'source_url' => 'https://example.com/contact-us',
                'status' => $status,
                'replied_at' => $status === InquiryStatus::Replied ? $received->addHours(5) : null,
                'admin_notes' => $status === InquiryStatus::Replied ? 'Replied by email.' : null,
                'notified_to' => $topic?->email,
            ]);

            if (! $inquiry->exists) {
                $inquiry->setCreatedAt($received);
            }

            $inquiry->deleted_at = null;
            $inquiry->save();
        }
    }

    // -------------------------------------------------------------- donations

    private function donations(): void
    {
        $events = Event::query()->where('slug', 'like', 'demo-%')->pluck('title', 'id');
        $eventIds = $events->keys()->values();

        // [first, last, cents, status, days ago, event index or null]
        /** @var list<array{0: string, 1: string, 2: int, 3: DonationStatus, 4: int, 5: int|null}> $rows */
        $rows = [
            ['Olivia', 'Brown', 5000, DonationStatus::Succeeded, 1, 0],
            ['Noah', 'Wilson', 10000, DonationStatus::Succeeded, 2, 1],
            ['Emma', 'Martin', 2500, DonationStatus::Succeeded, 4, null],
            ['Liam', 'Roy', 7500, DonationStatus::Succeeded, 6, 0],
            ['Sophia', 'Gill', 25000, DonationStatus::Succeeded, 9, 2],
            ['Lucas', 'Chen', 3000, DonationStatus::Succeeded, 12, null],
            ['Ava', 'Singh', 5000, DonationStatus::Failed, 3, 0],
            ['Mason', 'Taylor', 4000, DonationStatus::Pending, 0, null],
        ];

        foreach ($rows as $index => [$first, $last, $cents, $status, $daysAgo, $eventIndex]) {
            $key = sprintf('demo-donation-%03d', $index + 1);
            $when = CarbonImmutable::now()->subDays($daysAgo);
            $eventId = $eventIndex === null || $eventIds->isEmpty() ? null : $eventIds[$eventIndex % $eventIds->count()];
            $succeeded = $status === DonationStatus::Succeeded;

            $donation = Donation::query()->firstOrNew(['idempotency_key' => $key]);

            if ($donation->exists) {
                continue;
            }

            $donation->fill([
                'reference' => (string) Str::uuid(),
                'status' => $status,
                'amount_cents' => $cents,
                'currency' => 'CAD',
                'event_id' => $eventId,
                'event_title' => $eventId === null ? null : $events[$eventId],
                'donor_first_name' => $first,
                'donor_last_name' => $last,
                'donor_email' => Str::lower("{$first}.{$last}@example.test"),
                'address_line1' => '123 Main Street',
                'city' => 'Kelowna',
                'province' => 'BC',
                'postal_code' => 'V1X 2A1',
                'country' => 'CA',
                'consent_at' => $when,
                'gateway' => 'fake',
                'is_test' => true,
                'gateway_transaction_id' => $status === DonationStatus::Pending ? null : 'demo_'.Str::lower(Str::random(10)),
                'failure_reason' => $status === DonationStatus::Failed ? 'The card was declined.' : null,
                'card_brand' => $status === DonationStatus::Pending ? null : 'Visa',
                'card_last4' => $status === DonationStatus::Pending ? null : '4242',
                'paid_at' => $succeeded ? $when : null,
                'receipt_number' => $succeeded ? sprintf('DEMO-%06d', $index + 1) : null,
                'receipt_issued_at' => $succeeded ? $when : null,
                'idempotency_key' => $key,
            ]);
            $donation->setCreatedAt($when);
            $donation->save();
        }
    }

    // ------------------------------------------------------------------ access

    /**
     * Two roles and two sub-admin accounts, so the Users and Roles screens have something to show. The
     * passwords are random and printed once: there are no well-known demo credentials.
     */
    private function access(): void
    {
        (new PermissionSync)->run();

        $writer = Role::findOrCreate('Demo: Content writer', 'web');
        if ($writer->wasRecentlyCreated) {
            $writer->syncPermissions(['stories.view', 'stories.create', 'stories.update', 'events.view', 'events.create', 'events.update', 'media.view', 'media.create']);
        }

        $inbox = Role::findOrCreate('Demo: Inbox manager', 'web');
        if ($inbox->wasRecentlyCreated) {
            $inbox->syncPermissions(['inquiries.view', 'inquiries.update', 'inquiries.export']);
        }

        foreach ([['Demo Writer', 'demo.writer@example.test', $writer], ['Demo Inbox', 'demo.inbox@example.test', $inbox]] as [$name, $email, $role]) {
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            $password = Str::password(16);
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['role' => UserRole::Editor, 'email_verified_at' => now()])->save();
            $user->assignRole($role);

            $this->command?->line("  Demo account: {$email}  password: {$password}  ({$role->name})");
        }
    }
}

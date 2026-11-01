<?php

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Models\User;
use App\Services\Settings;
use App\Support\CsvExport;
use Inertia\Testing\AssertableInertia as Assert;

describe('inbox', function () {
    test('guests are sent to the login page', function () {
        $this->get(route('inquiries.index'))->assertRedirect();
        $this->get(route('inquiries.export'))->assertRedirect();
    });

    test('lists open inquiries with unread first, hiding spam and archived by default', function () {
        Inquiry::factory()->status(InquiryStatus::Read)->create(['first_name' => 'Read', 'created_at' => now()->subDay()]);
        Inquiry::factory()->status(InquiryStatus::New)->create(['first_name' => 'Unread', 'created_at' => now()->subDays(3)]);
        Inquiry::factory()->status(InquiryStatus::Spam)->create(['first_name' => 'Spammer']);
        Inquiry::factory()->status(InquiryStatus::Archived)->create(['first_name' => 'Old']);

        $this->actingAs(User::factory()->create())->get(route('inquiries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('inquiries/index')
                ->has('inquiries.data', 2)
                ->where('inquiries.data.0.name', fn (string $name) => str_starts_with($name, 'Unread'))
                ->where('counts.inbox', 2)->where('counts.new', 1)->where('counts.spam', 1)->where('counts.archived', 1)->where('counts.all', 4));
    });

    test('filters by view, topic and search', function () {
        $media = InquiryType::factory()->create(['slug' => 'media', 'name' => 'Media']);
        Inquiry::factory()->create(['first_name' => 'Maya', 'inquiry_type_id' => $media->id, 'type_name' => 'Media']);
        Inquiry::factory()->create(['first_name' => 'Noah', 'message' => 'About a golf tournament sponsorship']);
        Inquiry::factory()->status(InquiryStatus::Spam)->create(['first_name' => 'Bot']);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('inquiries.index', ['type' => 'media']))
            ->assertInertia(fn (Assert $page) => $page->has('inquiries.data', 1)->where('inquiries.data.0.type', 'Media'));
        $this->actingAs($user)->get(route('inquiries.index', ['search' => 'golf']))
            ->assertInertia(fn (Assert $page) => $page->has('inquiries.data', 1)->where('inquiries.data.0.name', fn (string $n) => str_starts_with($n, 'Noah')));
        $this->actingAs($user)->get(route('inquiries.index', ['view' => 'spam']))
            ->assertInertia(fn (Assert $page) => $page->has('inquiries.data', 1)->where('inquiries.data.0.name', fn (string $n) => str_starts_with($n, 'Bot')));
    });

    test('an unknown view falls back to the inbox', function () {
        $this->actingAs(User::factory()->create())->get(route('inquiries.index', ['view' => 'nonsense']))
            ->assertInertia(fn (Assert $page) => $page->where('filters.view', 'inbox'));
    });

    test('the sidebar badge counts unread inquiries', function () {
        Inquiry::factory()->count(2)->create();
        Inquiry::factory()->status(InquiryStatus::Read)->create();

        $this->actingAs(User::factory()->create())->get(route('inquiries.index'))
            ->assertInertia(fn (Assert $page) => $page->where('unreadInquiries', 2));
    });
});

describe('one inquiry', function () {
    test('opening a new inquiry marks it read', function () {
        $inquiry = Inquiry::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('inquiries.show', $inquiry))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('inquiries/show')->where('inquiry.reference', $inquiry->reference)->where('inquiry.phone', '(403) 555-1234'));

        expect($inquiry->fresh()->status)->toBe(InquiryStatus::Read);
    });

    test('opening does not change a replied or archived inquiry', function () {
        $inquiry = Inquiry::factory()->status(InquiryStatus::Replied)->create();

        $this->actingAs(User::factory()->create())->get(route('inquiries.show', $inquiry))->assertOk();

        expect($inquiry->fresh()->status)->toBe(InquiryStatus::Replied);
    });

    test('status, assignee and notes are saved, and the reply time is remembered once', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $inquiry = Inquiry::factory()->create();

        $this->actingAs($user)->patch(route('inquiries.update', $inquiry), ['status' => 'replied', 'assigned_to' => $other->id, 'admin_notes' => 'Called back.'])
            ->assertRedirect();

        $inquiry->refresh();
        $firstReply = $inquiry->replied_at;

        expect($inquiry->status)->toBe(InquiryStatus::Replied)->and($inquiry->assigned_to)->toBe($other->id)->and($inquiry->admin_notes)->toBe('Called back.')->and($firstReply)->not->toBeNull();

        $this->travel(1)->hours();
        $this->actingAs($user)->patch(route('inquiries.update', $inquiry), ['status' => 'replied', 'assigned_to' => 'none'])->assertRedirect();

        expect($inquiry->fresh()->replied_at->equalTo($firstReply))->toBeTrue()->and($inquiry->fresh()->assigned_to)->toBeNull();
    });

    test('rejects an unknown status or assignee', function () {
        $inquiry = Inquiry::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('inquiries.update', $inquiry), ['status' => 'bogus'])->assertSessionHasErrors('status');
        $this->actingAs($user)->patch(route('inquiries.update', $inquiry), ['status' => 'read', 'assigned_to' => 9999])->assertSessionHasErrors('assigned_to');
    });

    test('can be deleted', function () {
        $inquiry = Inquiry::factory()->create();

        $this->actingAs(User::factory()->create())->delete(route('inquiries.destroy', $inquiry))->assertRedirect(route('inquiries.index'));

        expect(Inquiry::count())->toBe(0);
    });
});

describe('CSV export', function () {
    test('exports the filtered rows with a byte-order mark', function () {
        Inquiry::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'type_name' => 'Media']);
        Inquiry::factory()->status(InquiryStatus::Spam)->create(['first_name' => 'Spammer']);

        $response = $this->actingAs(User::factory()->create())->get(route('inquiries.export'))->assertOk();
        $csv = $response->streamedContent();

        expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('Reference,Received,Status')->toContain('Ada')->not->toContain('Spammer');
    });

    test('neutralises spreadsheet formulas typed by visitors', function (string $value, string $expected) {
        expect(CsvExport::safeCell($value))->toBe($expected);
    })->with([
        ['=HYPERLINK("http://evil")', "'=HYPERLINK(\"http://evil\")"],
        ['+1 555', "'+1 555"],
        ['-2+3', "'-2+3"],
        ['@SUM(A1)', "'@SUM(A1)"],
        ['Plain text', 'Plain text'],
        ['', ''],
    ]);
});

describe('inquiry topics (settings)', function () {
    test('only administrators can reach them', function () {
        $editor = User::factory()->create();

        $this->actingAs($editor)->get(route('inquiry-types.index'))->assertForbidden();
        $this->actingAs($editor)->post(route('inquiry-types.store'), ['name' => 'X', 'email' => 'x@y.ca'])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('inquiry-types.index'))->assertOk();
    });

    test('an administrator adds, edits and deletes topics', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('inquiry-types.store'), ['name' => 'Media & Press', 'email' => 'Press@CancoCares.ca', 'is_active' => '1'])->assertRedirect();

        $type = InquiryType::firstOrFail();
        expect($type->slug)->toBe('media-press')->and($type->email)->toBe('press@cancocares.ca')->and($type->is_active)->toBeTrue();

        $this->actingAs($admin)->patch(route('inquiry-types.update', $type), ['name' => 'Press', 'email' => 'news@cancocares.ca'])->assertRedirect();

        $type->refresh();
        // The slug does not change with the name, and a missing checkbox hides the topic.
        expect($type->slug)->toBe('media-press')->and($type->name)->toBe('Press')->and($type->is_active)->toBeFalse();

        $inquiry = Inquiry::factory()->create(['inquiry_type_id' => $type->id, 'type_name' => 'Press']);
        $this->actingAs($admin)->delete(route('inquiry-types.destroy', $type))->assertRedirect();

        expect(InquiryType::count())->toBe(0)->and($inquiry->fresh()->type_name)->toBe('Press')->and($inquiry->fresh()->inquiry_type_id)->toBeNull();
    });

    test('validation', function () {
        $admin = User::factory()->admin()->create();
        InquiryType::factory()->create(['name' => 'Media']);

        $this->actingAs($admin)->post(route('inquiry-types.store'), ['name' => '', 'email' => 'nope'])->assertSessionHasErrors(['name', 'email']);
        $this->actingAs($admin)->post(route('inquiry-types.store'), ['name' => 'Media', 'email' => 'a@b.ca'])->assertSessionHasErrors('name');
    });

    test('new topics are added to the end of the list', function () {
        $admin = User::factory()->admin()->create();
        InquiryType::factory()->create(['sort_order' => 5]);

        $this->actingAs($admin)->post(route('inquiry-types.store'), ['name' => 'Last', 'email' => 'l@y.ca', 'is_active' => '1']);

        expect(InquiryType::where('name', 'Last')->firstOrFail()->sort_order)->toBe(6);
    });

    test('the fallback addresses are saved, de-duplicated and validated', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('inquiry-types.fallback'), ['emails' => ['A@x.ca', 'a@x.ca', 'b@x.ca']])->assertRedirect();
        expect(app(Settings::class)->strings('inquiries.fallback_emails'))->toBe(['a@x.ca', 'b@x.ca']);

        $this->actingAs($admin)->put(route('inquiry-types.fallback'), ['emails' => ['not-an-email']])->assertSessionHasErrors('emails.0');
    });
});

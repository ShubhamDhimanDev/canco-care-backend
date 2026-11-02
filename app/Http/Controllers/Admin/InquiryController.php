<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The staff inbox for contact-form messages: read, triage, assign, annotate and export. Both roles
 * use it. Donors' details never appear here.
 */
class InquiryController extends Controller
{
    /** The tabs: "inbox" is everything still open (new, read or replied). */
    private const VIEWS = ['inbox', 'new', 'replied', 'archived', 'spam', 'all'];

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Inquiry::class);

        [$view, $type, $search] = $this->filters($request);

        $inquiries = $this->filtered(Inquiry::query(), $view, $type, $search)
            // Unread first, then newest.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [InquiryStatus::New->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Inquiry $inquiry): array => $this->row($inquiry));

        $count = fn (string $name): int => $this->filtered(Inquiry::query(), $name, $type, $search)->count();

        return Inertia::render('inquiries/index', [
            'inquiries' => $inquiries,
            'filters' => ['view' => $view, 'type' => $type, 'search' => $search],
            'counts' => array_combine(self::VIEWS, array_map($count, self::VIEWS)),
            'types' => InquiryType::query()->orderBy('sort_order')->orderBy('name')->get(['slug', 'name']),
        ]);
    }

    public function show(Request $request, Inquiry $inquiry): Response
    {
        Gate::authorize('view', $inquiry);

        // Opening a new inquiry is what makes it "read".
        if ($inquiry->status === InquiryStatus::New) {
            $inquiry->update(['status' => InquiryStatus::Read]);
        }

        $inquiry->load('assignee:id,name', 'type:id,name,email');

        return Inertia::render('inquiries/show', [
            'inquiry' => [
                'id' => $inquiry->id,
                'reference' => $inquiry->reference,
                'name' => $inquiry->fullName(),
                'first_name' => $inquiry->first_name,
                'email' => $inquiry->email,
                'phone' => $inquiry->displayPhone(),
                'phone_raw' => $inquiry->phone,
                'type' => $inquiry->type_name,
                'routed_to' => $inquiry->notified_to,
                'message' => $inquiry->message,
                'status' => $inquiry->status->value,
                'assigned_to' => $inquiry->assigned_to,
                'admin_notes' => $inquiry->admin_notes ?? '',
                'received_at' => $inquiry->created_at?->toIso8601String(),
                'replied_at' => $inquiry->replied_at?->toIso8601String(),
                'source_url' => $inquiry->source_url,
            ],
            'statuses' => array_map(fn (InquiryStatus $status): array => ['value' => $status->value, 'label' => $status->label()], InquiryStatus::cases()),
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            'back' => $request->query('back'),
        ]);
    }

    public function update(Request $request, Inquiry $inquiry): RedirectResponse
    {
        Gate::authorize('update', $inquiry);

        if ($request->input('assigned_to') === 'none') {
            $request->merge(['assigned_to' => null]);
        }

        $data = $request->validate([
            'status' => ['required', Rule::enum(InquiryStatus::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $status = InquiryStatus::from($data['status']);

        $inquiry->fill([
            'status' => $status,
            'assigned_to' => $data['assigned_to'] ?? null,
            'admin_notes' => filled($data['admin_notes'] ?? null) ? $data['admin_notes'] : null,
            // The first time it is marked replied, remember when.
            'replied_at' => $inquiry->replied_at ?? ($status === InquiryStatus::Replied ? now() : null),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquiry updated.')]);

        return back();
    }

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        Gate::authorize('delete', $inquiry);

        $inquiry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inquiry deleted.')]);

        return to_route('inquiries.index');
    }

    /**
     * A CSV of whatever the current filters show, for spreadsheets.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Inquiry::class);

        [$view, $type, $search] = $this->filters($request);
        $query = $this->filtered(Inquiry::query(), $view, $type, $search)->orderByDesc('created_at');

        return CsvExport::download(
            'inquiries-'.now()->format('Y-m-d').'.csv',
            ['Reference', 'Received', 'Status', 'Type', 'First name', 'Last name', 'Email', 'Phone', 'Message', 'Sent to', 'Replied'],
            $query,
            fn (Inquiry $inquiry): array => [
                $inquiry->reference,
                $inquiry->created_at?->toDateTimeString(),
                $inquiry->status->label(),
                $inquiry->type_name,
                $inquiry->first_name,
                $inquiry->last_name,
                $inquiry->email,
                $inquiry->displayPhone(),
                $inquiry->message,
                $inquiry->notified_to,
                $inquiry->replied_at?->toDateTimeString(),
            ],
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function filters(Request $request): array
    {
        $view = in_array($request->query('view'), self::VIEWS, true) ? (string) $request->query('view') : 'inbox';

        return [$view, trim((string) $request->query('type', '')), trim((string) $request->query('search', ''))];
    }

    /**
     * @param  Builder<Inquiry>  $query
     * @return Builder<Inquiry>
     */
    private function filtered(Builder $query, string $view, string $type, string $search): Builder
    {
        return $query
            ->when($view === 'inbox', fn (Builder $q) => $q->whereIn('status', [InquiryStatus::New->value, InquiryStatus::Read->value, InquiryStatus::Replied->value]))
            ->when(in_array($view, ['new', 'replied', 'archived', 'spam'], true), fn (Builder $q) => $q->where('status', $view))
            ->when($type !== '', fn (Builder $q) => $q->whereHas('type', fn (Builder $t) => $t->where('slug', $type)))
            ->when($search !== '', fn (Builder $q) => $q->where(
                fn (Builder $s) => $s->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
            ));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Inquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'reference' => $inquiry->reference,
            'name' => $inquiry->fullName(),
            'email' => $inquiry->email,
            'type' => $inquiry->type_name,
            'status' => $inquiry->status->value,
            'snippet' => str($inquiry->message)->squish()->limit(110)->toString(),
            'received_at' => $inquiry->created_at?->toIso8601String(),
        ];
    }
}

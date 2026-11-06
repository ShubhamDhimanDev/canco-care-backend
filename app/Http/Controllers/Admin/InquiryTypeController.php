<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RevalidateFrontend;
use App\Models\InquiryType;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings › Inquiries: the topics offered in the contact form's dropdown, the email address each
 * topic is sent to, and the fallback addresses used when a topic has none. Administrators only
 * (the routes sit behind the `admin` gate).
 */
class InquiryTypeController extends Controller
{
    public function index(Settings $settings): Response
    {
        return Inertia::render('inquiry-types/index', [
            'types' => InquiryType::query()->withCount('inquiries')->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (InquiryType $type): array => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'email' => $type->email,
                    'is_active' => $type->is_active,
                    'sort_order' => $type->sort_order,
                    'inquiries_count' => (int) $type->getAttribute('inquiries_count'),
                ]),
            'fallbackEmails' => $settings->strings('inquiries.fallback_emails'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        InquiryType::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'sort_order' => $data['sort_order'] ?? ((int) InquiryType::max('sort_order') + 1),
        ]);

        return $this->done(__('Inquiry type added.'));
    }

    public function update(Request $request, InquiryType $inquiryType): RedirectResponse
    {
        $data = $this->validated($request);

        // The slug stays as it was: it is what the website sends, and renaming a topic must not break forms in flight.
        $inquiryType->update([...$data, 'sort_order' => $data['sort_order'] ?? $inquiryType->sort_order]);

        return $this->done(__('Inquiry type saved.'));
    }

    public function destroy(InquiryType $inquiryType): RedirectResponse
    {
        // Past inquiries keep the name they were sent under (inquiries.type_name).
        $inquiryType->delete();

        return $this->done(__('Inquiry type deleted. Past inquiries keep their topic name.'));
    }

    public function updateFallback(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'emails' => ['nullable', 'array', 'max:10'],
            'emails.*' => ['required', 'email:rfc', 'max:190'],
        ]);

        $settings->set('inquiries.fallback_emails', array_values(array_unique(array_map('strtolower', $data['emails'] ?? []))));

        return $this->done(__('Fallback addresses saved.'));
    }

    /**
     * @return array{name: string, email: string, is_active: bool, sort_order?: int|null}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('inquiry_types', 'name')->ignore($request->route('inquiry_type'))],
            'email' => ['required', 'email:rfc', 'max:190'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ], [
            'name.required' => 'Give the topic a name visitors will recognise.',
            'name.unique' => 'There is already a topic with that name.',
            'email.required' => 'Enter the email address these inquiries should go to.',
        ]);

        return [
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => $data['sort_order'] ?? null,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'topic';
        $slug = $base;

        for ($i = 2; InquiryType::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function done(string $message): RedirectResponse
    {
        // The website caches the topics for its dropdown under the "settings" tag.
        RevalidateFrontend::fire(['settings']);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('inquiry-types.index');
    }
}

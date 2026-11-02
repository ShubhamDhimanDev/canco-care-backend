<?php

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Http\Requests\Api\V1\StoreInquiryRequest;
use App\Mail\InquiryAutoReply;
use App\Mail\InquiryReceived;
use App\Models\Inquiry;
use App\Models\InquiryType;
use App\Support\ClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Stores a public inquiry and tells people about it: the staff address that belongs to the chosen
 * type (or the fallback list), and an auto-reply to the sender. Mail is queued; a mail problem
 * never fails the request, because the inquiry is already safely stored.
 */
class InquiryService
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array<string, mixed>  $data  validated StoreInquiryRequest input
     */
    public function receive(array $data, Request $request): Inquiry
    {
        $type = filled($data['inquiry_type'] ?? null)
            ? InquiryType::where('slug', $data['inquiry_type'])->first()
            : null;

        // A filled honeypot is a bot: keep the row (to see how much there is), tell nobody.
        $spam = filled($data['website'] ?? null);

        $inquiry = Inquiry::create([
            'reference' => (string) Str::ulid(),
            'inquiry_type_id' => $type?->id,
            'type_name' => $type?->name,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => (string) StoreInquiryRequest::normalisePhone((string) $data['phone']),
            'message' => $data['message'],
            'consent_at' => now(),
            'source_url' => $data['source_url'] ?? null,
            'ip_hash' => ClientIp::hash(ClientIp::for($request)),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'status' => $spam ? InquiryStatus::Spam : InquiryStatus::New,
        ]);

        if (! $spam) {
            $this->notify($inquiry, $type);
        }

        return $inquiry;
    }

    /**
     * The staff addresses for an inquiry: its type's email, else the fallback list.
     *
     * @return list<string>
     */
    public function recipients(?InquiryType $type): array
    {
        if ($type !== null && filled($type->email)) {
            return [$type->email];
        }

        return $this->settings->strings('inquiries.fallback_emails');
    }

    private function notify(Inquiry $inquiry, ?InquiryType $type): void
    {
        $recipients = $this->recipients($type);

        if ($recipients === []) {
            Log::warning('An inquiry was stored but no staff address is configured for it.', ['reference' => $inquiry->reference]);
        } else {
            $inquiry->update(['notified_to' => Str::limit(implode(', ', $recipients), 500, '')]);
            $this->queue(fn () => Mail::to($recipients)->queue(new InquiryReceived($inquiry)), $inquiry);
        }

        $this->queue(fn () => Mail::to($inquiry->email, $inquiry->fullName())->queue(new InquiryAutoReply($inquiry)), $inquiry);
    }

    private function queue(\Closure $send, Inquiry $inquiry): void
    {
        try {
            $send();
        } catch (\Throwable $exception) {
            Log::error('Could not queue an inquiry email.', ['reference' => $inquiry->reference, 'error' => $exception->getMessage()]);
        }
    }
}

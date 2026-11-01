<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DonationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDonationRequest;
use App\Models\Donation;
use App\Models\Event;
use App\Services\Donations\DonationGateways;
use App\Services\Donations\DonationService;
use App\Services\Donations\GatewayException;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    /**
     * What the donation form needs: whether giving is open, the amounts, and the events a donor can
     * support (those an administrator switched on in the event's "Show in the donation form").
     */
    public function options(Settings $settings): JsonResponse
    {
        $events = Event::visible()
            ->where('status', 'published')
            ->where('show_in_donation_form', true)
            ->orderByDesc('starts_at')
            ->get(['slug', 'title']);

        return response()->json(['data' => [
            'enabled' => $this->open($settings),
            'currency' => 'CAD',
            'presets_cents' => array_values(array_map('intval', (array) $settings->get('donations.presets_cents'))),
            'min_cents' => max(100, $settings->int('donations.min_cents')),
            'max_cents' => StoreDonationRequest::MAX_CENTS,
            'test_mode' => DonationGateways::available() && DonationGateways::make()->isTest(),
            'events' => $events->map(fn (Event $event): array => ['slug' => $event->slug, 'title' => $event->title])->values(),
        ]]);
    }

    /**
     * Creates the pending donation and the session the browser uses to pay. The same
     * `idempotency_key` returns the same donation (200) instead of making another.
     */
    public function store(StoreDonationRequest $request, DonationService $donations, Settings $settings): JsonResponse
    {
        if (! $this->open($settings)) {
            return response()->json(['message' => 'Online donations are not open yet.'], 403);
        }

        [$donation, $created] = $donations->create($request->validated(), $request);

        return response()->json(['data' => [
            ...$this->summary($donation),
            'gateway' => $donation->status === DonationStatus::Pending ? $donations->session($donation) : null,
        ]], $created ? 201 : 200);
    }

    /**
     * Charges a pending donation. Anything the browser sends other than the single-use token is
     * ignored: the amount is the stored one. A donation that is no longer pending is answered with
     * its current state and is never charged again (409).
     */
    public function confirm(Request $request, string $reference, DonationService $donations): JsonResponse
    {
        $data = $request->validate(['payment_token' => ['required', 'string', 'max:2048']]);

        $donation = Donation::where('reference', $reference)->firstOrFail();

        if ($donation->status !== DonationStatus::Pending) {
            return response()->json(['data' => $this->summary($donation)], 409);
        }

        try {
            $donation = $donations->confirm($donation, $data['payment_token']);
        } catch (GatewayException) {
            return response()->json([
                'message' => "We couldn't confirm your donation. Please don't try again yet — check your email or contact us.",
            ], 502);
        }

        // A declined card is an ordinary answer (200 with status "failed"), so the form can explain it.
        return response()->json(['data' => $this->summary($donation)]);
    }

    /**
     * The thank-you page / status poll. Public by the donation's unguessable reference, and minimal:
     * no email, address or gateway details.
     */
    public function show(string $reference): JsonResponse
    {
        $donation = Donation::where('reference', $reference)->firstOrFail();

        return response()->json(['data' => [
            ...$this->summary($donation),
            'paid_at' => $donation->paid_at?->toIso8601String(),
            'donor_first_name' => $donation->donor_first_name,
        ]]);
    }

    private function open(Settings $settings): bool
    {
        return $settings->bool('donations.enabled') && DonationGateways::available();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Donation $donation): array
    {
        return [
            'reference' => $donation->reference,
            'status' => $donation->status->value,
            'amount_cents' => $donation->amount_cents,
            'currency' => $donation->currency,
            'receipt_number' => $donation->receipt_number,
            'failure_reason' => $donation->failure_reason,
        ];
    }
}

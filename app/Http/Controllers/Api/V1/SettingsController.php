<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Donations\DonationGateways;
use App\Services\Settings;
use App\Support\ImpactFigure;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * Settings the website shows (docs/plan/03-api-spec.md "PublicSettings"). Nothing private: no
     * recipient addresses, receipt details, gateway or mode.
     */
    public function show(Settings $settings): JsonResponse
    {
        return response()->json(['data' => [
            'impact' => [
                'amount_text' => (new ImpactFigure($settings))->amountText(),
                'caption' => $settings->string('impact.caption'),
                'blurb' => $settings->string('impact.blurb'),
            ],
            'social' => [
                'facebook' => $settings->string('social.facebook'),
                'instagram' => $settings->string('social.instagram'),
                'linkedin' => $settings->string('social.linkedin'),
            ],
            'contact' => ['inquiry_email' => $settings->string('contact.inquiry_email')],
            'donations' => [
                'enabled' => $settings->bool('donations.enabled') && DonationGateways::available(),
                'currency' => 'CAD',
            ],
        ]]);
    }
}

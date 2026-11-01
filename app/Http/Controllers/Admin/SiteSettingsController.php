<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteSettingsRequest;
use App\Services\Donations\DonationGateways;
use App\Services\Settings;
use App\Support\ImpactFigure;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings › Site: the impact figure, social links, contact address, donation options and the
 * details printed on tax receipts. Administrators only (the routes sit behind the `admin` gate).
 */
class SiteSettingsController extends Controller
{
    public function edit(Settings $settings): Response
    {
        $impact = new ImpactFigure($settings);
        $dollars = fn (int $cents): string => rtrim(rtrim(number_format($cents / 100, 2, '.', ''), '0'), '.');

        return Inertia::render('site-settings/edit', [
            'values' => [
                'impact' => [
                    'mode' => $impact->mode(),
                    'amount_text' => $settings->string('impact.amount_text'),
                    'caption' => $settings->string('impact.caption'),
                    'blurb' => $settings->string('impact.blurb'),
                    'starting_amount' => $dollars($settings->int('impact.actual_offset_cents')),
                ],
                'social' => [
                    'facebook' => $settings->string('social.facebook'),
                    'instagram' => $settings->string('social.instagram'),
                    'linkedin' => $settings->string('social.linkedin'),
                ],
                'contact' => ['inquiry_email' => $settings->string('contact.inquiry_email')],
                'donations' => [
                    'enabled' => $settings->bool('donations.enabled'),
                    'presets' => array_map(fn (mixed $cents): int => intdiv((int) $cents, 100), (array) $settings->get('donations.presets_cents')),
                    'min' => $dollars($settings->int('donations.min_cents')),
                ],
                'receipts' => [
                    'charity_name' => $settings->string('receipts.charity_name'),
                    'address' => $settings->string('receipts.address'),
                    'registration_number' => $settings->string('receipts.registration_number'),
                    'signatory_name' => $settings->string('receipts.signatory_name'),
                    'signatory_label' => $settings->string('receipts.signatory_label'),
                    'reply_to' => $settings->string('receipts.reply_to'),
                ],
            ],
            'impactPreview' => [
                'actual_text' => ImpactFigure::format($settings->int('impact.actual_offset_cents') + $impact->raisedCents()),
                'raised_text' => ImpactFigure::format($impact->raisedCents()),
                'showing' => $impact->amountText(),
            ],
            'gateway' => [
                'problem' => DonationGateways::problem(),
                'available' => DonationGateways::available(),
            ],
        ]);
    }

    public function update(SiteSettingsRequest $request, Settings $settings): RedirectResponse
    {
        $data = $request->validated();
        $cents = fn (mixed $dollars): int => (int) round(((float) $dollars) * 100);
        $text = fn (mixed $value): ?string => filled($value) ? trim((string) $value) : null;

        $presets = array_map('intval', $data['donations']['presets']);
        sort($presets);

        $settings->setMany([
            'impact.mode' => $data['impact']['mode'],
            // Keep the typed figure when switching to "actual", so switching back brings it back.
            'impact.amount_text' => $text($data['impact']['amount_text'] ?? null) ?? $settings->string('impact.amount_text'),
            'impact.caption' => $text($data['impact']['caption'] ?? null) ?? '',
            'impact.blurb' => $text($data['impact']['blurb'] ?? null) ?? '',
            'impact.actual_offset_cents' => $cents($data['impact']['starting_amount'] ?? 0),

            'social.facebook' => $text($data['social']['facebook'] ?? null) ?? '',
            'social.instagram' => $text($data['social']['instagram'] ?? null) ?? '',
            'social.linkedin' => $text($data['social']['linkedin'] ?? null) ?? '',
            'contact.inquiry_email' => $text($data['contact']['inquiry_email'] ?? null) ?? '',

            'donations.enabled' => (bool) ($data['donations']['enabled'] ?? false),
            'donations.presets_cents' => array_map(fn (int $dollars): int => $dollars * 100, $presets),
            'donations.min_cents' => $cents($data['donations']['min']),

            'receipts.charity_name' => trim($data['receipts']['charity_name']),
            'receipts.address' => trim($data['receipts']['address']),
            'receipts.registration_number' => trim($data['receipts']['registration_number']),
            'receipts.signatory_name' => trim($data['receipts']['signatory_name']),
            'receipts.signatory_label' => trim($data['receipts']['signatory_label']),
            'receipts.reply_to' => $text($data['receipts']['reply_to'] ?? null) ?? '',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('site-settings.edit');
    }
}

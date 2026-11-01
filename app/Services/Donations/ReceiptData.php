<?php

namespace App\Services\Donations;

use App\Models\Donation;
use App\Services\Settings;

/**
 * Everything printed on a tax receipt, in one place, so the email and the admin's preview show the
 * same thing. The charity's details come from Settings (defaults are those on the sample receipt).
 */
final class ReceiptData
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Donation $donation, Settings $settings): array
    {
        $paid = ($donation->paid_at ?? now())->copy()->setTimezone(Donation::RECEIPT_TIMEZONE);
        $issued = ($donation->receipt_issued_at ?? now())->copy()->setTimezone(Donation::RECEIPT_TIMEZONE);
        $front = rtrim((string) config('services.frontend.url'), '/');

        return [
            'charity_name' => $settings->string('receipts.charity_name'),
            'charity_address' => $settings->string('receipts.address'),
            'registration_number' => $settings->string('receipts.registration_number'),
            'signatory_name' => $settings->string('receipts.signatory_name'),
            'signatory_label' => $settings->string('receipts.signatory_label'),
            'logo_url' => $front === '' ? null : $front.'/images/careLogo.png',

            'title' => $donation->event_title ?: 'Donation',
            'receipt_number' => $donation->receipt_number,
            'date_of_donation' => $paid->format('l, F j, Y'),
            'date_issued' => $issued->format('F j, Y'),
            'place_issued' => 'Kelowna, BC',

            'donor_name' => $donation->donorName(),
            'street' => trim($donation->address_line1.' '.($donation->address_line2 ?? '')),
            'city' => $donation->city,
            'province' => $donation->province,
            'postal_code' => $donation->postal_code,

            'amount' => Donation::plainAmount($donation->amount_cents),
            'currency' => $donation->currency,
            // A gift with no goods or services in return: the whole amount is eligible and there is no advantage.
            'eligible_amount' => Donation::plainAmount($donation->amount_cents),
            'advantage' => Donation::plainAmount(0),

            'is_test' => $donation->is_test,
        ];
    }
}

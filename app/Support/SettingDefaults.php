<?php

namespace App\Support;

/**
 * Every setting the site knows about, with the value used until an administrator changes it.
 * The defaults reproduce cancocares.ca exactly, so an empty `settings` table renders today's site.
 */
final class SettingDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            // Impact counter (docs/plan/13 §7). `mode` is "custom" (show the text below) or "actual"
            // (starting amount + the total of succeeded donations).
            'impact.mode' => 'custom',
            'impact.amount_text' => '$80,000',
            'impact.caption' => 'AND COUNTING!',
            'impact.blurb' => "Canco Cares is already impacting families and communities, with plans for rapid expansion. We're proud partners of the Make-A-Wish Foundation, having donated over \$80,000. Our mission is to give back to communities and families, creating a ripple of positive change in our society.",
            'impact.actual_offset_cents' => 8_000_000,

            'social.facebook' => 'https://www.facebook.com/profile.php?id=61566542968163',
            'social.instagram' => 'https://www.instagram.com/cancocares?igsh=YWVwdHJrdnpoeWhq',
            'social.linkedin' => 'https://www.linkedin.com/company/104790620',

            'contact.inquiry_email' => 'inquiry@cancocares.ca',

            // Used when an inquiry type has no address of its own.
            'inquiries.fallback_emails' => [],

            'donations.enabled' => false,
            'donations.presets_cents' => [2500, 5000, 10000, 25000],
            'donations.min_cents' => 500,

            // Printed on tax receipts (docs/plan/13 §7). Defaults come from the owner's sample receipt.
            'receipts.charity_name' => 'Canco Cares Foundation',
            'receipts.address' => '125 Hwy 33 E, Kelowna, BC V1X 2A1',
            'receipts.registration_number' => '79021 4621 RR0001',
            'receipts.signatory_name' => 'Simran Kaur',
            'receipts.signatory_label' => 'Authorized Signature',
            'receipts.reply_to' => 'inquiry@cancocares.ca',

            // The home page document is merged over HomeContent::defaults().
            'home.content' => [],
        ];
    }

    public static function groupOf(string $key): string
    {
        return explode('.', $key, 2)[0];
    }
}

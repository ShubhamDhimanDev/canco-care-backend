<!doctype html>
<html lang="en-CA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donation receipt {{ $receipt['receipt_number'] }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f6fa;font-family:Roboto,Arial,Helvetica,sans-serif;color:#4f597f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;margin:0 auto;background:#ffffff;">
    <tr>
        <td style="padding:28px 40px 40px;">

            @if ($receipt['is_test'])
                <p style="margin:0 0 20px;padding:10px 14px;background:#fff4e5;border:2px solid #f15024;color:#8a2a0c;font-size:14px;font-weight:bold;text-align:center;">
                    TEST RECEIPT &mdash; not valid for tax purposes. No real payment was taken.
                </p>
            @endif

            <p style="margin:0 0 24px;text-align:center;font-size:14px;font-weight:bold;color:#4f597f;">
                Official receipt for income tax purposes
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    @if ($receipt['logo_url'])
                        <td width="150" valign="top" style="padding-right:20px;">
                            <img src="{{ $receipt['logo_url'] }}" alt="Canco Cares" width="130" style="display:block;border:0;height:auto;">
                        </td>
                    @endif
                    <td valign="top">
                        <p style="margin:0 0 6px;font-size:28px;line-height:1.2;font-weight:bold;color:#0c3b8e;">{{ $receipt['charity_name'] }}</p>
                        <p style="margin:0 0 2px;font-size:12px;line-height:1.5;color:#212529;">Address: {{ $receipt['charity_address'] }}</p>
                        <p style="margin:0;font-size:12px;line-height:1.5;color:#212529;">Charitable Registration #: {{ $receipt['registration_number'] }}</p>
                    </td>
                </tr>
            </table>

            <p style="margin:28px 0 24px;text-align:center;font-size:24px;line-height:1.3;font-weight:bold;color:#4f597f;">{{ $receipt['title'] }}</p>

            <p style="margin:0 0 24px;text-align:right;font-size:15px;font-weight:bold;line-height:1.5;">
                Receipt #<br>
                <span style="font-size:16px;">{{ $receipt['receipt_number'] }}</span>
            </p>

            <p style="margin:0 0 14px;font-size:14px;line-height:1.5;">
                <strong>Date of donation:</strong> {{ $receipt['date_of_donation'] }}
            </p>
            <p style="margin:0 0 14px;font-size:14px;line-height:1.5;">
                <strong>Donated by:</strong> {{ $receipt['donor_name'] }}
            </p>

            <p style="margin:0 0 18px;font-size:14px;line-height:1.5;">
                <strong>Address</strong><br>
                Street Address: {{ $receipt['street'] }}<br>
                City: {{ $receipt['city'] }}<br>
                Province: {{ $receipt['province'] }}<br>
                Postal Code: {{ $receipt['postal_code'] }}
            </p>

            <p style="margin:0 0 10px;font-size:14px;font-weight:bold;">Donation Received</p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="padding:12px 8px;background:#f4f5fa;font-weight:bold;">Description</td>
                </tr>
                <tr>
                    <td style="padding:14px 8px;font-weight:bold;border-bottom:1px solid #e6e8f0;">
                        Donation $ (Amount: {{ $receipt['amount'] }} {{ $receipt['currency'] }})
                    </td>
                </tr>
            </table>

            <p style="margin:10px 0 0;font-size:12px;line-height:1.6;color:#212529;">
                Eligible amount of gift for tax purposes: ${{ $receipt['eligible_amount'] }} {{ $receipt['currency'] }}
                &middot; Value of advantage: ${{ $receipt['advantage'] }}
            </p>

            <p style="margin:48px 0 0;text-align:center;">
                <span style="display:block;font-family:'Brush Script MT','Segoe Script','Lucida Handwriting',cursive;font-size:22px;font-style:italic;color:#212529;">{{ $receipt['signatory_name'] }}</span>
                <span style="display:block;margin-top:2px;font-size:15px;color:#212529;">{{ $receipt['signatory_label'] }}</span>
            </p>

            <p style="margin:36px 0 0;font-size:11px;line-height:1.6;color:#6b7280;text-align:center;">
                Receipt issued {{ $receipt['date_issued'] }} at {{ $receipt['place_issued'] }}.
                Canada Revenue Agency: www.canada.ca/charities-giving
            </p>
        </td>
    </tr>
</table>
</body>
</html>

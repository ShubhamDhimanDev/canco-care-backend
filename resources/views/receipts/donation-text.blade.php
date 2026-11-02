@if ($receipt['is_test'])
TEST RECEIPT - not valid for tax purposes. No real payment was taken.

@endif
Official receipt for income tax purposes

{{ $receipt['charity_name'] }}
Address: {{ $receipt['charity_address'] }}
Charitable Registration #: {{ $receipt['registration_number'] }}

{{ $receipt['title'] }}

Receipt #: {{ $receipt['receipt_number'] }}
Date of donation: {{ $receipt['date_of_donation'] }}
Donated by: {{ $receipt['donor_name'] }}

Address
Street Address: {{ $receipt['street'] }}
City: {{ $receipt['city'] }}
Province: {{ $receipt['province'] }}
Postal Code: {{ $receipt['postal_code'] }}

Donation Received
Donation $ (Amount: {{ $receipt['amount'] }} {{ $receipt['currency'] }})
Eligible amount of gift for tax purposes: ${{ $receipt['eligible_amount'] }} {{ $receipt['currency'] }}
Value of advantage: ${{ $receipt['advantage'] }}

{{ $receipt['signatory_name'] }}
{{ $receipt['signatory_label'] }}

Receipt issued {{ $receipt['date_issued'] }} at {{ $receipt['place_issued'] }}.
Canada Revenue Agency: www.canada.ca/charities-giving

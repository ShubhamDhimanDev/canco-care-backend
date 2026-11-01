New inquiry from {{ $inquiry->fullName() }}
@if ($inquiry->type_name)
About: {{ $inquiry->type_name }}
@endif
Email: {{ $inquiry->email }}
Phone: {{ $inquiry->displayPhone() }}
Reference: {{ $inquiry->reference }}

{{ $inquiry->message }}

Reply to this email to answer {{ $inquiry->first_name }} directly, or open it in the admin: {{ $adminUrl }}

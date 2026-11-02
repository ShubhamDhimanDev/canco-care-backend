<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;color:#212529;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;">
        <tr>
            <td style="padding:28px 32px;">
                <p style="margin:0 0 4px;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:#6b7aa6;">New inquiry</p>
                <h1 style="margin:0 0 16px;font-size:22px;color:#12285b;">{{ $inquiry->fullName() }}</h1>

                <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.6;margin-bottom:16px;">
                    @if ($inquiry->type_name)
                        <tr><td style="padding-right:16px;color:#6b7280;">About</td><td>{{ $inquiry->type_name }}</td></tr>
                    @endif
                    <tr><td style="padding-right:16px;color:#6b7280;">Email</td><td><a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a></td></tr>
                    <tr><td style="padding-right:16px;color:#6b7280;">Phone</td><td>{{ $inquiry->displayPhone() }}</td></tr>
                    <tr><td style="padding-right:16px;color:#6b7280;">Reference</td><td>{{ $inquiry->reference }}</td></tr>
                </table>

                <div style="white-space:pre-wrap;font-size:15px;line-height:1.6;border-left:4px solid #6b7aa6;padding:4px 0 4px 16px;margin:0 0 24px;">{{ $inquiry->message }}</div>

                <p style="margin:0;font-size:14px;">
                    Reply to this email to answer {{ $inquiry->first_name }} directly, or
                    <a href="{{ $adminUrl }}">open it in the admin</a> to track it.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

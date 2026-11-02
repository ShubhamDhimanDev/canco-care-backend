<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;color:#212529;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;">
        <tr>
            <td style="padding:28px 32px;font-size:15px;line-height:1.6;">
                <h1 style="margin:0 0 16px;font-size:22px;color:#12285b;">Thank you, {{ $inquiry->first_name }}.</h1>
                <p style="margin:0 0 12px;">We have received your message and will reply as soon as we can.</p>
                <p style="margin:0 0 12px;">Your reference is <strong>{{ $inquiry->reference }}</strong> — please quote it if you write to us again.</p>
                <p style="margin:0 0 0;color:#12285b;"><strong>Together we can create a ripple of positive change in our society.</strong></p>
                <p style="margin:16px 0 0;font-size:13px;color:#6b7280;">Canco Cares</p>
            </td>
        </tr>
    </table>
</body>
</html>

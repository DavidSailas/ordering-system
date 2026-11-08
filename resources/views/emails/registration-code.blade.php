<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:24px;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;color:#1f2430;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:14px;border:1px solid #e7e9ef;">
                <tr><td style="padding:28px 32px 8px;font-size:18px;font-weight:700;color:#d9441f;">{{ config('restaurant.name') }}</td></tr>
                <tr><td style="padding:8px 32px 0;font-size:15px;line-height:1.6;">
                    Hi {{ $name }},<br>
                    Use this code to finish creating your account:
                </td></tr>
                <tr><td align="center" style="padding:22px 32px;">
                    <div style="display:inline-block;padding:14px 28px;border-radius:12px;background:#fff4ef;border:1px dashed #f0a58c;font-size:34px;letter-spacing:10px;font-weight:700;color:#d9441f;">{{ $code }}</div>
                </td></tr>
                <tr><td style="padding:0 32px 28px;font-size:13px;line-height:1.6;color:#6b7385;">
                    The code expires in {{ config('restaurant.registration_code.ttl_minutes', 10) }} minutes. If you did not try to register, you can ignore this email.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>First Login Verification</title></head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family:Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr><td align="center">
            <table width="480" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.06);">
                <tr><td style="background:#1a1a2e; padding:24px; text-align:center;">
                    <h1 style="color:#fff; font-size:18px; margin:0;">E.V. CATAPANG</h1>
                    <p style="color:#c9c9d4; font-size:12px; margin:4px 0 0;">Design &amp; Construction</p>
                </td></tr>
                <tr><td style="padding:32px;">
                    <p style="font-size:15px; color:#333;">Hi{{ $name ? ' ' . $name : '' }},</p>
                    <p style="font-size:15px; color:#333;">Enter this code to verify your email and complete your first login:</p>
                    <div style="text-align:center; margin:28px 0;">
                        <span style="display:inline-block; font-size:32px; letter-spacing:8px; font-weight:bold; color:#1a1a2e; background:#f4f4f5; padding:14px 24px; border-radius:6px;">{{ $otp }}</span>
                    </div>
                    <p style="font-size:14px; color:#666;">This code expires in <strong>10 minutes</strong>. If you did not try to sign in, you can ignore this email.</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>

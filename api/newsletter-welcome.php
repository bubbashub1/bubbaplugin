<?php
declare(strict_types=1);

/**
 * Send the Bubba Hub newsletter welcome email.
 * Mail delivery failure must never block a successful newsletter signup.
 */
function bh_send_newsletter_welcome(string $email): bool {
    require_once __DIR__.'/mailer.php';

    $safeEmail = htmlspecialchars(strtolower(trim($email)), ENT_QUOTES, 'UTF-8');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

    $html = '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Welcome to Bubba Hub</title>
</head>
<body style="margin:0;padding:0;background:#d8e6db;font-family:Arial,Helvetica,sans-serif;color:#244c38;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#d8e6db;margin:0;padding:32px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:20px;overflow:hidden;">
<tr><td style="padding:28px 32px 18px;text-align:center;">
<img src="https://bubbahub.co.uk/wp-content/uploads/logo/logoheader.png" alt="Bubba Hub" style="max-width:220px;height:auto;border:0;">
</td></tr>
<tr><td style="padding:8px 36px 36px;">
<h1 style="margin:0 0 18px;color:#416651;font-size:30px;line-height:1.2;">Welcome to Bubba Hub! 💚</h1>
<p style="font-size:16px;line-height:1.7;margin:0 0 18px;">Thanks for joining our newsletter.</p>
<p style="font-size:16px;line-height:1.7;margin:0 0 24px;">Bubba Hub was created to make finding things to do with your little ones a whole lot easier — from baby groups and toddler classes to family activities, clubs and local services across Devon &amp; Cornwall.</p>
<h2 style="color:#416651;font-size:21px;margin:0 0 14px;">What you’ll get from us</h2>
<p style="font-size:16px;line-height:1.7;margin:0 0 8px;">👶 <strong>Family activities</strong> — discover what’s happening near you</p>
<p style="font-size:16px;line-height:1.7;margin:0 0 8px;">📅 <strong>What’s coming up</strong> — useful local events and activities</p>
<p style="font-size:16px;line-height:1.7;margin:0 0 8px;">💚 <strong>Bubba Hub updates</strong> — new features, improvements and things we’re working on</p>
<p style="font-size:16px;line-height:1.7;margin:0 0 24px;">📍 <strong>Local family finds</strong> — helping you discover more of what’s available in your area</p>
<p style="font-size:16px;line-height:1.7;margin:0 0 24px;">We’re building Bubba Hub to be your <strong>family hub for finding, planning and booking family activities</strong> — and we’re really pleased to have you with us.</p>
<p style="text-align:center;margin:30px 0;">
<a href="https://bubbahub.co.uk/" style="display:inline-block;background:#416651;color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;padding:14px 24px;border-radius:10px;">Explore Bubba Hub →</a>
</p>
<p style="font-size:16px;line-height:1.7;margin:0 0 18px;">Thanks for being part of the Bubba Hub family.</p>
<p style="font-size:16px;line-height:1.7;margin:0;">Gina<br><strong>Founder, Bubba Hub</strong></p>
<p style="font-size:13px;line-height:1.6;color:#617261;margin:28px 0 0;padding-top:18px;border-top:1px solid #d8e6db;">You’re receiving this because you signed up for the Bubba Hub newsletter. You can unsubscribe from our newsletter at any time.</p>
</td></tr>
<tr><td style="padding:18px 36px;background:#f3f7f3;text-align:center;font-size:12px;color:#617261;">
Bubba Hub · Devon &amp; Cornwall<br>
<a href="https://bubbahub.co.uk/" style="color:#416651;text-decoration:none;">bubbahub.co.uk</a>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';

    $plain = "Welcome to Bubba Hub! 💚

Thanks for joining our newsletter.

Bubba Hub helps families discover baby groups, toddler classes, family activities, clubs and local services across Devon & Cornwall.

What you'll get:
- Family activities and local finds
- What's coming up
- Bubba Hub updates
- Helpful local family information

Explore Bubba Hub: https://bubbahub.co.uk/

Thanks for being part of the Bubba Hub family.

Gina
Founder, Bubba Hub

You're receiving this because you signed up for the Bubba Hub newsletter.";

    return bh_send_smtp_mail(
        $email,
        'Welcome to Bubba Hub 💚',
        $html,
        $plain,
        ''
    );
}

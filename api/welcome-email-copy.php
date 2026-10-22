<?php
declare(strict_types=1);
/** Pure welcome-email copy. Sending is handled by the existing SMTP mailer. */
function bh_welcome_email_copy(string $role, string $firstName = ''): array {
    if (!in_array($role, ['family', 'leader'], true)) {
        throw new InvalidArgumentException('Unsupported account role');
    }
    $leader = $role === 'leader';
    $subject = $leader ? 'Welcome to Bubba Hub - your classes belong here!' : 'Welcome to Bubba Hub - find, book and plan!';
    $name = trim($firstName);
    $greeting = $name === '' ? 'Hi there!' : 'Hi ' . $name . '!';
    $url = $leader ? 'https://www.bubbahub.co.uk/leader' : 'https://www.bubbahub.co.uk/directory';
    $intro = $leader
        ? 'Thanks for joining our community of class leaders. Add your listings, venues and timetables to help families discover your activities.'
        : 'Thanks for joining our community. Discover family activities, save favourites and plan your week with My Hub.';
    $plain = $greeting . "\n\n" . $intro . "\n\nGet started: " . $url
        . "\n\nQuestions? contact@bubbahub.co.uk\n\nThe Bubba Hub Team\nFind. Book. Plan.";
    $html = '<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;line-height:1.6;color:#214934">'
        . '<h1 style="color:#28654c">Welcome to Bubba Hub!</h1>'
        . '<p>' . htmlspecialchars($greeting, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p>' . htmlspecialchars($intro, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<p><a href="' . $url . '">Get started with Bubba Hub</a></p>'
        . '<p>Questions? contact@bubbahub.co.uk</p><p>The Bubba Hub Team<br>Find. Book. Plan.</p></div>';
    return ['subject'=>$subject, 'html'=>$html, 'plain'=>$plain];
}

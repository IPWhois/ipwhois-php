<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Ipwhois\Client;

/*
 * If you make many requests with the same options, set them once on the
 * client. Per-call options always override the defaults.
 */

$client = (new Client('YOUR_API_KEY'))
    ->setLanguage('en')
    ->setFields(['country', 'city', 'flag.emoji', 'connection.isp'])
    ->setSecurity(true)
    ->setTimeout(8);

// Both calls below will use lang=en, the field whitelist, and security=1.
foreach (['8.8.8.8', '1.1.1.1'] as $ip) {
    $info = $client->lookup($ip);
    if (!$info['success']) {
        fprintf(STDERR, "%s: %s\n", $ip, $info['message'] ?? 'error');
        continue;
    }
    printf(
        "%s: %s / %s %s\n",
        $ip,
        $info['country'],
        $info['city'],
        $info['flag']['emoji'] ?? ''
    );
}

// One-off override — this single call uses German instead of English.
$info = $client->lookup('8.8.4.4', ['lang' => 'de']);
if ($info['success']) {
    printf("8.8.4.4 (de): %s / %s\n", $info['country'], $info['city']);
}

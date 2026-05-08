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
$google = $client->lookup('8.8.8.8');
$cf     = $client->lookup('1.1.1.1');

echo "{$google['country']} / {$google['city']} {$google['flag']['emoji']}\n";
echo "{$cf['country']} / {$cf['city']} {$cf['flag']['emoji']}\n";

// One-off override — this single call uses German instead of English.
$deOnly = $client->lookup('8.8.4.4', ['lang' => 'de']);
echo "{$deOnly['country']} / {$deOnly['city']}\n";

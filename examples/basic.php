<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Ipwhois\Client;

/* -----------------------------------------------------------------------
 * 1) Free plan — no API key, ~1 request/second per client IP.
 * -------------------------------------------------------------------- */
$client = new Client();

$info = $client->lookup('8.8.8.8');

// All errors — invalid IP, network failure, bad options, … — come back here
// with success === false. The library never throws.
if (!$info['success']) {
    fprintf(STDERR, "Lookup failed: %s\n", $info['message'] ?? 'unknown');
    exit(1);
}

echo sprintf(
    "%s  %s  (%s, %s)\n",
    $info['ip'],
    $info['flag']['emoji'] ?? '',
    $info['country'] ?? 'unknown',
    $info['city']    ?? 'unknown'
);

/* -----------------------------------------------------------------------
 * 2) Look up the caller's own IP — pass nothing (or null).
 * -------------------------------------------------------------------- */
$me = $client->lookup();
if ($me['success']) {
    echo "My IP: {$me['ip']} — {$me['country']}\n";
}

/* -----------------------------------------------------------------------
 * 3) Paid plan — supply the API key.
 * -------------------------------------------------------------------- */
$paid = new Client('YOUR_API_KEY');

$info = $paid->lookup('1.1.1.1', [
    'lang'     => 'en',                              // localised country/city/…
    'fields'   => ['country', 'city', 'connection.isp', 'flag.emoji'],
    'security' => true,                              // include proxy/vpn/tor flags
    'rate'     => true,                              // include rate-limit info
]);

print_r($info);

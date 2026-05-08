<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Ipwhois\Client;
use Ipwhois\Exception\ApiException;
use Ipwhois\Exception\NetworkException;

/* -----------------------------------------------------------------------
 * 1) Free endpoint — no API key, ~1 request/second per client IP.
 * -------------------------------------------------------------------- */
$client = new Client();

try {
    $info = $client->lookup('8.8.8.8');

    echo sprintf(
        "%s  %s  (%s, %s)\n",
        $info['ip'],
        $info['flag']['emoji'] ?? '',
        $info['country'] ?? 'unknown',
        $info['city']    ?? 'unknown',
    );
} catch (ApiException $e) {
    fprintf(STDERR, "API error %d: %s\n", $e->getStatusCode(), $e->getMessage());
} catch (NetworkException $e) {
    fprintf(STDERR, "Network error: %s\n", $e->getMessage());
}

/* -----------------------------------------------------------------------
 * 2) Look up the caller's own IP — pass nothing (or null).
 * -------------------------------------------------------------------- */
$me = $client->lookup();
echo "My IP: {$me['ip']} — {$me['country']}\n";

/* -----------------------------------------------------------------------
 * 3) Paid endpoint — supply the API key.
 * -------------------------------------------------------------------- */
$paid = new Client('YOUR_API_KEY');

$info = $paid->lookup('1.1.1.1', [
    'lang'     => 'ru',                          // localised country/city/…
    'fields'   => ['country', 'city', 'connection.isp', 'flag.emoji'],
    'security' => true,                          // include proxy/vpn/tor flags
    'rate'     => true,                          // include rate-limit info
]);

print_r($info);

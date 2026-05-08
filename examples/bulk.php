<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Ipwhois\Client;
use Ipwhois\Exception\ApiException;
use Ipwhois\Exception\AuthenticationException;
use Ipwhois\Exception\RateLimitException;

/*
 * Bulk lookup is available on the Business and Unlimited plans only.
 * The library uses the GET / comma-separated form of the bulk endpoint:
 *
 *     https://ipwhois.pro/bulk/IP1,IP2,IP3?key=...
 *
 * Up to 100 IP addresses can be passed in a single call. Each address
 * counts as one credit.
 */

$client = new Client('YOUR_API_KEY');

$ips = [
    '8.8.8.8',
    '1.1.1.1',
    '208.67.222.222',
    '2c0f:fb50:4003::',     // IPv6 is fine too — mix freely
];

try {
    $results = $client->bulkLookup($ips, [
        'lang'     => 'en',
        'security' => true,
    ]);

    foreach ($results as $row) {
        if (($row['success'] ?? false) === false) {
            // Per-IP application errors (e.g. "Invalid IP address",
            // "Reserved range") arrive inside the result array — they do
            // NOT throw, so the rest of the batch is still usable.
            echo sprintf("[skip] %s — %s\n", $row['ip'] ?? '?', $row['message'] ?? 'error');
            continue;
        }

        echo sprintf(
            "%-18s %s %-15s %s\n",
            $row['ip'],
            $row['flag']['emoji']     ?? '  ',
            $row['country_code']      ?? '',
            $row['connection']['isp'] ?? '',
        );
    }
} catch (AuthenticationException $e) {
    fprintf(STDERR, "Bad API key: %s\n", $e->getMessage());
} catch (RateLimitException $e) {
    fprintf(STDERR, "Rate limited; retry after %ds\n", $e->getRetryAfter() ?? 60);
} catch (ApiException $e) {
    fprintf(STDERR, "API error %d: %s\n", $e->getStatusCode(), $e->getMessage());
}

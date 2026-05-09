<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Ipwhois\IPWhois;

/*
 * Bulk lookup is available on the Business and Unlimited plans only.
 * The library uses the GET / comma-separated form of the bulk endpoint:
 *
 *     https://ipwhois.pro/bulk/IP1,IP2,IP3?key=...
 *
 * Up to 100 IP addresses can be passed in a single call. Each address
 * counts as one credit.
 */

$ipwhois = new IPWhois('YOUR_API_KEY');

$ips = [
    '8.8.8.8',
    '1.1.1.1',
    '208.67.222.222',
    '2c0f:fb50:4003::',     // IPv6 is fine too — mix freely
];

$results = $ipwhois->bulkLookup($ips, [
    'lang'     => 'en',
    'security' => true,
]);

// Whole-batch failure (network down, bad API key, rate limit, …) — the
// response is a single error array instead of a list of per-IP results.
if (isset($results['success']) && $results['success'] === false) {
    fprintf(
        STDERR,
        "Bulk request failed: %s (HTTP %d)\n",
        $results['message']     ?? 'unknown',
        $results['http_status'] ?? 0
    );
    exit(1);
}

foreach ($results as $row) {
    if (($row['success'] ?? false) === false) {
        // Per-IP errors (e.g. "Invalid IP address", "Reserved range") are
        // returned inline. The rest of the batch is still usable.
        echo sprintf("[skip] %s — %s\n", $row['ip'] ?? '?', $row['message'] ?? 'error');
        continue;
    }

    echo sprintf(
        "%-18s %s %-15s %s\n",
        $row['ip'],
        $row['flag']['emoji']     ?? '  ',
        $row['country_code']      ?? '',
        $row['connection']['isp'] ?? ''
    );
}

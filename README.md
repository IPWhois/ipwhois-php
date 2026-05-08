# ipwhois-php

[![Packagist Version](https://img.shields.io/packagist/v/ipwhois/ipwhois-php.svg)](https://packagist.org/packages/ipwhois/ipwhois-php)
[![PHP Version](https://img.shields.io/packagist/php-v/ipwhois/ipwhois-php.svg)](https://packagist.org/packages/ipwhois/ipwhois-php)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Official, dependency-free PHP client for the [ipwhois.io](https://ipwhois.io) IP Geolocation API.

- ✅ Single and bulk IP lookups (IPv4 and IPv6)
- ✅ Works with both the **Free** and **Paid** plans
- ✅ HTTPS by default
- ✅ Localisation, field selection, threat detection, rate info
- ✅ Typed exceptions for clean error handling
- ✅ No external dependencies — only the cURL extension
- ✅ PHP 8.0+

## Installation

```bash
composer require ipwhois/ipwhois-php
```

## Free vs Paid plan

The same `Client` class is used for both plans. The only difference is whether
you pass an API key:

- **Free plan** — create the client **without arguments**. No API key, no
  signup required. Suitable for low-traffic and non-commercial use.
- **Paid plan** — create the client **with your API key** from
  <https://ipwhois.io>. Higher limits, plus access to bulk lookups and
  threat-detection data.

```php
$free = new \Ipwhois\Client();               // Free plan — no API key
$paid = new \Ipwhois\Client('YOUR_API_KEY'); // Paid plan — with API key
```

Everything else (`lookup()`, options, error handling) is identical.

## Quick start — Free plan (no API key)

```php
require 'vendor/autoload.php';

use Ipwhois\Client;

$client = new Client(); // no API key

$info = $client->lookup('8.8.8.8');

echo $info['country'] . ' ' . $info['flag']['emoji'] . PHP_EOL;
// → United States 🇺🇸

echo $info['city'] . ', ' . $info['region'] . PHP_EOL;
// → Mountain View, California
```

## Quick start — Paid plan (with API key)

Get an API key at <https://ipwhois.io> and pass it to the constructor:

```php
require 'vendor/autoload.php';

use Ipwhois\Client;

$client = new Client('YOUR_API_KEY'); // with API key

$info = $client->lookup('8.8.8.8');

echo $info['country'] . ' ' . $info['flag']['emoji'] . PHP_EOL;
// → United States 🇺🇸

echo $info['city'] . ', ' . $info['region'] . PHP_EOL;
// → Mountain View, California
```

> ℹ️ Pass nothing to look up your own public IP: `$client->lookup();` — works
> on both plans.

## Lookup options

Every option below can be passed per call, or set once on the client as a
default.

| Option       | Type    | Plans needed         | Description                                                            |
| ------------ | ------- | -------------------- | ---------------------------------------------------------------------- |
| `lang`       | string  | Free + Paid          | One of: `en`, `ru`, `de`, `es`, `pt-BR`, `fr`, `zh-CN`, `ja`           |
| `fields`     | array   | Free + Paid          | Restrict the response to specific fields (e.g. `['country', 'city']`)  |
| `output`     | string  | Free + Paid          | `json` (default), `xml`, `csv`                                         |
| `rate`       | bool    | Basic and above      | Include the `rate` block (`limit`, `remaining`)                        |
| `security`   | bool    | Business and above   | Include the `security` block (proxy/vpn/tor/hosting)                   |

### Setting defaults once

If you make many calls with the same options, set them once and forget:

```php
// Free plan
$client = (new Client())
    ->setLanguage('en')
    ->setFields(['country', 'city', 'flag.emoji'])
    ->setTimeout(8);

$client->lookup('8.8.8.8');                   // uses all of the above
$client->lookup('1.1.1.1', ['lang' => 'de']); // per-call options override defaults
```

```php
// Paid plan
$client = (new Client('YOUR_API_KEY'))
    ->setLanguage('en')
    ->setFields(['country', 'city', 'flag.emoji'])
    ->setTimeout(8);

$client->lookup('8.8.8.8');                   // uses all of the above
$client->lookup('1.1.1.1', ['lang' => 'de']); // per-call options override defaults
```

> ℹ️ Paid plans additionally support `setSecurity(true)` (Business+) and
> `setRate(true)` (Basic+). See the table above for what's available where.

## HTTPS Encryption

By default, all requests are sent over HTTPS. If you need to disable it (for
example, in environments without an up-to-date CA bundle), pass `'ssl' => false`
to the constructor:

```php
use Ipwhois\Client;

// Free plan
$client = new Client(null, ['ssl' => false]);
```

```php
use Ipwhois\Client;

// Paid plan
$client = new Client('YOUR_API_KEY', ['ssl' => false]);
```

> ℹ️ HTTPS is strongly recommended for production traffic — your API key is
> sent in the query string and would otherwise travel in clear text.

## Bulk lookup (Paid plan only)

The bulk endpoint sends **up to 100 IPs** in a single GET request. Each
address counts as one credit. Available on the **Business** and **Unlimited**
plans.

```php
$client = new Client('YOUR_API_KEY');

$results = $client->bulkLookup([
    '8.8.8.8',
    '1.1.1.1',
    '208.67.222.222',
    '2c0f:fb50:4003::',   // IPv6 is fine — mix freely
]);

foreach ($results as $row) {
    if (($row['success'] ?? false) === false) {
        // Per-IP errors (e.g. "Invalid IP address") are returned inline,
        // they do NOT throw — the rest of the batch is still usable.
        echo "skip {$row['ip']}: {$row['message']}\n";
        continue;
    }
    echo "{$row['ip']} → {$row['country']}\n";
}
```

> ℹ️ Bulk requires an API key. Calling `bulkLookup()` without one will fail
> at the API level.

## Error handling

The library throws specific exceptions so you can react accordingly:

```php
use Ipwhois\Client;
use Ipwhois\Exception\ApiException;
use Ipwhois\Exception\AuthenticationException;
use Ipwhois\Exception\NetworkException;
use Ipwhois\Exception\RateLimitException;

try {
    $info = (new Client('YOUR_API_KEY'))->lookup('8.8.8.8');
} catch (AuthenticationException $e) {
    // HTTP 401 — invalid API key or expired subscription
} catch (RateLimitException $e) {
    // HTTP 429 — back off, then retry
    sleep($e->getRetryAfter() ?? 60);
} catch (ApiException $e) {
    // Any other API error, including HTTP 200 with success=false
    // (e.g. "Invalid IP address", "Reserved range")
    error_log("API error {$e->getStatusCode()}: {$e->getMessage()}");
} catch (NetworkException $e) {
    // DNS failure, connection timeout, malformed JSON, …
}
```

All exceptions extend `Ipwhois\Exception\IpwhoisException`, so a single
`catch (\Ipwhois\Exception\IpwhoisException $e)` works as a catch-all.

## Response shape

A successful response includes (depending on your plan and selected options):

```jsonc
{
    "ip": "8.8.4.4",
    "success": true,
    "type": "IPv4",
    "continent": "North America",
    "continent_code": "NA",
    "country": "United States",
    "country_code": "US",
    "region": "California",
    "region_code": "CA",
    "city": "Mountain View",
    "latitude": 37.3860517,
    "longitude": -122.0838511,
    "is_eu": false,
    "postal": "94039",
    "calling_code": "1",
    "capital": "Washington D.C.",
    "borders": "CA,MX",
    "flag": {
        "img": "https://cdn.ipwhois.io/flags/us.svg",
        "emoji": "🇺🇸",
        "emoji_unicode": "U+1F1FA U+1F1F8"
    },
    "connection": {
        "asn": 15169,
        "org": "Google LLC",
        "isp": "Google LLC",
        "domain": "google.com"
    },
    "timezone": {
        "id": "America/Los_Angeles",
        "abbr": "PDT",
        "is_dst": true,
        "offset": -25200,
        "utc": "-07:00",
        "current_time": "2026-05-08T14:31:48-07:00"
    },
    "currency": {
        "name": "US Dollar",
        "code": "USD",
        "symbol": "$",
        "plural": "US dollars",
        "exchange_rate": 1
    },
    "security": {
        "anonymous": false,
        "proxy": false,
        "vpn": false,
        "tor": false,
        "hosting": false
    },
    "rate": {
        "limit": 250000,
        "remaining": 50155
    }
}
```

For the full field reference, see the [official documentation](https://ipwhois.io/documentation).

## Requirements

- PHP **8.0** or newer
- ext-curl
- ext-json

## Contributing

Issues and pull requests are welcome on
[GitHub](https://github.com/IPWhois/ipwhois-php).

## License

[MIT](LICENSE) © ipwhois.io

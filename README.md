# ipwhois-php

[![Packagist Version](https://img.shields.io/packagist/v/ipwhois/ipwhois-php.svg)](https://packagist.org/packages/ipwhois/ipwhois-php)
[![PHP Version](https://img.shields.io/packagist/php-v/ipwhois/ipwhois-php.svg)](https://packagist.org/packages/ipwhois/ipwhois-php)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Official, dependency-free PHP client for the [ipwhois.io](https://ipwhois.io) IP Geolocation API.

- ✅ Single and bulk IP lookups (IPv4 and IPv6)
- ✅ Works with both the **Free** and **Paid** plans
- ✅ HTTPS by default
- ✅ Localisation, field selection, threat detection, rate info
- ✅ Never throws — all errors returned as `success: false` arrays
- ✅ No external dependencies — only the cURL extension
- ✅ PHP 8.0+

## Installation

```bash
composer require ipwhois/ipwhois-php
```

## Free vs Paid plan

The same `IPWhois` class is used for both plans. The only difference is whether
you pass an API key:

- **Free plan** — create the client **without arguments**. No API key, no
  signup required. Suitable for low-traffic and non-commercial use.
- **Paid plan** — create the client **with your API key** from
  <https://ipwhois.io>. Higher limits, plus access to bulk lookups and
  threat-detection data.

```php
$free = new \Ipwhois\IPWhois();               // Free plan — no API key
$paid = new \Ipwhois\IPWhois('YOUR_API_KEY'); // Paid plan — with API key
```

Everything else (`lookup()`, options, error handling) is identical.

## Quick start — Free plan (no API key)

```php
require 'vendor/autoload.php';

use Ipwhois\IPWhois;

$ipwhois = new IPWhois(); // no API key

$info = $ipwhois->lookup('8.8.8.8');

echo $info['country'] . ' ' . $info['flag']['emoji'] . PHP_EOL;
// → United States 🇺🇸

echo $info['city'] . ', ' . $info['region'] . PHP_EOL;
// → Mountain View, California
```

## Quick start — Paid plan (with API key)

Get an API key at <https://ipwhois.io> and pass it to the constructor:

```php
require 'vendor/autoload.php';

use Ipwhois\IPWhois;

$ipwhois = new IPWhois('YOUR_API_KEY'); // with API key

$info = $ipwhois->lookup('8.8.8.8');

echo $info['country'] . ' ' . $info['flag']['emoji'] . PHP_EOL;
// → United States 🇺🇸

echo $info['city'] . ', ' . $info['region'] . PHP_EOL;
// → Mountain View, California
```

> ℹ️ Pass nothing to look up your own public IP: `$ipwhois->lookup();` — works
> on both plans.

## Lookup options

Every option below can be passed per call, or set once on the client as a
default.

| Option       | Type    | Plans needed         | Description                                                            |
| ------------ | ------- | -------------------- | ---------------------------------------------------------------------- |
| `lang`       | string  | Free + Paid          | One of: `en`, `ru`, `de`, `es`, `pt-BR`, `fr`, `zh-CN`, `ja`           |
| `fields`     | array   | Free + Paid          | Restrict the response to specific fields (e.g. `['country', 'city']`)  |
| `rate`       | bool    | Basic and above      | Include the `rate` block (`limit`, `remaining`)                        |
| `security`   | bool    | Business and above   | Include the `security` block (proxy/vpn/tor/hosting)                   |

### Setting defaults once

Every option can be passed two ways: **per call** (as the second argument to
`lookup()` / `bulkLookup()`) or **once as a default** on the client. Per-call
options always override the defaults, so it's safe to set sensible defaults
and only override what differs for a specific call.

Defaults are set with fluent setters — `setLanguage()`, `setFields()`,
`setSecurity()`, `setRate()`, `setTimeout()`, `setConnectTimeout()`,
`setUserAgent()` — and can be chained:

```php
// Pass 'YOUR_API_KEY' to the constructor for the paid plan; otherwise omit it.
$ipwhois = (new IPWhois())
    ->setLanguage('en')
    ->setFields(['success', 'country', 'city', 'flag.emoji'])
    ->setTimeout(8);

$ipwhois->lookup('8.8.8.8');                   // uses lang=en, the field whitelist, and timeout=8
$ipwhois->lookup('1.1.1.1', ['lang' => 'de']); // overrides lang for this single call only
```

> ⚠️ When you restrict fields with `setFields()` (or the per-call `'fields'`
> option), the API only returns the fields you ask for. Always include
> `'success'` in the list if you rely on `$info['success']` for error
> checking — otherwise the field will be missing on responses.

> ℹ️ `setSecurity(true)` requires Business+ and `setRate(true)` requires
> Basic+. See the table above for what's available where.

## HTTPS Encryption

By default, all requests are sent over HTTPS. If you need to disable it (for
example, in environments without an up-to-date CA bundle), pass `'ssl' => false`
to the constructor:

```php
use Ipwhois\IPWhois;

// Free plan
$ipwhois = new IPWhois(null, ['ssl' => false]);
```

```php
use Ipwhois\IPWhois;

// Paid plan
$ipwhois = new IPWhois('YOUR_API_KEY', ['ssl' => false]);
```

> ℹ️ HTTPS is strongly recommended for production traffic — your API key is
> sent in the query string and would otherwise travel in clear text.

## Bulk lookup (Paid plan only)

The bulk endpoint sends **up to 100 IPs** in a single GET request. Each
address counts as one credit. Available on the **Business** and **Unlimited**
plans.

```php
$ipwhois = new IPWhois('YOUR_API_KEY');

$results = $ipwhois->bulkLookup([
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

**The library never throws.** Every failure — invalid IP, bad API key, rate
limit, network outage, missing extension, bad options — comes back inside
the response array with `success => false` and a `message`. Just check
`$info['success']` after every call:

```php
$info = $ipwhois->lookup('8.8.8.8');

if (!$info['success']) {
    error_log("Lookup failed: {$info['message']}");
    return;
}

echo $info['country'];
```

This means an outage of the ipwhois.io API (or of your server's DNS,
connection, etc.) will never surface as a fatal error in your application —
you decide how to react.

### Error response fields

Every error response contains `success: false` and a `message`. Some errors
include extra fields you can branch on:

| Field          | When it's present                                                       |
| -------------- | ----------------------------------------------------------------------- |
| `error_type`   | `'network'`, `'environment'`, or `'invalid_argument'` — for non-API errors |
| `http_status`  | On HTTP 4xx / 5xx responses                                             |
| `retry_after`  | On HTTP 429 if the API sent a `Retry-After` header                      |

```php
$info = $ipwhois->lookup('8.8.8.8');

if (!$info['success']) {
    if (($info['http_status'] ?? 0) === 429) {
        sleep($info['retry_after'] ?? 60);
        // …retry
    }
    if (($info['error_type'] ?? null) === 'network') {
        // DNS failure, connection refused, timeout, …
    }
    error_log("Error: {$info['message']}");
    return;
}
```

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

An **error** response looks like:

```jsonc
{
    "success": false,
    "message": "Invalid IP address",
    "http_status": 400         // present for HTTP 4xx / 5xx
    // "retry_after": 60       // additionally present on HTTP 429 if the API sent a Retry-After header
    // "error_type": "network" // present for non-API errors: 'network', 'environment', 'invalid_argument'
}
```

## Requirements

- PHP **8.0** or newer
- ext-curl
- ext-json

## Contributing

Issues and pull requests are welcome on
[GitHub](https://github.com/IPWhois/ipwhois-php).

## License

[MIT](LICENSE) © ipwhois.io


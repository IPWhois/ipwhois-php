# Changelog

All notable changes to `ipwhois/ipwhois-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2026-05-08

### Fixed

- Non-JSON responses (when `output=xml` or `output=csv` is requested) now
  include `success => true` alongside the `raw` payload, so the universal
  `if (!$info['success'])` check documented in README works for these
  responses too. Previously they only contained the `raw` key, which made
  a `!$info['success']` check evaluate to `true` for a successful response.
- Updated the `examples/defaults.php` example to actually follow the
  success-check contract documented in README.

## [1.1.0] - 2026-05-08

### Changed

- **Behaviour change: the library never throws.** All errors — API errors,
  network failures, missing extension, bad input options — are returned in
  the response array with `success => false` and a `message`. An outage of
  the ipwhois.io API or of your server's DNS / connection will never surface
  as a fatal error in your application.
- HTTP error responses are additionally enriched with `http_status`, and
  HTTP 429 responses with `retry_after` (when the API sent a `Retry-After`
  header).
- Non-API errors carry an `error_type` field (`'network'`, `'environment'`,
  or `'invalid_argument'`) so you can branch on the cause if needed.

### Migration

```php
// Before (1.0.x):
try {
    $info = $client->lookup('bad.ip');
} catch (\Ipwhois\Exception\ApiException $e) {
    error_log($e->getMessage());
} catch (\Ipwhois\Exception\NetworkException $e) {
    error_log($e->getMessage());
}

// After (1.1.0+):
$info = $client->lookup('bad.ip');
if (!$info['success']) {
    error_log($info['message']);
}
```

### Removed

- All exception classes (`IpwhoisException`, `ApiException`,
  `AuthenticationException`, `RateLimitException`, `NetworkException`) have
  been removed. The library never throws, so they had no purpose. Code that
  imported them from 1.0.x will need to drop those `use` statements; code
  that only called `lookup()` / `bulkLookup()` and read the result is
  unaffected.

## [1.0.1] - 2026-05-08

### Fixed

- The library now actually runs on PHP 8.0, matching the version constraint
  declared in `composer.json`. Previous releases used `readonly` properties,
  which require PHP 8.1+. Replaced them with classical `private` properties
  with constructor assignment — public API and exception behaviour are
  unchanged.

## [1.0.0] - 2026-05-08

### Added

- Initial release.
- `Client::lookup()` — single IP lookup (IPv4 / IPv6, or current IP).
- `Client::bulkLookup()` — up to 100 IPs in a single GET request (paid plan).
- Localisation, field filtering, threat detection (`security`), rate info.
- Optional `'ssl' => false` flag to fall back to HTTP.
- Typed exceptions: `IpwhoisException`, `ApiException`,
  `AuthenticationException`, `RateLimitException`, `NetworkException`.
- Fluent setters for client-wide defaults.
- PHPUnit test suite covering URL construction and input validation.

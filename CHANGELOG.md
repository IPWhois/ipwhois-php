# Changelog

All notable changes to `ipwhois/ipwhois-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.1] - 2026-05-11

### Fixed

- Removed two `curl_close()` calls from the internal request handler. The
  function has been a no-op since PHP 8.0 (`curl_init()` returns a
  `CurlHandle` object that is automatically freed when it goes out of
  scope) and was deprecated in PHP 8.5, which made the library emit:

      Deprecated: Function curl_close() is deprecated since 8.5, as it
      has no effect since PHP 8.0 in …/src/IPWhois.php

  The handle is now released by PHP's garbage collector at the end of
  `request()`. No behavioural change — public API, return shapes, and
  error handling are identical to 1.2.0.

## [1.2.0] - 2026-05-10

### Added

- Every error response now carries an `error_type` field, including errors
  returned by the API. The new value `'api'` joins the existing `'network'`,
  `'environment'`, and `'invalid_argument'` codes, so callers can branch on
  the category of any failure with a single `$info['error_type']` check —
  no need to combine `success` with `http_status` to distinguish API vs.
  non-API errors. Applies to HTTP 4xx / 5xx responses, malformed JSON
  bodies, and HTTP 2xx responses where the API itself sets `success: false`
  (e.g. "Invalid IP address", "Reserved range").

### Changed

- `retry_after` is now only attached to HTTP 429 responses on the **free
  plan** (`ipwho.is`). The paid endpoint (`ipwhois.pro`) does not send a
  `Retry-After` header, so reading it on paid plans is now skipped and the
  field will not appear there. Behaviour on the free plan is unchanged.
- README "Setting defaults once" section now shows the Free and Paid plans
  as two separate code blocks, matching the layout used in "Quick start"
  and "HTTPS Encryption". The setters work identically on both plans, so
  the lookup-override snippet is shared underneath.
- README "Error response fields" table now lists `message` explicitly (it
  has always been present on every error response) and the `error_type`
  row covers the new `'api'` value as well.

## [1.1.4] - 2026-05-10

### Removed

- **The `output` option has been removed.** The library only ever processed
  JSON responses meaningfully, so `output=xml` and `output=csv` were a
  thin pass-through that returned the raw payload as a string. The option
  has been dropped from `lookup()`, `bulkLookup()`, and the constructor's
  `$options` array; the `IPWhois::SUPPORTED_OUTPUTS` constant is gone.
  Passing `'output' => …` will silently no-op.
- The 2xx + non-JSON success-with-`raw` fallback in the response handler
  (which only existed to support the removed `output` parameter) is gone.
  The API always returns JSON, so any non-JSON 2xx body is now treated as
  a transport error and returned as a `success => false` array.

### Changed

- `setFields()` PHPDoc now mentions that `success` should be included in
  the field whitelist if you rely on `$info['success']` for error checking
  — when `fields` is set, the API only returns the fields you list.
- README "Setting defaults once" section rewritten for clarity: the two
  ways of passing options (per call vs. as defaults), the available
  setters, and the `success`-in-`fields` gotcha are now spelled out
  explicitly. The free/paid example pair was collapsed into a single
  example, since the setters work identically on both plans.
- All examples that filter fields (`README.md`, `examples/basic.php`,
  `examples/defaults.php`) now include `'success'` in the field list.

### Migration

If your code passes `'output' => 'json'` you can simply remove it — the
library always returns the decoded JSON anyway. If you were relying on
`'output' => 'xml'` or `'output' => 'csv'` to get the raw payload, that
use case is no longer supported; call the API directly with cURL for
those formats.

```php
// Before (1.1.3):
$info = $ipwhois->lookup('8.8.8.8', ['output' => 'json', 'fields' => ['country', 'city']]);

// After (1.1.4):
$info = $ipwhois->lookup('8.8.8.8', ['fields' => ['success', 'country', 'city']]);
```

## [1.1.3] - 2026-05-09

### Changed

- Bumped `IPWhois::VERSION` constant to `1.1.3` (in 1.1.2 it was still set
  to `1.1.2`, this release just keeps the constant in sync with the actual
  released tag). No functional changes since 1.1.2.

## [1.1.2] - 2026-05-09

### Changed

- **Renamed the main class `Client` to `IPWhois`** for consistency with the
  package and brand. The fully-qualified name is now `\Ipwhois\IPWhois`. The
  source file moved from `src/Client.php` to `src/IPWhois.php`, and the test
  class from `tests/ClientTest.php` to `tests/IPWhoisTest.php`. Public
  behaviour, method signatures, constructor arguments, and return shapes are
  all unchanged.

### Migration

```php
// Before (1.1.1):
use Ipwhois\Client;
$client = new Client('YOUR_API_KEY');
$info   = $client->lookup('8.8.8.8');

// After (1.1.2+):
use Ipwhois\IPWhois;
$ipwhois = new IPWhois('YOUR_API_KEY');
$info    = $ipwhois->lookup('8.8.8.8');
```

The variable name (`$client`, `$ipwhois`, anything else) is up to you; only
the class identifier changed.

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
    $info = $ipwhois->lookup('bad.ip');
} catch (\Ipwhois\Exception\ApiException $e) {
    error_log($e->getMessage());
} catch (\Ipwhois\Exception\NetworkException $e) {
    error_log($e->getMessage());
}

// After (1.1.0+):
$info = $ipwhois->lookup('bad.ip');
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
- `IPWhois::lookup()` — single IP lookup (IPv4 / IPv6, or current IP).
- `IPWhois::bulkLookup()` — up to 100 IPs in a single GET request (paid plan).
- Localisation, field filtering, threat detection (`security`), rate info.
- Optional `'ssl' => false` flag to fall back to HTTP.
- Typed exceptions: `IpwhoisException`, `ApiException`,
  `AuthenticationException`, `RateLimitException`, `NetworkException`.
- Fluent setters for client-wide defaults.
- PHPUnit test suite covering URL construction and input validation.

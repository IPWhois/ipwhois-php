# Changelog

All notable changes to `ipwhois/ipwhois-php` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

<?php

declare(strict_types=1);

namespace Ipwhois;

use Ipwhois\Exception\ApiException;
use Ipwhois\Exception\AuthenticationException;
use Ipwhois\Exception\NetworkException;
use Ipwhois\Exception\RateLimitException;

/**
 * PHP client for the ipwhois.io IP Geolocation API.
 *
 * Quick start
 * -----------
 *   // Free plan (no API key, ~1 request/second per client IP)
 *   $client = new \Ipwhois\Client();
 *   $info   = $client->lookup('8.8.8.8');
 *
 *   // Paid plan (with API key, higher limits, bulk, security data, …)
 *   $client = new \Ipwhois\Client('YOUR_API_KEY');
 *   $info   = $client->lookup('8.8.8.8', ['lang' => 'en', 'security' => true]);
 *
 *   // Bulk lookup — up to 100 IPs in one call (paid only)
 *   $list = $client->bulkLookup(['8.8.8.8', '1.1.1.1', '208.67.222.222']);
 *
 *   // HTTPS is enabled by default. Pass ['ssl' => false] to fall back to HTTP.
 */
final class Client
{
    /** Library version, used in the default User-Agent header. */
    public const VERSION = '1.0.0';

    /** Free-plan endpoint host (used when no API key is provided). */
    public const HOST_FREE = 'ipwho.is';

    /** Paid-plan endpoint host (used when an API key is provided). */
    public const HOST_PAID = 'ipwhois.pro';

    /** Maximum number of IP addresses allowed in a single bulk request. */
    public const BULK_LIMIT = 100;

    /** Languages supported by the `lang` parameter. */
    public const SUPPORTED_LANGUAGES = ['en', 'ru', 'de', 'es', 'pt-BR', 'fr', 'zh-CN', 'ja'];

    /** Output formats supported by the `output` parameter. */
    public const SUPPORTED_OUTPUTS = ['json', 'xml', 'csv'];

    private string $userAgent = 'ipwhois-php/' . self::VERSION;
    private int $timeout = 10;
    private int $connectTimeout = 5;
    private bool $ssl = true;

    /** Default options applied to every request unless overridden. */
    private array $defaults = [];

    /**
     * @param string|null $apiKey  Your ipwhois.io API key. Omit for the free plan.
     * @param array       $options Optional defaults applied to every request.
     *                             Recognised keys: `lang`, `fields`, `security`,
     *                             `rate`, `output`, `ssl`, `timeout`,
     *                             `connect_timeout`, `user_agent`.
     */
    public function __construct(
        private readonly ?string $apiKey = null,
        array $options = [],
    ) {
        if (!\extension_loaded('curl')) {
            throw new NetworkException('The cURL PHP extension is required by ipwhois/ipwhois-php.');
        }

        if (isset($options['timeout'])) {
            $this->timeout = (int) $options['timeout'];
            unset($options['timeout']);
        }
        if (isset($options['connect_timeout'])) {
            $this->connectTimeout = (int) $options['connect_timeout'];
            unset($options['connect_timeout']);
        }
        if (isset($options['user_agent'])) {
            $this->userAgent = (string) $options['user_agent'];
            unset($options['user_agent']);
        }
        if (array_key_exists('ssl', $options)) {
            $this->ssl = (bool) $options['ssl'];
            unset($options['ssl']);
        }

        $this->defaults = $options;
    }

    /**
     * Look up information for a single IP address.
     *
     * Pass `null` (or call without arguments) to look up the caller's own
     * public IP, as documented at https://ipwhois.io/documentation.
     *
     * @param string|null $ip      IPv4 or IPv6 address. Null = current IP.
     * @param array       $options Per-call options: `lang`, `fields`,
     *                             `security` (bool), `rate` (bool), `output`.
     *
     * @return array<string, mixed> Decoded JSON response.
     *
     * @throws ApiException            On any API-level error.
     * @throws AuthenticationException On HTTP 401 (paid plan, bad key).
     * @throws RateLimitException      On HTTP 429.
     * @throws NetworkException        On transport / parsing failure.
     */
    public function lookup(?string $ip = null, array $options = []): array
    {
        $path = $ip !== null ? '/' . rawurlencode($ip) : '/';
        $url  = $this->buildUrl($path, $options);

        return $this->request($url);
    }

    /**
     * Look up information for multiple IP addresses in a single request.
     *
     * Uses the GET / comma-separated form documented at
     * https://ipwhois.io/documentation/bulk — up to 100 addresses per call.
     * Each address counts as one credit.
     *
     * Available on the Business and Unlimited plans only.
     *
     * @param string[] $ips     Up to 100 IPv4/IPv6 addresses (mixable).
     * @param array    $options Per-call options (same keys as {@see lookup()}).
     *
     * @return array<int, array<string, mixed>> List of results, one per input IP.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws RateLimitException
     * @throws NetworkException
     * @throws \InvalidArgumentException If the input list is empty or too large.
     */
    public function bulkLookup(array $ips, array $options = []): array
    {
        if ($ips === []) {
            throw new \InvalidArgumentException('Bulk lookup requires at least one IP address.');
        }
        if (\count($ips) > self::BULK_LIMIT) {
            throw new \InvalidArgumentException(
                sprintf('Bulk lookup accepts at most %d IP addresses per call.', self::BULK_LIMIT)
            );
        }

        // The API accepts addresses joined by commas — no URL-encoding of the
        // commas themselves, otherwise the path is misinterpreted.
        $joined = implode(',', array_map(static fn ($ip) => rawurlencode((string) $ip), $ips));
        $url    = $this->buildUrl('/bulk/' . $joined, $options);

        $data = $this->request($url);

        // The bulk endpoint returns either a JSON array of results, or — on
        // API-level failure — an associative error object. The latter has
        // already been turned into an ApiException by request().
        return $data;
    }

    /**
     * Set the default language used when none is supplied per call.
     *
     * @param string $lang One of {@see SUPPORTED_LANGUAGES}.
     */
    public function setLanguage(string $lang): self
    {
        $this->defaults['lang'] = $lang;
        return $this;
    }

    /**
     * Restrict every response to a fixed set of fields by default.
     *
     * @param string[] $fields For example: ['country', 'city', 'flag.emoji'].
     */
    public function setFields(array $fields): self
    {
        $this->defaults['fields'] = $fields;
        return $this;
    }

    /** Enable or disable threat-detection data on every call by default. */
    public function setSecurity(bool $enabled): self
    {
        $this->defaults['security'] = $enabled;
        return $this;
    }

    /** Enable or disable the `rate` block in responses by default. */
    public function setRate(bool $enabled): self
    {
        $this->defaults['rate'] = $enabled;
        return $this;
    }

    /** Set the per-request total timeout in seconds (default: 10). */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /** Set the connection timeout in seconds (default: 5). */
    public function setConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    /** Override the User-Agent header sent with every request. */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Build the full HTTPS URL for a given path + options.
     */
    private function buildUrl(string $path, array $options): string
    {
        $host = $this->apiKey !== null ? self::HOST_PAID : self::HOST_FREE;

        // Per-call options win over defaults.
        $merged = array_replace($this->defaults, $options);

        $query = [];

        if ($this->apiKey !== null) {
            $query['key'] = $this->apiKey;
        }

        if (isset($merged['lang'])) {
            $lang = (string) $merged['lang'];
            if (!\in_array($lang, self::SUPPORTED_LANGUAGES, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Unsupported language "%s". Supported: %s.',
                    $lang,
                    implode(', ', self::SUPPORTED_LANGUAGES)
                ));
            }
            $query['lang'] = $lang;
        }

        if (isset($merged['output'])) {
            $output = (string) $merged['output'];
            if (!\in_array($output, self::SUPPORTED_OUTPUTS, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Unsupported output format "%s". Supported: %s.',
                    $output,
                    implode(', ', self::SUPPORTED_OUTPUTS)
                ));
            }
            $query['output'] = $output;
        }

        if (isset($merged['fields'])) {
            $query['fields'] = \is_array($merged['fields'])
                ? implode(',', $merged['fields'])
                : (string) $merged['fields'];
        }

        if (!empty($merged['security'])) {
            $query['security'] = '1';
        }

        if (!empty($merged['rate'])) {
            $query['rate'] = '1';
        }

        $url = ($this->ssl ? 'https' : 'http') . '://' . $host . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * Perform a GET request and return the decoded JSON body.
     *
     * @return array<int|string, mixed>
     */
    private function request(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
            ],
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            $no  = curl_errno($ch);
            curl_close($ch);
            throw new NetworkException(sprintf('cURL error (%d): %s', $no, $err));
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr((string) $raw, 0, $headerSize);
        $body       = substr((string) $raw, $headerSize);
        $headers    = $this->parseHeaders($rawHeaders);

        $decoded = null;
        if ($body !== '') {
            try {
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                // Non-JSON output is legitimate when output=xml or output=csv
                // was requested — return a thin wrapper so the caller still
                // gets the raw payload.
                if ($statusCode >= 200 && $statusCode < 300) {
                    return ['raw' => $body];
                }

                // Non-JSON 4xx/5xx — surface as an ApiException carrying the
                // HTTP status, which is more useful to the caller than a
                // generic JSON-decode error.
                $snippet = trim((string) preg_replace('/\s+/', ' ', $body));
                if (\strlen($snippet) > 200) {
                    $snippet = substr($snippet, 0, 200) . '…';
                }
                throw new ApiException(
                    sprintf('HTTP %d returned by ipwhois API: %s', $statusCode, $snippet),
                    $statusCode,
                    null,
                    $e
                );
            }
        }

        if (!\is_array($decoded)) {
            $decoded = $decoded === null ? [] : ['value' => $decoded];
        }

        // Application-level error returned with HTTP 200 (e.g. "Invalid IP
        // address", "Reserved range") — surface as ApiException.
        if ($statusCode >= 200 && $statusCode < 300
            && isset($decoded['success']) && $decoded['success'] === false
        ) {
            throw new ApiException(
                (string) ($decoded['message'] ?? 'API returned success=false'),
                $statusCode,
                $decoded
            );
        }

        if ($statusCode >= 400) {
            $message = \is_array($decoded) && isset($decoded['message'])
                ? (string) $decoded['message']
                : sprintf('HTTP %d returned by ipwhois API', $statusCode);

            throw match ($statusCode) {
                401     => new AuthenticationException($message, $statusCode, $decoded),
                429     => new RateLimitException(
                    $message,
                    $statusCode,
                    $decoded,
                    isset($headers['retry-after']) ? (int) $headers['retry-after'] : null
                ),
                default => new ApiException($message, $statusCode, $decoded),
            };
        }

        return $decoded;
    }

    /**
     * Parse a raw HTTP header block into a lowercase-keyed map.
     *
     * @return array<string, string>
     */
    private function parseHeaders(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name  = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            if ($name !== '') {
                $out[$name] = $value;
            }
        }
        return $out;
    }
}

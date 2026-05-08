<?php

declare(strict_types=1);

namespace Ipwhois\Exception;

/**
 * Thrown when the API returns HTTP 429 (rate limit exceeded).
 *
 * For the Free endpoint, the documentation says clients are limited to about
 * 1 request per second and the IP is restricted for 5 minutes after a violation.
 * The response includes a Retry-After header indicating when the limit resets.
 */
class RateLimitException extends ApiException
{
    private ?int $retryAfter;

    /**
     * @param array<string, mixed>|null $response
     */
    public function __construct(
        string $message,
        int $statusCode = 429,
        ?array $response = null,
        ?int $retryAfter = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $response, $previous);
        $this->retryAfter = $retryAfter;
    }

    /**
     * Number of seconds until the rate limit resets, taken from the
     * Retry-After response header (null if the header was not present).
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}

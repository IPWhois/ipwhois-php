<?php

declare(strict_types=1);

namespace Ipwhois\Exception;

/**
 * Thrown when the API returns an error response.
 *
 * This covers two situations described in the ipwhois.io documentation:
 *   - HTTP 4xx responses (400, 401, 403, 404, 405, 414, 429)
 *   - HTTP 200 responses where the body has {"success": false, "message": "..."}
 *     (for example: "Invalid IP address" or "Reserved range")
 *
 * Use {@see getStatusCode()} to inspect the HTTP status, and
 * {@see getMessage()} to read the message returned by the API.
 */
class ApiException extends IpwhoisException
{
    private int $statusCode;

    /** @var array<string, mixed>|null */
    private ?array $response;

    /**
     * @param array<string, mixed>|null $response
     */
    public function __construct(
        string $message,
        int $statusCode = 0,
        ?array $response = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->response   = $response;
    }

    /**
     * HTTP status code returned by the API (or 0 for application-level
     * errors that came back with HTTP 200).
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The full decoded response body, when available.
     *
     * @return array<string, mixed>|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }
}

<?php

declare(strict_types=1);

namespace Ipwhois\Exception;

/**
 * Thrown for transport-level problems (DNS failure, connection timeout,
 * TLS handshake error, malformed JSON in the response, etc.).
 *
 * In other words: the request could not be completed or the response
 * could not be understood — distinct from {@see ApiException}, which is
 * thrown when the API itself returns a recognised error.
 */
class NetworkException extends IpwhoisException
{
}

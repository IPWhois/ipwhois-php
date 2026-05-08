<?php

declare(strict_types=1);

namespace Ipwhois\Exception;

/**
 * Thrown when the API returns HTTP 401 (invalid API key or expired subscription).
 */
class AuthenticationException extends ApiException
{
}

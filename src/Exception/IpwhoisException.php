<?php

declare(strict_types=1);

namespace Ipwhois\Exception;

/**
 * Base exception for all errors thrown by the ipwhois.io PHP client.
 *
 * Catch this if you want to catch any error from the library.
 */
class IpwhoisException extends \RuntimeException
{
}

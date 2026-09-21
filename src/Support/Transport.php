<?php

declare(strict_types=1);

namespace Darvis\ApiLinkedin\Support;

use Closure;
use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * Sends a request and keeps a connection failure inside the package's exception
 * hierarchy. Host apps are promised that every failure is a LinkedInException;
 * without this a timeout would surface as Laravel's ConnectionException.
 *
 * @internal
 */
final class Transport
{
    /**
     * @param  Closure(): Response  $request
     *
     * @throws LinkedInApiException with status 0 when LinkedIn could not be reached.
     */
    public static function send(string $operation, Closure $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw LinkedInApiException::unreachable($operation, $e);
        }
    }
}

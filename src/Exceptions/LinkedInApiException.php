<?php

namespace Darvis\ApiLinkedin\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * LinkedIn answered a call with an error. Carries the operation, the HTTP status
 * and the raw body, so callers can react on those instead of parsing the message
 * — which is free to change between releases.
 *
 * It also covers the call that never got an answer: LinkedIn unreachable, a DNS
 * failure, a timeout. Then `status` is 0, `body` is empty and the original
 * ConnectionException is the `previous` exception.
 */
class LinkedInApiException extends LinkedInException
{
    public const OPERATION_TOKEN = 'token';

    public const OPERATION_PROFILE = 'profile';

    public const OPERATION_PUBLISH = 'publish';

    public const OPERATION_ORGANIZATIONS = 'organizations';

    public const OPERATION_IMAGE = 'image';

    public function __construct(
        public readonly string $operation,
        public readonly int $status,
        public readonly string $body,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function from(string $operation, Response $response, string $summary): self
    {
        return new self(
            operation: $operation,
            status: $response->status(),
            body: $response->body(),
            message: $summary.': '.$response->body(),
        );
    }

    /**
     * The request never got an answer. The message deliberately leaves out the text
     * of the original exception: it names the URL, and an image upload URL is a
     * signed, single-use secret. The original is available as `getPrevious()`.
     */
    public static function unreachable(string $operation, ConnectionException $previous): self
    {
        return new self(
            operation: $operation,
            status: 0,
            body: '',
            message: 'LinkedIn could not be reached ('.$operation.')',
            previous: $previous,
        );
    }

    /**
     * No response came back at all (timeout, DNS, refused connection). Usually
     * worth a retry, unlike an answer with an error status.
     */
    public function isConnectionProblem(): bool
    {
        return $this->status === 0;
    }

    /**
     * LinkedIn rejected the token or the scopes — usually solved by reconnecting.
     */
    public function isAuthorizationProblem(): bool
    {
        return in_array($this->status, [401, 403], true);
    }
}

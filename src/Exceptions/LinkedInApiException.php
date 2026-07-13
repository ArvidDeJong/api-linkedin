<?php

namespace Darvis\ApiLinkedin\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * LinkedIn answered a call with an error. Carries the operation, the HTTP status
 * and the raw body, so callers can react on those instead of parsing the message
 * — which is free to change between releases.
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
    ) {
        parent::__construct($message);
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
     * LinkedIn rejected the token or the scopes — usually solved by reconnecting.
     */
    public function isAuthorizationProblem(): bool
    {
        return in_array($this->status, [401, 403], true);
    }
}

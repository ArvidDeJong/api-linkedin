<?php

namespace Darvis\ApiLinkedin\Exceptions;

/**
 * The access token expired and there is no usable refresh token left, so the
 * account has to be reconnected. This is the one failure a user can act on
 * themselves, which is why it has its own type.
 */
class LinkedInConnectionExpired extends LinkedInException
{
    public function __construct(string $message = 'The LinkedIn connection has expired. Please reconnect the account.')
    {
        parent::__construct($message);
    }
}

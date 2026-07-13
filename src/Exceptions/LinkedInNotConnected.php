<?php

namespace Darvis\ApiLinkedin\Exceptions;

/**
 * No LinkedIn account is connected at all. The user has to go through the OAuth
 * flow first.
 */
class LinkedInNotConnected extends LinkedInException
{
    public function __construct(string $message = 'No active LinkedIn connection.')
    {
        parent::__construct($message);
    }
}

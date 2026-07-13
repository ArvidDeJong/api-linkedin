<?php

namespace Darvis\ApiLinkedin\Exceptions;

/**
 * The package is missing configuration it needs for the requested call — for
 * example posting to a company page without an URN and without a configured
 * default. A developer error, not something an end user can fix.
 */
class LinkedInConfigurationException extends LinkedInException {}

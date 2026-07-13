<?php

namespace Darvis\ApiLinkedin\Exceptions;

use Darvis\ApiLinkedin\Scopes;

/**
 * The call needs a scope that LinkedIn never granted this connection.
 *
 * Thrown *before* the request goes out: the stored scopes already prove it would
 * come back as a 403, and a 403 is indistinguishable from a dozen other causes.
 * Only raised when the granted scopes are known and the scope is provably absent
 * — see {@see \Darvis\ApiLinkedin\Models\LinkedInAccount::lacksScope()}.
 *
 * The fix is never a retry: add the missing product to the LinkedIn app and
 * reconnect the account, because a token does not gain scopes afterwards.
 */
class LinkedInScopeMissing extends LinkedInException
{
    public function __construct(public readonly string $scope)
    {
        parent::__construct(sprintf(
            'The LinkedIn connection was not granted the "%s" scope%s. Reconnect the account after adding the product to your LinkedIn app.',
            $scope,
            Scopes::requiresCommunityManagementApi($scope) ? ' (which requires the "Community Management API")' : '',
        ));
    }
}

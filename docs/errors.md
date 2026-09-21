---
title: "Error handling"
description: "The exception types of darvis/api-linkedin: not connected, connection expired, missing scope, configuration and API errors, and what your code does with each."
nav_order: 7
---

# Error handling

Every failure throws a subclass of `Darvis\ApiLinkedin\Exceptions\LinkedInException`, and each kind of failure has its own type. React on the **type**, never on the message text: the messages are English, have changed before, and are free to change between releases.

```php
use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInConnectionExpired;
use Darvis\ApiLinkedin\Exceptions\LinkedInNotConnected;
use Darvis\ApiLinkedin\Exceptions\LinkedInScopeMissing;
use Darvis\ApiLinkedin\Facades\LinkedIn;

try {
    LinkedIn::postAsMember($text);
} catch (LinkedInNotConnected) {
    // Nobody connected an account yet.
} catch (LinkedInConnectionExpired) {
    // Send the user back through the OAuth flow; the only failure they can fix.
} catch (LinkedInScopeMissing $e) {
    // $e->scope was never granted. Add the product to the LinkedIn app and reconnect.
    // No request was sent, because LinkedIn would only have answered 403.
} catch (LinkedInApiException $e) {
    // $e->operation  'token' | 'profile' | 'publish' | 'organizations' | 'image'
    // $e->status     HTTP status, 0 when LinkedIn could not be reached
    // $e->body       raw response body; log it, never show it to a visitor
    if ($e->isAuthorizationProblem()) {   // 401 or 403: scope or permission issue
        // ...
    }

    if ($e->isConnectionProblem()) {      // timeout, DNS, refused: no answer at all
        // Worth a retry; getPrevious() is Laravel's ConnectionException.
    }
}
```

| Exception | Meaning | What to do |
| --- | --- | --- |
| `LinkedInNotConnected` | No account is connected. Thrown by every facade method that needs the account | Offer the connect route |
| `LinkedInConnectionExpired` | The token expired and cannot be refreshed | Ask the user to connect again |
| `LinkedInConfigurationException` | `postAsOrganization()` got no URN and `linkedin.organization_urn` is empty | Pass a URN or set `LINKEDIN_ORGANIZATION_URN`; a developer error |
| `LinkedInScopeMissing` | The stored scopes show that the token lacks the scope this call needs (`$e->scope`): `w_organization_social` for a company page author or image owner, `r_organization_admin` for `organizations()`. Thrown before any request goes out | Add the product to the LinkedIn app and reconnect |
| `LinkedInApiException` | LinkedIn returned an error (`operation`, `status`, `body`), answered a successful call with a body the package cannot use, or could not be reached at all (`status` 0, `isConnectionProblem()`) | Log it; check `isAuthorizationProblem()` and the API version, retry on `isConnectionProblem()` |

All of them extend `LinkedInException`, so a single `catch (LinkedInException $e)` still catches everything. `LinkedInConnectionExpired` is the one an end user can act on; everything else is a developer error or an upstream failure, and UIs should keep that distinction.

A timeout or an unreachable LinkedIn is part of the same family since 1.8. The package catches Laravel's `Illuminate\Http\Client\ConnectionException` on every call and throws a `LinkedInApiException` with `status` 0, an empty `body` and the original exception as `getPrevious()`. Its message leaves out the address that failed, because an image upload URL is a signed, single-use secret.

`$e->getMessage()` and `$e->body` of a `LinkedInApiException` hold what LinkedIn answered. That belongs in your log. Show a visitor your own sentence instead, the way the built-in callback does; see [When connecting fails](connecting.md#when-connecting-fails).

## A refused authorization

A denial on the OAuth callback is not an exception but a query string from LinkedIn. `AuthorizationDenial::fromCallback($request)` reads it, decodes the HTML entities LinkedIn puts in the description, and tells you whether the member declined or your app lacks a product; see [Connecting](connecting.md#apps-without-the-community-management-api).

## Looking up a message

[Troubleshooting](troubleshooting.md) lists the literal messages of these exceptions and of the connect flow, each with its cause and its fix.

## A 403 from LinkedIn while the scope check passed

`LinkedInScopeMissing` is only thrown when the package knows the scopes of the token. Two cases still reach LinkedIn and come back as a `LinkedInApiException` with status 403 (`isAuthorizationProblem()` is true):

- The connection was stored before version 1.4, so its scopes are unknown (`$account->grantedScopes()` is `null`).
- The token has the scope, but the member does not administer that company page.

Reconnecting fixes the first case. The second needs the member to get a role on that page that may post.

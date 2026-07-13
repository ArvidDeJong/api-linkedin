# darvis/api-linkedin

> 🇳🇱 [Nederlandse documentatie](docs/nl/README.md)

Universal Laravel integration for publishing posts on **LinkedIn** — both on your
**personal profile** and on a **company page** — through OAuth 2.0 and the
LinkedIn Posts API. Without external dependencies (only the built-in HTTP client).

- OAuth 2.0 authorization code flow with automatic token refresh
- Post on behalf of a member (`w_member_social`) or an organization (`w_organization_social`)
- Encrypted token storage in a `linkedin_accounts` table
- Optional, ready-to-use connect/callback routes
- `LinkedIn` facade for a one-liner post

## Installation

```bash
composer require darvis/api-linkedin
```

Optionally publish the config and the migration (the migration is loaded
automatically as well):

```bash
php artisan vendor:publish --tag=linkedin-config
php artisan vendor:publish --tag=linkedin-migrations
php artisan migrate
```

## Setting up the LinkedIn app

1. Create an app at [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps).
2. Request the products **Share on LinkedIn** (profile) and/or **Community Management API** (company page).
3. Set the redirect URL to `https://your-domain.test/linkedin/callback` (or your own callback route).
4. Fill in your `.env`:

```dotenv
LINKEDIN_CLIENT_ID=...
LINKEDIN_CLIENT_SECRET=...
# Company page — leave empty to post on your profile only
LINKEDIN_ORGANIZATION_URN=urn:li:organization:1234567
# Valid, recent API version (YYYYMM)
LINKEDIN_API_VERSION=202601
```

## Connecting

Send the user to the built-in connect route:

```blade
<a href="{{ route('linkedin.connect') }}">Connect with LinkedIn</a>
```

Afterwards the user is redirected back (see `linkedin.routes.redirect_to`) with a
flash message in `session('linkedin_status')` or `session('linkedin_error')`.

Want to wire the flow yourself? Set `linkedin.routes.enabled` to `false` and use
`LinkedIn::authorizationUrl($state)` and `LinkedIn::connectFromCode($code)`.

## Publishing

```php
use Darvis\ApiLinkedin\Facades\LinkedIn;

// On your personal profile
LinkedIn::postAsMember("New blog article!\n\nhttps://example.com/blog/my-article");

// On the company page
LinkedIn::postAsOrganization('Company news with a link https://example.com');
```

Both return `['urn' => '...', 'permalink' => '...']`. Put a URL in the text and
LinkedIn builds the link preview itself from the Open Graph tags of that page.

> Tip: run the publishing in a queued job, so a slow or failing API call does not
> block your request.

### Through the services (dependency injection)

```php
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;

public function share(LinkedInPublisher $publisher)
{
    $account = LinkedInAccount::current();

    $publisher->publish($account, $account->member_urn, 'Text with a link https://example.com');
}
```

## Configuration

All keys live in `config/linkedin.php`. The important ones:

| Key | Description |
| --- | --- |
| `organization_urn` | Company page URN; empty = profile only |
| `api_version` | LinkedIn API version (YYYYMM) |
| `routes.enabled` | Turn the built-in connect/callback routes on/off |
| `routes.prefix` / `routes.middleware` | Prefix and middleware of those routes |
| `routes.callback_name` | Route name used for the `redirect_uri` (when using your own routes) |
| `routes.redirect_to` | Route name to redirect back to after the flow |
| `table` | Name of the accounts table |

## Testing

```bash
composer install
composer test
```

## License

MIT © Arvid de Jong

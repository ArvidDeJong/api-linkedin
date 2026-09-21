---
title: "Company pages"
description: "List the LinkedIn company pages the connected member administers, let a user pick one per post, and understand the r_organization_admin scope and the cache."
nav_order: 6
---

# Company pages

Do you administer several company pages and want a user to pick one? Ask LinkedIn which pages the connected member administers. It takes three steps, in this order:

1. Make sure your LinkedIn app has the **Community Management API** product.
2. Turn the setting on, then run `php artisan config:clear` when your config is cached:

   ```dotenv
   LINKEDIN_ORGANIZATIONS_ENABLED=true
   ```

3. Connect the account again. The setting only changes which scopes the **next** connect asks for.

Then list the pages:

```php
use Darvis\ApiLinkedin\Facades\LinkedIn;

LinkedIn::organizations();
// [
//   ['urn' => 'urn:li:organization:42', 'id' => '42', 'name' => 'Acme BV', 'vanity_name' => 'acme'],
//   ['urn' => 'urn:li:organization:99', 'id' => '99', 'name' => 'Acme Labs', 'vanity_name' => 'acme-labs'],
// ]

LinkedIn::organizations(fresh: true); // bypass the cache
LinkedIn::forgetOrganizations();      // drop the cached list
```

Each entry has the `urn` to post with, the numeric `id`, the `name` and the `vanity_name` (the name in the address of the page, or `null`). This is also how you find the value for `LINKEDIN_ORGANIZATION_URN`.

Combine it with `LinkedIn::postAsOrganization($text, $urn)` to let a user choose a target per post, and offer the choice only when `LinkedIn::canListOrganizations()` is true.

## What the setting does, and what it does not

`organizations.enabled` does two things: it adds `w_organization_social` and `r_organization_admin` to the scopes a connect asks for, and it is one of the two conditions of `LinkedIn::canListOrganizations()` (the other is that the token carries `r_organization_admin`).

`LinkedIn::organizations()` itself does **not** look at the setting. It looks at the token only: with `r_organization_admin` it lists the pages, also while the setting is off. Gate your interface on `canListOrganizations()`, not on whether `organizations()` throws.

## The scope, and why listing is off by default

Listing requires the `r_organization_admin` scope, which needs the **Community Management API** product on your LinkedIn app. The scope is only requested while `organizations.enabled` is on, because LinkedIn refuses the entire authorization over a single unauthorized scope: were it always requested, an app without that product could not connect at all.

Turning the setting on **after** connecting does not upgrade the stored token. When the stored scopes show that `r_organization_admin` is missing, `organizations()` throws `LinkedInScopeMissing` before any request goes out, and keeps doing so until the account is reconnected. A token never gains scopes, so don't retry; reconnect.

A connection stored before version 1.4 has no recorded scopes. The package then cannot tell, sends the request, and a missing scope comes back as a `LinkedInApiException` with status 403.

Without a connected account `organizations()` throws `LinkedInNotConnected`.

## Caching

The list is cached per account in your application's default cache store for `organizations.cache_ttl` seconds, one hour by default. Set it to `0` to disable the cache, pass `fresh: true` for one uncached call, or call `forgetOrganizations()` after anything that changes page membership. Connecting the account again and `LinkedIn::disconnect()` both drop the cached list themselves.

When LinkedIn leaves the organization details out of the response, `name` is the URN, `id` is the last part of the URN and `vanity_name` is `null`, so the list is always usable.

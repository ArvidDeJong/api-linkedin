---
title: Company pages
description: "List the LinkedIn company pages the connected member administers, let a user pick one per post, and understand the r_organization_admin scope and the cache."
nav_order: 5
---

# Company pages

Do you administer several company pages and want a user to pick one? Turn the listing on and ask LinkedIn which pages the connected member administers.

```dotenv
LINKEDIN_ORGANIZATIONS_ENABLED=true
```

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

Combine it with `LinkedIn::postAsOrganization($text, $urn)` to let a user choose a target per post, and offer the choice only when `LinkedIn::canListOrganizations()` is true.

## The scope, and why listing is off by default

Listing requires the `r_organization_admin` scope, which needs the **Community Management API** product on your LinkedIn app. The scope is only requested while `organizations.enabled` is on, because LinkedIn refuses the entire authorization over a single unauthorized scope: were it always requested, an app that only holds Share on LinkedIn could not connect at all.

Turning the setting on **after** connecting does not upgrade the stored token. `organizations()` then throws `LinkedInScopeMissing` before any request goes out, and keeps doing so until the account is reconnected. A token never gains scopes, so don't retry; reconnect.

## Caching

The list is cached per account for `organizations.cache_ttl` seconds, one hour by default; company pages rarely change. Set it to `0` to disable the cache, pass `fresh: true` for one uncached call, or call `forgetOrganizations()` after anything that changes page membership. Connecting the account again and `LinkedIn::disconnect()` both drop the cached list themselves.

When LinkedIn leaves the organization details out of the response, the bare URN is used as the name, so the list is always usable.

---
title: Home
nav_order: 1
description: "darvis/api-linkedin for Laravel: publish posts on a LinkedIn profile or company page through OAuth 2.0 and the Posts API, with encrypted token storage and automatic refresh."
permalink: /
---

# darvis/api-linkedin

Publish posts on **LinkedIn** from a Laravel application, on the connected member's personal profile or on a company page, through OAuth 2.0 and the LinkedIn Posts API. No SDK, no Socialite provider, no Guzzle wrapper: everything goes through Laravel's built-in HTTP client.

This is an independent open-source package, not affiliated with LinkedIn.

```bash
composer require darvis/api-linkedin
php artisan migrate
```

Requires PHP 8.2+ and Laravel 11, 12 or 13, and a LinkedIn app with the **Share on LinkedIn** product. Company pages also need the **Community Management API**.

## Features

- **OAuth 2.0** authorization code flow with ready-made connect and callback routes, or wire the flow yourself
- **Automatic token refresh**, with the tokens stored encrypted in a `linkedin_accounts` table
- **Post as a member** (`w_member_social`) or **as a company page** (`w_organization_social`)
- **List the company pages** the member administers and pick one per post
- **Article cards with your own image**: upload a thumbnail and keep the whole card clickable
- **Knows what LinkedIn granted**: an app without the Community Management API still connects, on the profile
- **Typed exceptions**, so your code branches on the type instead of on LinkedIn's message text
- **Laravel Boost guideline** shipped in the package, so AI tooling in your app knows the rules

## Quick example

```php
use Darvis\ApiLinkedin\Facades\LinkedIn;

// On the connected member's profile
LinkedIn::postAsMember("New blog article!\n\nhttps://example.com/blog/my-article");

// On the default company page (linkedin.organization_urn)
LinkedIn::postAsOrganization('Company news with a link https://example.com');

// Both return ['urn' => 'urn:li:share:…', 'permalink' => 'https://www.linkedin.com/feed/update/…']
```

## Read next

- [Installation & configuration](installation.md): the LinkedIn app, environment variables and every config key
- [Connecting](connecting.md): the OAuth flow, apps without the Community Management API, and what the connection may do
- [Publishing](publishing.md): posts, article cards and image uploads
- [Company pages](company-pages.md): listing the pages a member administers
- [Error handling](errors.md): the exception types and what to do with each
- [FAQ](faq.md)

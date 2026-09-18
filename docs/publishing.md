---
title: Publishing
description: "Publish LinkedIn posts from Laravel: on the member's profile or a company page, with an article card and your own thumbnail image, through the facade or the services."
nav_order: 4
---

# Publishing

```php
use Darvis\ApiLinkedin\Facades\LinkedIn;

// On the connected member's profile
LinkedIn::postAsMember("New blog article!\n\nhttps://example.com/blog/my-article");

// On the default company page (linkedin.organization_urn)
LinkedIn::postAsOrganization('Company news with a link https://example.com');

// On a specific company page
LinkedIn::postAsOrganization('Company news', 'urn:li:organization:1234567');

// Any author URN
LinkedIn::publish('urn:li:organization:1234567', 'Company news');
```

All of these return `['urn' => '...', 'permalink' => '...']`. Put a URL in the text and LinkedIn builds the link preview itself from the Open Graph tags of that page.

Run the publishing in a queued job, so a slow or failing LinkedIn call never blocks a request.

## What the text may contain

The Posts API reserves a set of characters in the commentary: `| { } @ [ ] ( ) < > # * _ ~` and the backslash. The package escapes them for you, so write plain text and don't escape anything yourself.

## Posting as a company page

There is no separate token for a company page. `postAsOrganization()` uses the access token of the connected member and only sets a different author, so it works when the token carries `w_organization_social` and the member administers the page. When the token provably lacks the scope, the package throws `LinkedInScopeMissing` before any request goes out; see [Connecting](connecting.md#the-config-asks-the-token-decides).

## An article card with your own image

Letting LinkedIn crawl your Open Graph tags works, but only if LinkedIn can reach the page, the result is cached per URL, and you have no say over the image. Attach an `Article` instead: you supply the thumbnail, and the whole card stays clickable through to your site. That is different from an image post, where the link survives only as plain text in the commentary.

```php
use Darvis\ApiLinkedin\Article;
use Darvis\ApiLinkedin\Facades\LinkedIn;

$author = LinkedIn::account()->member_urn;

$article = Article::to('https://example.com/blog/my-article')
    ->withTitle('My article')
    ->withDescription('A short summary.')
    ->withThumbnail(LinkedIn::uploadImage($author, file_get_contents($path), 'image/png'));

LinkedIn::postAsMember('New blog article!', $article);
```

Two things that bite if you skip them:

- **The image owner must be the author of the post.** `uploadImage()` takes the owner URN for that reason: posting as a company page means uploading as that company page. Posting the same article on a profile and two company pages means three uploads, one per author; LinkedIn rejects an image URN reused across authors.
- **Uploading needs no extra scope.** It rides on the same `w_member_social` or `w_organization_social` the post itself needs, so adding image support never forces a reconnect.

The thumbnail is optional: an `Article` without one still renders a clickable card and LinkedIn falls back to crawling the page for an image. Omit the `Article` entirely and the payload is exactly the pre-1.5 behaviour.

`uploadImage()` takes the raw bytes and a content type, not a path. The package does no filesystem work, so the image may come from disk, S3 or anywhere else.

## Through the services

Everything the facade does is available on the services, for dependency injection:

```php
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;

public function share(LinkedInPublisher $publisher): void
{
    $account = LinkedInAccount::current();

    $publisher->publish($account, $account->member_urn, 'Text with a link https://example.com');
}
```

`LinkedInManager` (bound as `linkedin` in the container) is the front the facade talks to. Behind it sit `LinkedInOAuth` (authorization URL, code exchange, refresh), `LinkedInPublisher` (posts), `LinkedInImages` (uploads) and `LinkedInOrganizations` (company pages).

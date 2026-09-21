---
title: "Publishing"
description: "Publish LinkedIn posts from Laravel: on the member's profile or a company page, what the result holds, reserved characters, and an article card with your image."
nav_order: 5
---

# Publishing

Publishing needs a connected account; see [Connecting](connecting.md). Every method below uses the one connection the application has.

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

All of these return an array with two keys:

| Key | Value |
| --- | --- |
| `urn` | The id LinkedIn gave the post, read from the `x-restli-id` response header, for example `urn:li:share:123` |
| `permalink` | `https://www.linkedin.com/feed/update/<urn>/` |

The three methods take an optional [`Article`](#an-article-card-with-your-own-image) as their last argument. Without one the package sends the text only, and a preview of an address in the text is LinkedIn's own work.

Run the publishing in a queued job, so a slow or failing LinkedIn call does not block a web request. The [quick start](quickstart.md#3-publish-from-a-queued-job) has a complete job.

## An empty URN is not an error

When LinkedIn answers with a success status but without an `x-restli-id` header and without an `id` in the body, the package returns `['urn' => '', 'permalink' => '']` and throws nothing. Check for an empty `urn` before you store the permalink.

## Publishing twice posts twice

The package has no duplicate check. A job that is retried after the post went out sends it again, so store the URN before anything else in the job can fail.

## What the text may contain

The Posts API reserves a set of characters in the text of a post (the `commentary`): `| { } @ [ ] ( ) < > # * _ ~` and the backslash; see [LinkedIn's little text format](https://learn.microsoft.com/linkedin/marketing/community-management/shares/little-text-format). The package escapes all of them, every time, so write plain text and don't escape anything yourself.

The consequence: mention syntax such as `@[Acme](urn:li:organization:1)` and `#hashtag` arrive as literal text, not as a mention or a clickable hashtag element.

## Posting as a company page

There is no separate token for a company page. `postAsOrganization()` uses the access token of the connected member and only sets a different author. LinkedIn accepts that when the token carries `w_organization_social` and the member administers the page.

- Without a URN argument, `postAsOrganization()` uses `LINKEDIN_ORGANIZATION_URN`. With neither it throws `LinkedInConfigurationException`.
- When the stored scopes show that the token lacks `w_organization_social`, the package throws `LinkedInScopeMissing` before any request goes out; see [The config asks, the token decides](connecting.md#the-config-asks-the-token-decides).
- The same guard applies to `publish()` and `uploadImage()` with a URN that starts with `urn:li:organization:`.

Offer the company page in your interface only when `LinkedIn::canPostAsOrganization()` is true.

## An article card with your own image

Without an article card you have no say over the preview LinkedIn makes of an address in the text. Attach an `Article` to send the card yourself: the address, a title, a description and a thumbnail you uploaded.

```php
use Darvis\ApiLinkedin\Article;
use Darvis\ApiLinkedin\Facades\LinkedIn;

use Illuminate\Support\Facades\Storage;

// The image must be owned by the author of the post: here the member.
$author = LinkedIn::account()->member_urn;

// Returns the URN of the image, in the form urn:li:image:...
$image = LinkedIn::uploadImage($author, Storage::get('og/my-article.png'), 'image/png');

$article = Article::to('https://example.com/blog/my-article')
    ->withTitle('My article')
    ->withDescription('A short summary.')
    ->withThumbnail($image);

LinkedIn::postAsMember('New blog article!', $article);
```

`uploadImage($ownerUrn, $contents, $contentType)` makes two calls to LinkedIn and returns the URN of the image. `Article::to()` and the `with…()` methods each return a new `Article`; the object is immutable. `LinkedIn::account()` is `null` when nothing is connected, so check `LinkedIn::isConnected()` first in code that may run before a connect.

Two things that bite if you skip them:

- **The image owner must be the author of the post.** `uploadImage()` takes the owner URN for that reason: posting as a company page means uploading with the URN of that company page. Posting the same article on a profile and two company pages means three uploads, one per author.
- **Uploading needs no extra scope.** It rides on the same `w_member_social` or `w_organization_social` the post itself needs, so adding image support never forces a reconnect.

The title, the description and the thumbnail are optional. Empty values are left out of the request.

`uploadImage()` takes the raw bytes and a content type (`application/octet-stream` when you leave it out), not a path. The package does no filesystem work, so the image may come from a disk, S3 or anywhere else.

## Through the services

Everything the facade does is available on the services, for dependency injection:

```php
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;

public function share(LinkedInPublisher $publisher): void
{
    $account = LinkedInAccount::current(); // null when nothing is connected

    if ($account === null) {
        return;
    }

    $publisher->publish($account, $account->member_urn, 'Text with a link https://example.com');
}
```

The services take the account as an argument and do not check whether one is connected; the facade does that and throws `LinkedInNotConnected`.

`LinkedInManager` (bound as `linkedin` in the container) is the front the facade talks to. Behind it sit `LinkedInOAuth` (authorization URL, code exchange, refresh), `LinkedInPublisher` (posts), `LinkedInImages` (uploads) and `LinkedInOrganizations` (company pages).

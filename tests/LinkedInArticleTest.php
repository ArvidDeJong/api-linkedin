<?php

use Darvis\ApiLinkedin\Article;
use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInScopeMissing;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Darvis\ApiLinkedin\Services\LinkedInImages;
use Illuminate\Support\Facades\Http;

const UPLOAD_URL = 'https://www.linkedin.com/dms-uploads/abc';

/**
 * Fake the two calls an upload takes: initializeUpload hands out a single-use URL,
 * the bytes are then PUT to that URL.
 */
function fakeImageUpload(): void
{
    Http::fake([
        'https://api.linkedin.com/rest/images?action=initializeUpload' => Http::response([
            'value' => ['uploadUrl' => UPLOAD_URL, 'image' => 'urn:li:image:C4E123'],
        ]),
        UPLOAD_URL => Http::response(null, 201),
        'https://api.linkedin.com/rest/posts' => Http::response(null, 201, ['x-restli-id' => 'urn:li:share:1']),
    ]);
}

it('uploads an image and returns its urn', function () {
    fakeImageUpload();
    $account = account(['scopes' => 'openid profile w_member_social']);

    $urn = app(LinkedInImages::class)->upload($account, 'urn:li:person:1', 'the-bytes', 'image/png');

    expect($urn)->toBe('urn:li:image:C4E123');

    // The bytes go to the signed URL, not to the REST API — and without a
    // LinkedIn-Version header, which that host rejects.
    Http::assertSent(fn ($request) => $request->url() === UPLOAD_URL
        && $request->method() === 'PUT'
        && $request->body() === 'the-bytes'
        && ! $request->hasHeader('LinkedIn-Version'));
});

it('uploads the image as the author that will publish it', function () {
    fakeImageUpload();
    account(['scopes' => 'openid profile w_member_social w_organization_social']);

    LinkedIn::uploadImage('urn:li:organization:42', 'bytes');

    // LinkedIn refuses an image owned by anyone other than the post's author.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'initializeUpload')
        && $request['initializeUploadRequest']['owner'] === 'urn:li:organization:42');
});

it('refuses to upload as a company page without the scope', function () {
    Http::fake();

    app(LinkedInImages::class)->upload(
        account(['scopes' => 'openid profile w_member_social']),
        'urn:li:organization:42',
        'bytes',
    );

    // Without this guard an upload would still go out for an author the token
    // cannot publish as — exactly the request the publisher's guard prevents.
    Http::assertNothingSent();
})->throws(LinkedInScopeMissing::class);

it('throws when LinkedIn returns no upload url', function () {
    Http::fake([
        'https://api.linkedin.com/rest/images*' => Http::response(['value' => []]),
    ]);

    app(LinkedInImages::class)->upload(account(), 'urn:li:person:1', 'bytes');
})->throws(LinkedInApiException::class);

it('attaches an article card with the uploaded thumbnail', function () {
    fakeImageUpload();
    account(['scopes' => 'openid profile w_member_social']);

    $article = Article::to('https://example.com/blog/my-article')
        ->withTitle('My article')
        ->withDescription('A short summary.')
        ->withThumbnail(LinkedIn::uploadImage('urn:li:person:1', 'bytes'));

    LinkedIn::postAsMember('Accompanying text', $article);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.linkedin.com/rest/posts'
        && $request['content']['article'] === [
            'source' => 'https://example.com/blog/my-article',
            'title' => 'My article',
            'description' => 'A short summary.',
            'thumbnail' => 'urn:li:image:C4E123',
        ]);
});

it('drops empty fields, because LinkedIn rejects a blank thumbnail', function () {
    $article = Article::to('https://example.com')->withTitle('Title')->withThumbnail(null);

    expect($article->toArray())->toBe([
        'source' => 'https://example.com',
        'title' => 'Title',
    ]);
});

it('posts without any content when no article is given', function () {
    fakeImageUpload();
    account(['scopes' => 'openid profile w_member_social']);

    LinkedIn::postAsMember('Just text with https://example.com in it');

    // No `content` key at all: LinkedIn then builds its own preview from the URL's
    // Open Graph tags, which is the pre-1.5 behaviour.
    Http::assertSent(fn ($request) => $request->url() === 'https://api.linkedin.com/rest/posts'
        && ! array_key_exists('content', $request->data()));
});

it('keeps the article immutable while building it', function () {
    $base = Article::to('https://example.com');
    $withTitle = $base->withTitle('Title');

    expect($base->title)->toBeNull()
        ->and($withTitle->title)->toBe('Title')
        ->and($withTitle->source)->toBe('https://example.com');
});

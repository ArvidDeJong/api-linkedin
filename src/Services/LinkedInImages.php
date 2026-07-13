<?php

namespace Darvis\ApiLinkedin\Services;

use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Exceptions\LinkedInScopeMissing;
use Darvis\ApiLinkedin\Models\LinkedInAccount;
use Darvis\ApiLinkedin\Scopes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Uploads an image to LinkedIn and returns its URN, for use as the thumbnail of
 * an {@see \Darvis\ApiLinkedin\Article} or as the body of an image post.
 *
 * Uploading is two calls, not one: `initializeUpload` hands out a short-lived,
 * single-use URL, and the bytes are then PUT to that URL. That second call goes
 * to LinkedIn's upload host, not to the REST API — it takes no `LinkedIn-Version`
 * header, and sending one makes it fail.
 *
 * No new scope is needed: uploading rides on the same `w_member_social` /
 * `w_organization_social` the post itself needs. The `owner` must be the exact
 * author the post will be published as, or LinkedIn rejects the image.
 */
class LinkedInImages
{
    private const IMAGES_URL = 'https://api.linkedin.com/rest/images';

    public function __construct(private LinkedInOAuth $oauth) {}

    /**
     * Upload the raw bytes of an image and return its `urn:li:image:...`.
     *
     * The package deliberately does no filesystem work: hand it the contents, so
     * the bytes may come from disk, S3, or anywhere else.
     *
     * @throws LinkedInException on an expired connection or an API error.
     */
    public function upload(
        LinkedInAccount $account,
        string $ownerUrn,
        string $contents,
        string $contentType = 'application/octet-stream',
    ): string {
        // Owning an image as a company page needs the same scope as posting as one.
        // Guard here too, or an upload would still go out for an author the token
        // cannot publish as — the request the publisher's guard exists to prevent.
        if (Str::startsWith($ownerUrn, 'urn:li:organization:') && $account->lacksScope(Scopes::POST_AS_ORGANIZATION)) {
            throw new LinkedInScopeMissing(Scopes::POST_AS_ORGANIZATION);
        }

        $token = $this->oauth->freshAccessToken($account);

        $initialized = Http::withToken($token)
            ->withHeaders([
                'LinkedIn-Version' => (string) config('linkedin.api_version'),
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->post(self::IMAGES_URL.'?action=initializeUpload', [
                'initializeUploadRequest' => ['owner' => $ownerUrn],
            ]);

        if ($initialized->failed()) {
            throw LinkedInApiException::from(
                LinkedInApiException::OPERATION_IMAGE,
                $initialized,
                'Could not initialize the LinkedIn image upload',
            );
        }

        $uploadUrl = (string) $initialized->json('value.uploadUrl', '');
        $urn = (string) $initialized->json('value.image', '');

        if ($uploadUrl === '' || $urn === '') {
            throw new LinkedInApiException(
                operation: LinkedInApiException::OPERATION_IMAGE,
                status: $initialized->status(),
                body: $initialized->body(),
                message: 'LinkedIn returned no upload URL for the image',
            );
        }

        $uploaded = Http::withToken($token)
            ->withBody($contents, $contentType)
            ->put($uploadUrl);

        if ($uploaded->failed()) {
            throw LinkedInApiException::from(
                LinkedInApiException::OPERATION_IMAGE,
                $uploaded,
                'LinkedIn refused the image upload',
            );
        }

        return $urn;
    }
}

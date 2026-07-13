<?php

namespace Darvis\ApiLinkedin;

/**
 * A link card attached to a post: the article LinkedIn should show, with a
 * thumbnail you supply yourself.
 *
 * Without this, a URL in the commentary makes LinkedIn crawl the page and build
 * a preview from its Open Graph tags — which only works if LinkedIn can reach the
 * page, and gives you no say over the image. With it, the card is yours: the whole
 * thing stays clickable, and the thumbnail is the one you uploaded.
 *
 * The thumbnail is an image URN, not a path — upload the bytes first with
 * {@see LinkedInManager::uploadImage()}, whose owner must be the same author the
 * post is published as.
 */
final class Article
{
    private function __construct(
        public readonly string $source,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $thumbnail = null,
    ) {}

    /**
     * A card pointing at $url.
     */
    public static function to(string $url): self
    {
        return new self($url);
    }

    public function withTitle(?string $title): self
    {
        return new self($this->source, $title, $this->description, $this->thumbnail);
    }

    public function withDescription(?string $description): self
    {
        return new self($this->source, $this->title, $description, $this->thumbnail);
    }

    /**
     * @param  string|null  $imageUrn  A `urn:li:image:...` from {@see LinkedInManager::uploadImage()}.
     */
    public function withThumbnail(?string $imageUrn): self
    {
        return new self($this->source, $this->title, $this->description, $imageUrn);
    }

    /**
     * The `content.article` payload. Empty fields are dropped: LinkedIn rejects a
     * thumbnail that is present but blank.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'source' => $this->source,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
        ], static fn (?string $value): bool => filled($value));
    }
}

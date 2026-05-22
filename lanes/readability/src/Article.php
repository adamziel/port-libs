<?php

declare(strict_types=1);

namespace PortLibs\Readability;

final class Article
{
    public function __construct(
        public readonly string $title,
        public readonly string $contentHtml,
        public readonly string $text,
        public readonly string $excerpt,
        public readonly ?string $byline = null,
        public readonly ?string $siteName = null,
        public readonly ?string $publishedTime = null,
        public readonly ?string $dir = null,
        public readonly ?string $lang = null,
    ) {
    }
}

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
    ) {
    }
}


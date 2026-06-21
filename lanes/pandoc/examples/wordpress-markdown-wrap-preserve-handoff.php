<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$markdown = <<<'MARKDOWN'
# Preserve Reviewer Lines

Source archive note line one
editor follow-up remains the same paragraph
with source wrapping preserved for review.

Publish handoff keeps nonsemantic source breaks visible.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);

echo (new MarkdownWriter([
    'setextHeadings' => true,
    'wrap' => 'wrap-preserve',
]))->write($document) . "\n";

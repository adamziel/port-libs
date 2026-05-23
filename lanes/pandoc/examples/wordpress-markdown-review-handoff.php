<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$markdown = <<<'MARKDOWN'
# Import Review

Migration source note.^[Keep the original archive link with the reviewer handoff.]

Reviewer source says [audit log](https://example.test/wp-admin/post.php?post=42&action=edit) before publish.

> Block quote note.^[Quoted source needs editorial confirmation.]
>
> Keep the quoted source grouped with its note.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);

echo (new MarkdownWriter([
    'referenceLinks' => true,
    'referenceLocation' => 'end_of_block',
    'setextHeadings' => true,
]))->write($document) . "\n";

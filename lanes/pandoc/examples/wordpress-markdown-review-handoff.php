<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$markdown = <<<'MARKDOWN'
# Import Review

Migration source note.^[Keep the original archive link with the reviewer handoff.]

Reviewer source says [audit log](https://example.test/wp-admin/post.php?post=42&action=edit) before publish.

Adjacent source echoes: [source](https://example.test/source-a)[source](https://example.test/source-b) [source](https://example.test/source-c) [review brackets].

Citation-adjacent handoff: [citation source](https://example.test/citation)[@migration-audit]

> Block quote note.^[Quoted source needs editorial confirmation.]
>
> Keep the quoted source grouped with its note.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$document = new AstNode('document', $document->attrs, [
    ...$document->children,
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer packet links: ']),
        new AstNode('link', [
            'url' => 'https://example.test/review-packet',
            'classes' => ['uri'],
        ], [
            new AstNode('text', ['text' => 'https://example.test/review-packet']),
        ]),
        new AstNode('text', ['text' => ' and ']),
        new AstNode('link', [
            'url' => 'mailto:editor@example.test',
            'classes' => ['email'],
        ], [
            new AstNode('text', ['text' => 'editor@example.test']),
        ]),
        new AstNode('text', ['text' => ' plus ']),
        new AstNode('link', [
            'url' => 'https://example.test/review-packet',
            'title' => 'Review packet',
            'id' => 'review-packet',
            'classes' => ['source-link'],
            'attributes' => ['data-source' => 'batch-42'],
        ], [
            new AstNode('text', ['text' => 'packet']),
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer media preview: ']),
        new AstNode('image', [
            'url' => 'https://example.test/uploads/review-screenshot.jpg',
            'title' => 'Review screenshot',
            'alt' => 'Screenshot alt text',
            'id' => 'review-screenshot',
            'classes' => ['source-image'],
            'attributes' => ['data-source' => 'batch-42'],
        ], [
            new AstNode('text', ['text' => 'Review screenshot']),
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => '# Literal audit tokens: * _ ` | ^ ~ $ <review> &ouml; \\macro']),
    ]),
]);

echo (new MarkdownWriter([
    'referenceLinks' => true,
    'referenceLocation' => 'end_of_block',
    'setextHeadings' => true,
]))->write($document) . "\n";

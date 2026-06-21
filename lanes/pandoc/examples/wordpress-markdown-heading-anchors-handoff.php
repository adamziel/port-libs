<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$imported = (new MarkdownReader())->read("# Imported Review\n\n# Imported Review");

$document = new AstNode('document', [], [
    new AstNode('heading', [
        'level' => 1,
        'id' => 'wp-review-packet',
        'classes' => ['wp-import-review'],
        'attributes' => ['source' => 'batch-42'],
    ], [$text('WordPress Review Packet')]),
    new AstNode('paragraph', [], [
        $text('Custom anchors stay attached to reviewer packets for intra-document audit links.'),
    ]),
    ...$imported->children,
    new AstNode('heading', [
        'level' => 2,
        'id' => 'manual-media-review',
    ], [$text('Media Follow-up')]),
]);

echo (new MarkdownWriter(['setextHeadings' => true]))->write($document) . "\n";

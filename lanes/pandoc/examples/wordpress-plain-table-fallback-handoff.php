<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

$caption = [
    new AstNode('strong', [], [$text('Migration')]),
    $text(' '),
    new AstNode('link', [
        'url' => '/wp-admin/post.php?post=42&action=edit',
        'title' => 'Edit imported post',
    ], [$text('source edit')]),
    $text(' for '),
    new AstNode('code', ['text' => 'wp_posts']),
];

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Plain-text import audit packet for a table shape that cannot be represented faithfully.'),
    ]),
    new AstNode('table', [
        'captionInlines' => $caption,
        'classes' => ['wp-review'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['left', 'right'],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell([$text('Section')], ['header' => true]),
                $cell([$text('Count')], ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell([
                    new AstNode('strong', [], [$text('All imports')]),
                    $text(' via '),
                    new AstNode('link', [
                        'url' => '/wp-admin/post.php?post=42&action=edit',
                    ], [$text('edit link')]),
                ], ['colspan' => 2, 'align' => 'center']),
            ]),
            $row([
                $cell([$text('Posts')]),
                $cell([
                    new AstNode('code', ['text' => '42']),
                    $text(' ready'),
                ]),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'plain',
    'gridTables' => false,
    'pipeTables' => false,
    'rawHtml' => false,
    'columns' => 96,
]))->write($document) . "\n";

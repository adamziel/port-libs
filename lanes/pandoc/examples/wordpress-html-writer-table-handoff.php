<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
$row = static fn (array $cells, array $attrs = []): AstNode => new AstNode('table_row', $attrs, $cells);

$table = new AstNode('table', [
    'id' => 'migration-table',
    'classes' => ['audit-table'],
    'attributes' => ['data-source' => 'batch-42'],
    'captionInlines' => [
        new AstNode('strong', [], [$text('Migration')]),
        $text(' '),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
            'title' => 'Edit imported post',
        ], [$text('source audit')]),
    ],
    'widths' => [0.25, 0.35, 0.0],
    'alignments' => ['left', 'right', 'default'],
], [
    new AstNode('table_head', ['classes' => ['source-head']], [
        $row([
            $cell([$text('Field')]),
            $cell([$text('Review status')], ['colspan' => 2, 'align' => 'center']),
        ]),
    ]),
    new AstNode('table_body', [
        'htmlAttributes' => ['data-phase' => 'import'],
        'rowHeadColumns' => 1,
    ], [
        $row([
            $cell([$text('Posts')], ['attributes' => ['scope' => 'row']]),
            $cell([$text('42')]),
            $cell([new AstNode('paragraph', [], [$text('Needs <review>')])], ['rowspan' => 2]),
        ], ['classes' => ['flagged']]),
        $row([
            $cell([$text('Media')], ['attributes' => ['scope' => 'row']]),
            $cell([new AstNode('code', ['text' => 'ready()'])]),
        ]),
    ]),
    new AstNode('table_foot', [], [
        $row([
            $cell([$text('Ready for block import')], ['colspan' => 3]),
        ]),
    ]),
]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [$text('HTML table preview before WordPress import:')]),
    $table,
]);

echo "HTML preview:\n";
echo (new HtmlWriter())->write($document) . "\n\n";

echo "WordPress blocks:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

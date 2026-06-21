<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$cell = static function (string $value, array $attrs = []) use ($text): AstNode {
    return new AstNode(
        'table_cell',
        ['text' => $value] + $attrs,
        [$text($value)]
    );
};
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Narrow Markdown reviewer handoff for a multilingual WordPress import queue.'),
    ]),
    new AstNode('table', [
        'caption' => 'Default-width label plus weighted review columns',
        'classes' => ['wp-review-pipe-widths'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['default', 'right', 'left'],
        'widths' => [0.0, 0.25, 0.75],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell('項目詳細', ['header' => true]),
                $cell('Items', ['header' => true]),
                $cell('Reviewer note', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell('画像'),
                $cell('42'),
                $cell('Long source notes intentionally exceed the narrow review column.'),
            ]),
            $row([
                $cell('投稿者'),
                $cell('7'),
                $cell('Confirm author slugs before publishing imported posts.'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter([
    'columns' => 40,
    'multilineTables' => false,
]))->write($document) . "\n";

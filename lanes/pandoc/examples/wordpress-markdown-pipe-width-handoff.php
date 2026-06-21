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
        $text('Markdown reviewer handoff for a narrow WordPress import queue.'),
    ]),
    new AstNode('table', [
        'caption' => 'Narrow pipe table for migration review',
        'classes' => ['wp-review-pipe'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['left', 'right', 'default'],
        'widths' => [0.18, 0.17, 0.65],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell('Import area', ['header' => true]),
                $cell('Items', ['header' => true]),
                $cell('Reviewer note', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell('Posts'),
                $cell('42'),
                $cell('Long source notes intentionally exceed the narrow review column.'),
            ]),
            $row([
                $cell('Media'),
                $cell('7'),
                $cell('Captions and alt text need one more editorial pass.'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter([
    'columns' => 40,
    'multilineTables' => false,
]))->write($document) . "\n";

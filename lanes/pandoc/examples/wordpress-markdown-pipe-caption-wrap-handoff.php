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
        $text('Narrow Markdown reviewer handoff for WordPress import captions.'),
    ]),
    new AstNode('table', [
        'caption' => 'Migration reviewer captions wrap before publishing WordPress imports',
        'classes' => ['wp-review-caption-wrap'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['left', 'default'],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell('Source', ['header' => true]),
                $cell('Review note', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell('Posts'),
                $cell('Confirm captions before publish.'),
            ]),
            $row([
                $cell('Media'),
                $cell('Keep imported image captions attached.'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter([
    'columns' => 36,
    'simpleTables' => false,
    'multilineTables' => false,
]))->write($document) . "\n";

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
        $text('CommonMark-flavored reviewer handoff for WordPress import tables.'),
    ]),
    new AstNode('table', [
        'caption' => 'CommonMark reviewer captions stay visible without Pandoc colon markers',
        'classes' => ['wp-review-commonmark'],
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
                $cell('Keep captions visible for editors.'),
            ]),
            $row([
                $cell('Media'),
                $cell('Confirm image captions before publish.'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'commonmark',
    'simpleTables' => false,
    'multilineTables' => false,
    'columns' => 44,
]))->write($document) . "\n";

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
        $text('Markdown reviewer handoff for widthless WordPress import totals.'),
    ]),
    new AstNode('table', [
        'caption' => 'Widthless source totals for Data Liberation review',
        'classes' => ['wp-review-simple'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['right', 'left', 'center', 'default'],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell('Ready', ['header' => true]),
                $cell('Entity', ['header' => true]),
                $cell('Owner', ['header' => true]),
                $cell('Reviewer note', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell('42'),
                $cell('Posts'),
                $cell('editorial'),
                $cell('block conversion complete'),
            ]),
            $row([
                $cell('7'),
                $cell('Media'),
                $cell('media'),
                $cell('needs alt text review'),
            ]),
            $row([
                $cell('3'),
                $cell('Links'),
                $cell('ops'),
                $cell('redirect map pending'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter())->write($document) . "\n";

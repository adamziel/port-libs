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
        $text('Markdown reviewer handoff for WordPress Data Liberation batch 42.'),
    ]),
    new AstNode('table', [
        'caption' => "Wrapped source notes\nKeep width hints",
        'classes' => ['wp-review-multiline'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['center', 'left', 'right', 'left'],
        'widths' => [0.15, 0.1375, 0.1625, 0.35],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell("Import\nArea", ['header' => true]),
                $cell("Source\nState", ['header' => true]),
                $cell("Items\nReady", ['header' => true]),
                $cell('Reviewer note', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell('Posts'),
                $cell('parsed'),
                $cell('42'),
                $cell("Long legacy excerpts stay wrapped\nwithout falling back to raw HTML."),
            ]),
            $row([
                $cell('Media'),
                $cell('queued'),
                $cell('7'),
                $cell("Confirm captions and alt text\nbefore publishing the import."),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter(['columns' => 80]))->write($document) . "\n";

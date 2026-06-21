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
        $text('Markdown reviewer handoff for multilingual WordPress import labels.'),
    ]),
    new AstNode('table', [
        'caption' => 'Unicode width reviewer handoff',
        'classes' => ['wp-review-width'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['left', 'left', 'left'],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell('項目詳細', ['header' => true]),
                $cell('German', ['header' => true]),
                $cell('Note', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell('画像'),
                $cell("Auf\u{200C}lage"),
                $cell('ready'),
            ]),
            $row([
                $cell('投稿者'),
                $cell("Permalink\u{200D}scan"),
                $cell('needs review'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter())->write($document) . "\n";

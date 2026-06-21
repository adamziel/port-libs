<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$cell = static fn (array $children, array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
$textCell = static function (string $value, array $attrs = []) use ($text): AstNode {
    return new AstNode(
        'table_cell',
        ['text' => $value] + $attrs,
        [$text($value)]
    );
};
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Reviewer handoff for WordPress Data Liberation batch 42.'),
    ]),
    new AstNode('table', [
        'caption' => 'Block-rich migration review queue',
        'classes' => ['wp-review-grid'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['left', 'right'],
        'widths' => [0.32, 0.68],
    ], [
        new AstNode('table_head', [], [
            $row([
                $textCell('Section', ['header' => true]),
                $textCell('Reviewer note', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell([
                    new AstNode('heading', ['level' => 3], [$text('Posts')]),
                    new AstNode('paragraph', [], [$text('Ready for import')]),
                    new AstNode('bullet_list', [], [
                        new AstNode('list_item', [], [$text('preserve captions')]),
                        new AstNode('list_item', [], [$text('check image alt text')]),
                    ]),
                ]),
                $cell([
                    $text('Needs final source review'),
                    new AstNode('linebreak'),
                    $text('before publish.'),
                ]),
            ]),
        ]),
        new AstNode('table_foot', [], [
            $row([
                $textCell('Total items'),
                $textCell('49'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter(['columns' => 64]))->write($document) . "\n";

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
        $text('No-wrap Markdown reviewer handoff for WordPress source audit notes.'),
    ]),
    new AstNode('table', [
        'caption' => 'WrapNone import review queue',
        'classes' => ['wp-review-nowrap'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['left', 'left'],
        'widths' => [0.1, 0.25],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell('Source token', ['header' => true]),
                $cell('Reviewer note', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell('wp_post_meta_supercalifragilisticexpialidocious_key'),
                $cell('Needs editorial review before import'),
            ]),
            $row([
                $cell('legacy_builder_section_identifier'),
                $cell('Preserve reviewer phrasing without inserting soft wraps'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter(['columns' => 36, 'wrap' => 'none']))->write($document) . "\n";

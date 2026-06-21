<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$cell = static function (string $value, array $attrs = []) use ($text): AstNode {
    return new AstNode('table_cell', ['text' => $value] + $attrs, [$text($value)]);
};
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Spanned Markdown grid table handoff for WordPress migration review.'),
    ]),
    new AstNode('table', [
        'caption' => 'Media remediation queue',
        'classes' => ['wp-review-grid', 'spanned'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['left', 'center', 'right'],
    ], [
        new AstNode('table_head', [], [
            $row([
                $cell('Area', ['header' => true, 'rowspan' => 2]),
                $cell('Remediation status', ['header' => true, 'colspan' => 2, 'align' => 'center']),
            ]),
            $row([
                $cell('Items', ['header' => true]),
                $cell('Owner', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $row([
                $cell('Media library', ['rowspan' => 2]),
                $cell('42 images'),
                $cell('Editorial'),
            ]),
            $row([
                $cell('7 captions'),
                $cell('Migration QA'),
            ]),
            $row([
                $cell('Final checkpoint', ['colspan' => 3, 'align' => 'center']),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter(['columns' => 72]))->write($document) . "\n";

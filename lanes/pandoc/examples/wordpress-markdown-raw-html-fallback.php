<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$tableCell = static function (string $textValue, array $attrs = []) use ($text): AstNode {
    return new AstNode('table_cell', ['text' => $textValue] + $attrs, [$text($textValue)]);
};
$tableRow = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Reviewer packet fallback: '),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
            'title' => 'Edit source packet',
            'id' => 'source-packet',
            'classes' => ['source-link'],
            'attributes' => ['source' => 'batch-42'],
        ], [
            $text('source '),
            new AstNode('strong', [], [$text('packet')]),
        ]),
        $text(', scoped span '),
        new AstNode('span', [
            'id' => 'review-scope',
            'classes' => ['source-span'],
            'attributes' => [
                'data-source' => 'batch-42',
                'title' => 'Review scope',
            ],
        ], [
            $text('needs '),
            new AstNode('emph', [], [$text('manual review')]),
        ]),
        $text(', styles '),
        new AstNode('underline', [], [$text('manual review')]),
        $text(' and '),
        new AstNode('small_caps', [], [$text('source glossary')]),
        $text(', deleted source '),
        new AstNode('strikeout', [], [$text('legacy caption')]),
        $text(', script fallbacks H'),
        new AstNode('subscript', [], [$text('2')]),
        $text(' and x'),
        new AstNode('superscript', [], [$text('2')]),
        $text(', quoted source '),
        new AstNode('quoted', ['kind' => 'single'], [$text('legacy reviewer quote')]),
        $text(' and '),
        new AstNode('quoted', ['kind' => 'double'], [$text('migration excerpt')]),
        $text(', non-ASCII source Résumé © ∈ 😀 and literal “curly excerpt”…'),
        $text(' and media '),
        new AstNode('image', [
            'url' => '/wp-content/uploads/review-frame.jpg',
            'title' => 'Review frame',
            'alt' => 'Editorial screenshot alt',
            'id' => 'review-frame',
            'classes' => ['source-image'],
            'attributes' => ['source' => 'batch-42'],
        ], [$text('Visible reviewer caption')]),
        $text('.'),
    ]),
    new AstNode('figure', [
        'id' => 'review-figure',
        'classes' => ['wp-import-review'],
        'attributes' => ['data-source' => 'batch-42'],
        'caption' => 'Reviewer fallback figure',
    ], [
        new AstNode('plain', [], [
            new AstNode('image', [
                'url' => '/wp-content/uploads/review-fallback.jpg',
                'title' => 'Review fallback frame',
            ], [$text('Reviewer fallback figure')]),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Reviewer fallback table',
        'classes' => ['source-review-table'],
        'attributes' => ['source' => 'batch-42'],
        'alignments' => ['left', 'right'],
        'widths' => [0.4, 0.6],
    ], [
        new AstNode('table_head', [], [
            $tableRow([
                $tableCell('Section', ['header' => true]),
                $tableCell('Count', ['header' => true]),
            ]),
        ]),
        new AstNode('table_body', [], [
            $tableRow([
                $tableCell('All imported records', [
                    'colspan' => 2,
                    'align' => 'center',
                ]),
            ]),
            $tableRow([
                $tableCell('Posts'),
                $tableCell('42'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter([
    'linkAttributes' => false,
    'bracketedSpans' => false,
    'strikeout' => false,
    'subscript' => false,
    'superscript' => false,
    'smart' => false,
    'preferAscii' => true,
]))->write($document) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$citation = new AstNode('citation', [
    'citations' => [
        ['id' => 'source-audit'],
        ['id' => 'media-review'],
    ],
], [
    $text('See '),
    new AstNode('link', ['url' => '#ref-source-audit'], [$text('Source Audit')]),
    $text(' and the '),
    new AstNode('link', [
        'url' => '/wp-admin/post.php?post=42&action=edit',
        'title' => 'Review imported post',
    ], [$text('source post')]),
]);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Citation handoff: '),
        $citation,
        $text(' before block import.'),
    ]),
    $paragraph([
        $text('Footnote source trail'),
        new AstNode('note', [], [
            $paragraph([$text('Open the bibliography source packet before publishing.')]),
        ]),
        $text(' remains linked.'),
    ]),
    new AstNode('div', [
        'id' => 'refs',
        'classes' => ['csl-bib-body'],
    ], [
        new AstNode('div', [
            'id' => 'ref-source-audit',
            'classes' => ['csl-entry'],
        ], [
            $paragraph([$text('Source Audit. Legacy import packet.')]),
        ]),
    ]),
]);

echo "HTML preview:\n";
echo (new HtmlWriter())->write($document) . "\n\n";
echo "WordPress source blocks:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

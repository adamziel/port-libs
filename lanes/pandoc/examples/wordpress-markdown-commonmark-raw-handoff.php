<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('CommonMark source review keeps raw CommonMark inline markers: '),
        new AstNode('raw_inline', [
            'format' => 'commonmark_x',
            'text' => '<span data-source="batch-42">source span</span>',
        ]),
        $text(' and raw HTML review marks: '),
        new AstNode('raw_html_inline', [
            'html' => '<mark data-source="batch-42">needs editor review</mark>',
        ]),
        $text('.'),
    ]),
    new AstNode('paragraph', [], [
        $text('CommonMark hard-break handoff keeps source line'),
        new AstNode('linebreak'),
        $text('attached to the reviewer continuation.'),
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'commonmark',
    'escapedLineBreaks' => false,
]))->write($document) . "\n";

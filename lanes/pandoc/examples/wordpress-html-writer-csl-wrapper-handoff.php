<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$wrapper = new AstNode('div', [
    'id' => 'review-intro',
    'classes' => ['source-packet'],
    'attributes' => [
        'wrapper' => '1',
        'data-source' => 'bibliography-import',
    ],
], [
    new AstNode('paragraph', [], [
        $text('Reviewer bibliography packet for block import.'),
    ]),
]);

$bibliography = new AstNode('div', [
    'id' => 'refs',
    'classes' => ['csl-bib-body'],
], [
    new AstNode('div', [
        'id' => 'ref-source-audit',
        'classes' => ['csl-entry'],
        'attributes' => ['data-source' => 'citation-export'],
    ], [
        new AstNode('paragraph', [], [
            $text('Doe, J. '),
            new AstNode('emph', [], [$text('Migration source audit')]),
            $text('.'),
        ]),
        new AstNode('paragraph', [], [
            $text('Retrieved from '),
            new AstNode('link', [
                'url' => 'https://example.test/source-audit',
            ], [$text('source archive')]),
            $text('.'),
        ]),
    ]),
]);

$document = new AstNode('document', [], [$wrapper, $bibliography]);

echo "HTML preview:\n";
echo (new HtmlWriter())->write($document) . "\n\n";

echo "WordPress blocks:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

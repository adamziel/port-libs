<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Reviewer link packet: '),
        new AstNode('link', [
            'url' => 'https://example.test/import-review',
            'title' => 'Review import packet',
        ], [
            $text('Read '),
            new AstNode('link', [
                'id' => 'source-note',
                'classes' => ['legacy-ref'],
                'url' => 'https://example.test/source-note',
                'title' => 'Nested source note',
                'attributes' => ['data-source' => 'batch-42'],
            ], [
                $text('source note'),
            ]),
            $text(' before publish'),
        ]),
        $text('.'),
    ]),
]);

$preview = (new HtmlWriter())->write($document);
$wordpress = new AstNode('document', [], [
    new AstNode('raw_html', [
        'html' => '<section class="pandoc-link-label-review" data-pandoc-source="html-writer-remove-links">'
            . $preview
            . '</section>',
    ]),
]);

echo "HTML nested-link-label preview:\n";
echo $preview . "\n\n";

echo "WordPress review block:\n";
echo (new WordPressBlockWriter())->write($wordpress) . "\n";

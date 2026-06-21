<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$review = new AstNode('div', [
    'id' => 'review-body',
    'classes' => ['section', 'wp-import-review'],
    'attributes' => ['data-source' => 'legacy-export'],
], [
    new AstNode('paragraph', [], [$text('HTML preview before WordPress import.')]),
    new AstNode('raw_block', [
        'format' => 'html',
        'text' => '<aside data-source="batch-42">Trusted source aside</aside>',
    ]),
    new AstNode('raw_block', [
        'format' => 'tex',
        'text' => "\\begin{tabular}{ll}\nsource & value\n\\end{tabular}",
    ]),
    new AstNode('div', ['classes' => ['nested']], [
        new AstNode('plain', [], [$text('Nested reviewer note')]),
    ]),
]);

$document = new AstNode('document', [], [$review]);

echo "HTML preview:\n";
echo (new HtmlWriter())->write($document) . "\n\n";

echo "WordPress blocks:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

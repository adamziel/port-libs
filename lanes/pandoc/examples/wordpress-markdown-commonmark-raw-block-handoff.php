<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('CommonMark block-level raw handoff for WordPress import review.'),
    ]),
    new AstNode('raw_block', [
        'format' => 'commonmark_x',
        'text' => '<section data-source="batch-42">Preserve trusted source HTML.</section>',
    ]),
    new AstNode('raw_html', [
        'html' => "<aside data-source=\"batch-42\">\n\n<p>Keep source grouping visible.</p>\n</aside>",
    ]),
    new AstNode('raw_block', [
        'format' => 'markdown_github',
        'text' => '## GitHub-only source marker omitted from strict CommonMark',
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'commonmark',
    'rawAttribute' => false,
]))->write($document) . "\n";

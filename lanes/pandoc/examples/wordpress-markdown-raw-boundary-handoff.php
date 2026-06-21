<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
$rawHtml = static fn (string $html): AstNode => new AstNode('raw_html', ['html' => $html]);

$document = new AstNode('document', [], [
    $plain('Source review note for WordPress batch 42'),
    $rawHtml('<aside data-source="batch-42" class="migration-audit">Keep this raw source card attached.</aside>'),
    $plain('Reviewer continuation stays adjacent to the raw card.'),
    $rawHtml('<!-- wp:separator -->'),
    $rawHtml('<section data-source="batch-42">Second raw block remains adjacent.</section>'),
    new AstNode('heading', ['level' => 2, 'id' => 'next-review-step'], [$text('Next Review Step')]),
]);

echo (new MarkdownWriter())->write($document) . "\n";

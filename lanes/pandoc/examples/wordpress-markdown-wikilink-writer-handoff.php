<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$wikilink = static fn (string $url, string $label): AstNode => new AstNode('link', [
    'url' => $url,
    'classes' => ['wikilink'],
], [$text($label)]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Legacy wiki shortcuts: '),
        $wikilink('https://example.test/import/runbook', 'Migration runbook'),
        $text(' and '),
        $wikilink('Legacy import checklist', 'Legacy import checklist'),
        $text(' stay compact in reviewer Markdown.'),
    ]),
    new AstNode('paragraph', [], [
        $text('Before-pipe export for old wiki plugins: '),
        $wikilink('Name of page', 'Editorial checklist'),
        $text('.'),
    ]),
]);

echo (new MarkdownWriter(['wikilinksTitleAfterPipe' => true]))->write(new AstNode('document', [], [
    $document->children[0],
])) . "\n\n";

echo (new MarkdownWriter(['wikilinksTitleBeforePipe' => true]))->write(new AstNode('document', [], [
    $document->children[1],
])) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$heading = static fn (
    int $level,
    string $id,
    string $label,
    array $classes = [],
    array $attributes = []
): AstNode => new AstNode(
    'heading',
    [
        'level' => $level,
        'id' => $id,
        'classes' => $classes,
        'attributes' => $attributes,
    ],
    [$text($label)]
);

$document = new AstNode('document', [], [
    $heading(1, 'migration-review', 'Migration Review'),
    $heading(2, 'source-audit-queue', 'Source Audit Queue', ['unlisted']),
    $heading(2, 'manual-appendix', 'Manual Appendix', ['unnumbered']),
    $heading(2, 'legacy-export', 'Legacy Export', ['unlisted'], ['number' => 'A']),
    $heading(2, 'publish-handoff', 'Publish Handoff'),
    new AstNode('paragraph', [], [
        $text('Reviewer packet keeps source section numbers visible while body headings remain plain.'),
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => true,
    'tableOfContents' => true,
    'numberSections' => true,
    'tocDepth' => 2,
]))->write($document) . "\n";

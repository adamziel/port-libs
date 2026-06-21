<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [
    new AstNode('text', ['text' => $text]),
]);
$heading = static fn (int $level, string $label): AstNode => new AstNode('heading', [
    'level' => $level,
], [$text($label)]);

$document = new AstNode('document', [], [
    $heading(1, 'Import Review'),
    $paragraph('Reviewer packet for a WordPress migration batch.'),
    $heading(2, 'Source Package'),
    $paragraph('Confirm archive paths, authors, and publish dates before import.'),
    $heading(2, 'Media Queue'),
    $paragraph('Check captions, alt text, and attachment references.'),
    new AstNode('div', ['classes' => ['interior']], [
        $heading(1, 'Reviewer Scratch'),
        $paragraph('Internal notes stay in the Markdown body.'),
        $heading(1, 'Second Scratch Heading'),
        $paragraph('Multiple top-level headings make this div interior to the TOC.'),
    ]),
    new AstNode('div', ['classes' => ['publish']], [
        $heading(1, 'Publish Handoff'),
        $heading(2, 'Block Editor Checks'),
        $paragraph('Open the generated blocks and confirm embeds before publishing.'),
    ]),
]);

echo (new MarkdownWriter([
    'standalone' => true,
    'tableOfContents' => true,
]))->write($document) . "\n";

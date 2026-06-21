<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);

$document = new AstNode('document', [], [
    new AstNode('heading', [
        'level' => 1,
        'id' => 'source-audit',
        'classes' => ['unnumbered'],
    ], [
        $text('Source Audit'),
        $note([
            $paragraph([
                $text('Keep reviewer-only context out of the PDF bookmark.'),
            ]),
        ]),
    ]),
    $paragraph([
        $text('Public handoff starts after the review heading.'),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$codeBlock = static fn (string $text, array $attrs = []): AstNode => new AstNode('code_block', $attrs + ['text' => $text]);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Reviewer keeps the source snippet label for print export.'),
    ]),
    $codeBlock('do_shortcode(\'[gallery ids="4,5"]\');', [
        'id' => 'shortcode-audit',
        'classes' => ['php'],
        'attributes' => ['data-source' => 'legacy-shortcode'],
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter(['writerHighlightMethod' => 'IdiomaticHighlighting']))->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

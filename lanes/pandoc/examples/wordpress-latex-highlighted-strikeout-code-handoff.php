<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$code = static fn (string $text, array $attrs = []): AstNode => new AstNode('code', $attrs + ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$strikeout = static fn (array $children): AstNode => new AstNode('strikeout', [], $children);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Reviewer flags '),
        $strikeout([
            $code('renderBlocks', [
                'classes' => ['haskell'],
                'attributes' => ['data-source' => 'migration-lint'],
            ]),
            $text(' before release'),
        ]),
        $text(' while keeping the source audit visible.'),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

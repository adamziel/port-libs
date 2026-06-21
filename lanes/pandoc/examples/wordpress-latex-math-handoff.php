<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$math = static fn (string $text, bool $display = false): AstNode => new AstNode('math', [
    'text' => $text,
    'display' => $display,
]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Reviewer equation: '),
        $math('\sigma|_{\{x\}}'),
        $text(' before publish.'),
    ]),
    $paragraph([
        $text('Display check: '),
        $math('\alpha + \omega \times x^2', true),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

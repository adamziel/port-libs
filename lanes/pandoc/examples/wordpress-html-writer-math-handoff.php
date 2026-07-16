<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$math = static fn (string $text, bool $display = false): AstNode => new AstNode('math', [
    'text' => $text,
    'display' => $display,
]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Equation source handoff: '),
        $math('\langle post_id,media_id \rangle'),
        $text(' stays reviewable before publishing.'),
    ]),
    $paragraph([
        $text('Display equation: '),
        $math('\alpha + \omega \times x^2', true),
    ]),
]);

echo "HTML MathJax preview:\n";
echo (new HtmlWriter(['htmlMathMethod' => 'mathjax']))->write($document) . "\n\n";
echo "HTML KaTeX preview:\n";
echo (new HtmlWriter(['htmlMathMethod' => 'katex']))->write($document) . "\n\n";
echo "HTML WebTeX preview:\n";
echo (new HtmlWriter([
    'htmlMathMethod' => [
        'method' => 'webtex',
        'url' => 'https://example.test/math?tex=',
    ],
]))->write($document) . "\n\n";
echo "HTML GladTeX preview:\n";
echo (new HtmlWriter(['htmlMathMethod' => 'gladtex']))->write($document) . "\n\n";
echo "WordPress source blocks:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

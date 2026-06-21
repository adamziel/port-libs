<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$rawTexInline = static fn (string $tex): AstNode => new AstNode('raw_tex_inline', [
    'tex' => $tex,
    'text' => $tex,
]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Reviewer keeps source citation '),
        $rawTexInline('\cite{wp-import-audit}'),
        $text(' attached for print review.'),
    ]),
    new AstNode('raw_tex', [
        'tex' => "\\begin{tabular}{ll}\nField & Value \\\\\nSource & Legacy export \\\\\n\\end{tabular}",
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$codeBlock = static fn (string $text): AstNode => new AstNode('code_block', ['text' => $text]);
$note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Source audit:'),
        $note([
            $paragraph([
                $text('Inspect the shortcode export before publishing.'),
            ]),
            $codeBlock('do_shortcode(\'[gallery ids="4,5"]\');'),
        ]),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

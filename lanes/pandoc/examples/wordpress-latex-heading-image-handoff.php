<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('heading', [
        'level' => 1,
        'id' => 'source-hero',
    ], [
        $text('Source hero '),
        new AstNode('image', [
            'url' => 'https://example.test/uploads/source-hero.jpg',
            'alt' => 'Legacy hero image',
        ], [
            $text('Legacy hero image'),
        ]),
    ]),
    new AstNode('paragraph', [], [
        $text('Reviewer confirms heading art survived import.'),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

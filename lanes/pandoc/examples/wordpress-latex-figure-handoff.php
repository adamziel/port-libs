<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('figure', [
        'id' => 'fig-import-frame',
        'classes' => ['migration-frame'],
        'attributes' => [
            'latex-placement' => 'htbp',
        ],
        'caption' => 'Imported hero frame',
        'captionInlines' => [$text('Imported hero frame')],
    ], [
        new AstNode('plain', [], [
            new AstNode('image', [
                'url' => 'https://example.test/uploads/imported-frame.jpg',
                'alt' => 'Imported frame',
            ], [
                $text('Imported frame'),
            ]),
        ]),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

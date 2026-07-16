<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('heading', [
        'level' => 2,
        'attributes' => [
            'lang' => 'en',
            'invalid' => 'legacy-export-noise',
        ],
    ], [
        $text('Media Review'),
    ]),
    new AstNode('paragraph', [], [
        $text('Featured asset: '),
        new AstNode('image', [
            'url' => 'https://example.test/uploads/imported-frame.jpg',
            'title' => 'Original export frame',
            'attributes' => [
                'data-source' => 'legacy-export',
            ],
        ], [
            $text('imported '),
            new AstNode('strong', [], [$text('frame')]),
        ]),
        $text('.'),
    ]),
]);

echo (new HtmlWriter())->write($document) . "\n";

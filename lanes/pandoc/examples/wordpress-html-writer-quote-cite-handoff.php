<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Source reviewer says '),
        new AstNode('quoted', ['kind' => 'double'], [
            new AstNode('span', [
                'attributes' => [
                    'cite' => 'https://example.test/import-log#note-42',
                ],
            ], [
                $text('ready for block import'),
            ]),
        ]),
        $text(' before the migration handoff is published.'),
    ]),
]);

echo (new HtmlWriter(['htmlQTags' => true]))->write($document) . "\n";

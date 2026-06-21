<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);

$document = new AstNode('document', [], [
    new AstNode('ordered_list', [
        'start' => 100,
        'style' => 'decimal',
        'delimiter' => 'period',
    ], [
        new AstNode('list_item', [], [
            $paragraph('Review imported source packet before publishing.'),
            new AstNode('div', [
                'classes' => ['wp-import-review'],
                'attributes' => ['data-source' => 'batch-42'],
            ], [
                $plain('Raw source metadata stays grouped with checklist step 100.'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter())->write($document) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);

$document = new AstNode('document', [], [
    new AstNode('bullet_list', [], [
        new AstNode('list_item', [], [
            $plain('Reviewer source note for import batch 42'),
            new AstNode('div', [
                'classes' => ['wp-import-review'],
                'attributes' => ['data-source' => 'batch-42'],
            ], [
                $plain('Raw source packet stays grouped with this review item.'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter())->write($document) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [
    'meta' => [
        'batch' => 'wp-loop-guard-42',
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted source body mentions ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => ' for reviewer context.']),
    ]),
]);

$template = <<<'TPL'
WordPress plain partial loop packet
Batch: $batch$
Guard: ${ guard() }
Body: $body/chomp$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
    'partials' => [
        'guard' => '${ reviewer-loop() }' . "\n",
        'reviewer-loop' => '${ guard() }' . "\n",
    ],
]))->write($document) . "\n";

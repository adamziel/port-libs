<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [
    'meta' => [
        'batch' => 'wp-42',
        'workflow' => [
            'queue' => 'editorial',
        ],
        'reviewers' => [
            ['name' => 'Editor', 'items' => '42', 'status' => 'ready'],
            ['name' => 'Media', 'items' => '7', 'status' => 'needs alt'],
            ['name' => 'Legal', 'items' => '3', 'status' => 'hold'],
        ],
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted source body references ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => ' and reviewer-only import notes.']),
    ]),
]);

$template = <<<'TPL'
WordPress plain alignment template packet
Batch: $batch/left 8$
Queue: $workflow.queue/center 13 "[" "]"$

$for(reviewers)$$it.name/left 12 "| "$$it.items/right 5 " | " " | "$$it.status/center 9 "" " |"$$sep$
$endfor$

$body/chomp$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
]))->write($document) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [
    'meta' => [
        'audit' => [
            'batch' => 'wp-object-loop-42',
            'reviewers' => [
                ['name' => ['first' => 'Ada', 'last' => 'Lovelace'], 'queue' => 'content'],
                ['name' => ['first' => 'Grace', 'last' => 'Hopper'], 'queue' => 'media'],
                ['name' => ['first' => 'Katherine', 'last' => 'Johnson'], 'queue' => 'legal'],
            ],
        ],
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted source body references ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => ' and reviewer-only routing.']),
    ]),
]);

$template = <<<'TPL'
WordPress object-loop reviewer packet
Batch: $audit.batch$
$for(audit.reviewers)$
- $it.name.last$, $it.name.first$ -> $it.queue$
$endfor$
Body: $body/chomp$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
]))->write($document) . "\n";

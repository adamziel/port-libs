<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$document = new AstNode('document', [
    'meta' => [
        'reviewers' => [
            [
                'name' => 'Editor',
                'queue' => 'content',
            ],
            [
                'name' => 'Publisher',
                'queue' => 'final',
            ],
        ],
        'budget' => '30000',
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Review converted source ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'edit link'])]),
        new AstNode('text', ['text' => ' before publish.']),
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => <<<'TPL'
Review packet
${ for(reviewers) }
- ${it.name}: ${it.queue}
${ endfor }
Budget: $$${ budget }
Indented checklist:
  ${ checklist() }
Body: ${ body/chomp }
TPL,
    'partials' => [
        'checklist' => "Confirm source URL\nConfirm media captions\n",
    ],
]))->write($document) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [
    'meta' => [
        'workflow' => [
            'status' => 'ready',
            'queue' => 'editorial',
        ],
        'reviewers' => [
            [
                'name' => 'Editor',
                'email' => 'editor@example.test',
                'role' => 'content',
            ],
            [
                'name' => 'Publisher',
                'email' => 'publisher@example.test',
                'role' => 'final',
            ],
        ],
        'labels' => ['draft', 'public'],
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted source body mentions ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => ' and ']),
        new AstNode('code', ['text' => 'wp_update_post']),
        new AstNode('text', ['text' => '.']),
    ]),
]);

$template = <<<'TPL'
WordPress plain partial template packet
${ reviewer-list() }
Workflow: ${ workflow:workflow-line() }
Labels: ${ labels[, ] }

${body}
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
    'partials' => [
        'reviewer-list' => "Reviewers: \${ reviewers:reviewer()[; ] }\n\${ footer() }\n",
        'reviewer' => '$it.name$ <$it.email$> ($it.role$)' . "\n",
        'footer' => "Queue: \$workflow.queue$\n",
        'workflow-line' => '$it.status$ / $it.queue$',
    ],
]))->write($document) . "\n";

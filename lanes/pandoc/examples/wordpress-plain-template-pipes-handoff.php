<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [
    'meta' => [
        'workflow' => [
            'status' => 'READY',
            'queue' => 'Editorial',
        ],
        'labels' => ['draft', 'public', 'legal'],
        'reviewers' => [
            [
                'name' => 'Editor',
                'role' => 'Content',
            ],
            [
                'name' => 'Publisher',
                'role' => 'Final',
            ],
        ],
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted source body references ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => ' for reviewer context.']),
    ]),
]);

$template = <<<'TPL'
WordPress plain pipe template packet
Status: $workflow.status/lowercase$
Queue: $workflow.queue/uppercase$
Has labels: $if(labels/length)$yes$else$no$endif$
Labels: $labels/length$ total; first=$labels/first$; last=$labels/last$; middle=$for(labels/rest/allbutlast)$$it$$sep$, $endfor$
Reverse labels: $labels/reverse[, ]$
Partial reviewers: ${ reviewers:reviewer()/uppercase[; ] }
Reviewers:
$for(reviewers/pairs)$$it.key/alpha/uppercase$. $it.value.name/uppercase$ ($it.value.role/lowercase$)$sep$
$endfor$

$body/chomp$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
    'partials' => [
        'reviewer' => '$it.name$',
    ],
]))->write($document) . "\n";

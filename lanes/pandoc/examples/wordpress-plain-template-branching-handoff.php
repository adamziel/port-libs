<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [
    'meta' => [
        'workflow' => [
            'status' => 'ready',
            'queue' => 'editorial',
        ],
        'recipients' => [
            [
                'name' => 'Editor',
                'email' => 'editor@example.test',
            ],
            [
                'name' => 'Publisher',
                'email' => 'publisher@example.test',
            ],
        ],
    ],
], [
    $paragraph([
        $text('Converted body mentions '),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [$text('source edit')]),
        $text(' and the approved import queue.'),
    ]),
]);

$template = <<<'TPL'
WordPress plain import branch packet
Status: $if(workflow.status)$$workflow.status$$else$missing$endif$
Queue: ${ workflow.queue }
Fallback: ${ if(missing) }hidden${ else }WordPress review queue${ endif }
Map truth: $if(workflow)$yes$else$no$endif$
Escalation:
$if(legal_hold)$
Legal hold
$elseif(workflow.queue)$
Queue: $workflow.queue$
$else$
Unqueued
$endif$
Notify: $for(recipients)$$it.name$ <$it.email$>$sep$, $endfor$

${body}
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
]))->write($document) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [
    'meta' => [
        'batch' => 'data-liberation-42',
        'labels' => ['draft', 'public'],
        'phases' => [
            [
                'name' => 'intake',
                'status' => 'ready',
                'reviewers' => [
                    ['name' => 'Editor'],
                    ['name' => 'Publisher'],
                ],
            ],
            [
                'name' => 'publish',
                'fallback_status' => 'queued',
                'reviewers' => [],
            ],
        ],
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted source body for reviewer notification.']),
    ]),
]);

$template = <<<'TPL'
WordPress plain nested template packet
Batch: $batch$
Charge: $$0
$-- The source admin URL is intentionally omitted from this reviewer packet.
Labels: $for(labels)$$it$$sep$ | $endfor$
$for(phases)$Phase $it.name$: $if(it.status)$$it.status$$elseif(it.fallback_status)$$it.fallback_status$$else$missing$endif$
Reviewers: $if(it.reviewers)$$for(it.reviewers)$$it.name$$sep$ / $endfor$$else$none$endif$$sep$
---
$endfor$

${body}
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
]))->write($document) . "\n";

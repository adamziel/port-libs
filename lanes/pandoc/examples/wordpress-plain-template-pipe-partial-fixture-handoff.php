<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [
    'meta' => [
        'checks' => [
            "SEO redirect checks",
            "Content QA",
            "Media assets\nConfirm captions",
        ],
        'owners' => [
            'owner' => "editorial\n\n",
            'queue' => "import\n\n",
        ],
        'milestones' => [1, 5, 20],
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted source body references ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => ' for publication packet.']),
    ]),
]);

$template = <<<'TPL'
Reviewer pipe/partial packet
Checks:
$checks/pairs/reverse:check-row()$

Owners:
$for(owners/chomp/pairs/uppercase)$
$it.key$: $it.value$
$endfor$
Milestones: $milestones/roman[ ]$
Body: $body/chomp$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
    'partials' => [
        'check-row.txt' => '$it.key/alpha/uppercase$.  $^$$it.value$' . "\n\n",
    ],
]))->write($document) . "\n";

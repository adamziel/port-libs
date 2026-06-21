<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted source body references ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => ' and reviewer-only import notes.']),
    ]),
]);

$template = <<<'TPL'
WordPress plain nowrap template packet
$~$Reviewer summary can wrap at controlled breakable template spaces for narrow editorial packets.$~$

Protected: ${ protected()/nowrap }
Ready: [${ ready()/chomp }]

$body/chomp$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'columns' => 34,
    'template' => $template,
    'partials' => [
        'protected' => '$~$Legal hold import reference stays on one reviewer line.$~$',
        'ready' => "\$~\$approved \n\$~\$",
    ],
]))->write($document) . "\n";

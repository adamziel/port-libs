<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = new AstNode('document', [
    'meta' => [
        'reviewers' => [
            ['team' => 'Editorial', 'posts' => '42', 'notes' => "Ready for block\npublish"],
            ['team' => 'Media', 'posts' => '7', 'notes' => "Needs alt text\nand credit audit"],
            ['team' => 'Legal', 'notes' => 'Hold for source rights'],
        ],
    ],
], [
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Converted import body references ']),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [new AstNode('text', ['text' => 'source edit'])]),
        new AstNode('text', ['text' => ' and reviewer-only notes.']),
    ]),
]);

$template = <<<'TPL'
WordPress reviewer packet
|------------|-------|----------------------|
$for(reviewers)$
$it.team/left 10 "| "$$it.posts/right 5 " | " " | "$$it.notes/left 20 "" " |"$
$endfor$
|------------|-------|----------------------|

$body/chomp$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
]))->write($document) . "\n";

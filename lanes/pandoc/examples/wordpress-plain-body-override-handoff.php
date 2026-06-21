<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [
    'meta' => [
        'body' => [
            $paragraph([
                $text('Approved plain import body from '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [$text('source edit')]),
                $text(' using '),
                new AstNode('code', ['text' => 'wp_update_post']),
                $text('.'),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [$text('Queue reviewer notification')]),
                new AstNode('list_item', [], [$text('Keep original converted body archived')]),
            ]),
        ],
        'include-after' => [
            $paragraph([
                $text('Footer keeps source references plain for reviewer logs.'),
            ]),
        ],
    ],
], [
    $paragraph([
        $text('Original converted body is intentionally replaced for this reviewer packet.'),
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => true,
    'variables' => [
        'header-includes' => 'WordPress redacted import audit packet',
        'body' => 'Redacted body approved for notification email',
    ],
]))->write($document) . "\n";

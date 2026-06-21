<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [
    'meta' => [
        'include-after' => [
            $paragraph([
                $text('Import footer for '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [$text('review queue')]),
                $text(' using '),
                new AstNode('code', ['text' => 'wp_update_post']),
                $text('.'),
            ]),
        ],
    ],
], [
    $paragraph([
        $text('Review body from '),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [$text('source edit')]),
        $text(' and preserve reviewer notes.'),
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => true,
    'variables' => [
        'header-includes' => 'WordPress import audit packet',
        'include-before' => [
            'Batch: data-liberation-2026-05-23',
            'Reviewer: Migration Desk',
        ],
    ],
]))->write($document) . "\n";

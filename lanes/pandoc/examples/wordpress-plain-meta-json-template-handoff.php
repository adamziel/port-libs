<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [
    'meta' => [
        'title' => 'WordPress Import Audit',
        'batch' => 'data-liberation-2026-05-23',
        'review' => [
            'status' => 'ready',
            'posts' => 42,
        ],
        'include-before' => [
            $paragraph([
                $text('Metadata preface from '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [$text('source edit')]),
                $text('.'),
            ]),
        ],
    ],
], [
    $paragraph([
        $text('Converted body mentions '),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [$text('source edit')]),
        $text(' and '),
        new AstNode('code', ['text' => 'wp_update_post']),
        $text('.'),
    ]),
]);

$template = <<<'TPL'
$if(titleblock)$
$titleblock$

$endif$
Audit metadata:
$meta-json$

$for(include-before)$
$include-before$

$endfor$
$body$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
    'variables' => [
        'include-before' => [
            'Reviewer note: custom template preface overrides metadata preface.',
        ],
    ],
]))->write($document) . "\n";

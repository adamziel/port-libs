<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [
    'meta' => [
        'batch' => 'wp-final-newline-42',
        'ready' => true,
        'legal_hold' => false,
        'review_lines' => [
            ['text' => "Posts converted\n"],
            ['text' => "Media captions need review\n\n"],
            ['text' => 'Publish after reviewer approval'],
        ],
    ],
], [
    $paragraph([
        $text('Converted body mentions '),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [$text('source edit')]),
        $text(' and final reviewer notes.'),
    ]),
]);

$template = <<<'TPL'
WordPress plain final-newline packet
Batch: $batch$
Ready: $ready$
Legal hold: $legal_hold$
Review lines:
$for(review_lines)$
$review_lines.text$
$endfor$
Body: $body/chomp$
TPL;

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => $template,
]))->write($document) . "\n";

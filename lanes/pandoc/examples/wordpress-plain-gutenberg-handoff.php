<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [], [
    new AstNode('heading', ['level' => 2], [
        $text('Plain Gutenberg Review'),
    ]),
    $paragraph([
        $text('Status: '),
        new AstNode('strong', [], [
            $text('media Prüfung'),
        ]),
        $text(' before notifying '),
        new AstNode('strong', [], [
            new AstNode('link', [
                'url' => '/wp-admin/post.php?post=42&action=edit',
            ], [$text('source editor')]),
            $text(' via '),
            new AstNode('code', ['text' => 'wp_update_post']),
        ]),
        $text('.'),
    ]),
    $paragraph([
        $text('Keep '),
        new AstNode('emph', [], [$text('review-only emphasis')]),
        $text(' visible for Gutenberg plain-text excerpts.'),
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'plain',
    'gutenberg' => true,
]))->write($document) . "\n";

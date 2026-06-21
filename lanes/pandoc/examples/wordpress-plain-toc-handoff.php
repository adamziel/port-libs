<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$heading = static fn (int $level, string $id, array $children, array $classes = []): AstNode => new AstNode(
    'heading',
    ['level' => $level, 'id' => $id, 'classes' => $classes],
    $children
);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [], [
    $heading(1, 'migration-review', [
        $text('Migration '),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
            'classes' => ['source-edit'],
        ], [$text('Review')]),
    ]),
    $heading(2, 'media-audit', [
        $text('Media '),
        new AstNode('code', ['text' => 'audit']),
    ]),
    $heading(3, 'private-checks', [$text('Private checks')], ['unlisted']),
    $paragraph([
        $text('Body excerpt keeps '),
        new AstNode('link', [
            'url' => '/wp-admin/post.php?post=42&action=edit',
        ], [$text('source labels')]),
        $text(' and '),
        new AstNode('code', ['text' => 'wp_update_post']),
        $text(' review tokens.'),
    ]),
]);

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => true,
    'tableOfContents' => true,
    'tocDepth' => 2,
]))->write($document) . "\n";

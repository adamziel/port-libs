<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);

$document = new AstNode('document', [], [
    new AstNode('heading', ['level' => 2], [
        $text('Plain Media Review'),
    ]),
    new AstNode('figure', [
        'caption' => 'Imported gallery lead image',
    ], [
        new AstNode('image', [
            'url' => 'https://example.test/uploads/gallery-lead.jpg',
            'title' => 'Imported gallery lead image',
            'alt' => 'Imported gallery lead image',
        ], [$text('Imported gallery lead image')]),
    ]),
    $paragraph([
        $text('Editor follow-up'),
        $note([
            $paragraph([
                $text('Attach media ID before publishing via '),
                new AstNode('link', [
                    'url' => '/wp-admin/post.php?post=42&action=edit',
                ], [$text('source edit')]),
                $text('.'),
            ]),
            new AstNode('code_block', ['text' => 'wp_update_post($post_id);']),
        ]),
        $text(' stays visible without Markdown footnote syntax.'),
    ]),
]);

echo (new MarkdownWriter(['variant' => 'plain']))->write($document) . "\n";

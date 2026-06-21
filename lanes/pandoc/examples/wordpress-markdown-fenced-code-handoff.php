<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('heading', [
        'level' => 2,
        'id' => 'shortcode-cleanup-snippet',
    ], [$text('Shortcode Cleanup Snippet')]),
    new AstNode('paragraph', [], [
        $text('Keep the source batch id and reviewer start line attached to this migration snippet.'),
    ]),
    new AstNode('code_block', [
        'id' => 'batch-42-shortcode-cleanup',
        'classes' => ['php', 'numberLines'],
        'attributes' => [
            'startFrom' => '42',
            'data-source' => 'batch-42',
        ],
        'text' => "remove_shortcode('legacy_gallery');\nadd_shortcode('gallery', 'render_block_gallery');",
    ]),
]);

echo (new MarkdownWriter())->write($document) . "\n";

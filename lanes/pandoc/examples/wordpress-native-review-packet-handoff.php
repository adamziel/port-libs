<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [
    'meta' => [
        'title' => 'WordPress Native Review Packet',
        'batch' => 'wp-native-42',
        'ready' => true,
        'reviewers' => ['content', 'media'],
    ],
], [
    new AstNode('heading', ['level' => 2, 'id' => 'native-review-packet', 'classes' => ['handoff']], [
        $text('Native Review Packet'),
    ]),
    $paragraph([
        $text('Preserve source link '),
        new AstNode('link', ['url' => 'https://example.test/source/post-42', 'title' => 'Source archive'], [
            $text('legacy post 42'),
        ]),
        $text(' for fixture review.'),
    ]),
    new AstNode('bullet_list', [], [
        new AstNode('list_item', [], [
            $paragraph([$text('Confirm imported headings and anchors.')]),
        ]),
        new AstNode('list_item', [], [
            $paragraph([$text('Attach media captions before publish.')]),
        ]),
    ]),
    new AstNode('code_block', [
        'text' => "wp_update_post(\$post_id);\nclean_post_cache(\$post_id);",
        'classes' => ['php'],
        'attributes' => ['data-source' => 'batch-42'],
    ]),
]);

echo (new NativeWriter(['standalone' => true]))->write($document) . "\n";

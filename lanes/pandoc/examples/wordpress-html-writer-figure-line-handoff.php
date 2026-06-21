<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$figure = new AstNode('figure', [
    'id' => 'fig-lalune',
    'classes' => ['review-frame'],
    'attributes' => [
        'data-source' => 'testsuite-images',
    ],
    'caption' => 'lalune',
], [
    new AstNode('plain', [], [
        new AstNode('image', [
            'url' => 'lalune.jpg',
            'title' => 'Voyage dans la Lune',
            'alt' => 'lalune',
        ], [$text('lalune')]),
    ]),
]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('HTML preview before WordPress import:'),
    ]),
    $figure,
    new AstNode('line_block', [], [
        new AstNode('line', [], [
            $text('Reviewer '),
            new AstNode('emph', [], [$text('stanza')]),
        ]),
        new AstNode('line', ['text' => '']),
        new AstNode('line', [], [
            new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('edit source')]),
        ]),
    ]),
    new AstNode('horizontal_rule'),
]);

echo "HTML preview:\n";
echo (new HtmlWriter())->write($document) . "\n\n";

echo "WordPress blocks:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

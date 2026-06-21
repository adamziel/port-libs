<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$item = static fn (array $children): AstNode => new AstNode('list_item', [], $children);

$document = new AstNode('document', [], [
    new AstNode('bullet_list', [], [
        $item([
            $text('list item'),
            new AstNode('raw_html', ['html' => '<details>']),
            new AstNode('bullet_list', [], [
                $item([$text('subitem')]),
            ]),
            new AstNode('raw_html', ['html' => '</details>']),
            new AstNode('paragraph', [], [
                $text('item '),
                new AstNode('emph', [], [$text('continue')]),
                $text(' '),
                new AstNode('strong', [], [$text('with')]),
                $text(' formatting'),
            ]),
        ]),
        $item([$text('next list item')]),
    ]),
]);

echo "GFM reviewer handoff:\n";
echo (new MarkdownWriter(['variant' => 'gfm']))->write($document) . "\n\n";

echo "WordPress blocks:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

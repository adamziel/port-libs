<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [], [
    new AstNode('heading', ['level' => 1], [
        $text('Imported '),
        new AstNode('emph', [], [$text('Review')]),
    ]),
    $paragraph([
        $text('Source '),
        new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('edit link')]),
        $text(' queued for plain-text audit.'),
    ]),
    new AstNode('blockquote', [], [
        $paragraph([
            $text('Original reviewer note keeps quote indentation without Markdown markers.'),
        ]),
    ]),
    new AstNode('raw_block', [
        'format' => 'plain',
        'text' => 'Batch 42 plain reviewer packet',
    ]),
    new AstNode('horizontal_rule'),
]);

echo (new MarkdownWriter(['variant' => 'plain', 'columns' => 32]))->write($document) . "\n";

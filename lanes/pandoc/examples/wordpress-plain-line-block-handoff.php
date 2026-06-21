<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$nbsp = "\xC2\xA0";

$document = new AstNode('document', [], [
    new AstNode('line_block', [], [
        new AstNode('line', [], [
            $text('Reviewer import stanza'),
        ]),
        new AstNode('line', [], [
            $text(str_repeat($nbsp, 2) . 'source indentation survives plain export'),
        ]),
        new AstNode('line', ['text' => '']),
        new AstNode('line', [], [
            new AstNode('link', [
                'url' => '/wp-admin/post.php?post=42&action=edit',
            ], [
                $text('edit source'),
            ]),
            $text(' before publishing'),
        ]),
    ]),
]);

echo (new MarkdownWriter(['variant' => 'plain']))->write($document) . "\n";

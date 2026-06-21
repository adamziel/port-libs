<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$nbsp = "\xC2\xA0";

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Line block handoff:'),
    ]),
    new AstNode('line_block', [], [
        new AstNode('line', [], [
            $text('Reviewer import stanza'),
        ]),
        new AstNode('line', [], [
            $text(str_repeat($nbsp, 2) . 'preserve source indentation'),
        ]),
        new AstNode('line', ['text' => '']),
        new AstNode('line', [], [
            $text('Continuation '),
            new AstNode('link', [
                'url' => '/wp-admin/post.php?post=42&action=edit',
            ], [
                $text('edit source'),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter())->write($document) . "\n";

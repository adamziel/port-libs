<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Reviewer transform: '),
        new AstNode('code', [
            'text' => 'publish >>= notify',
            'classes' => ['sample', 'haskell'],
        ]),
        $text(' stores '),
        new AstNode('code', [
            'text' => 'postId >>= save',
            'classes' => ['haskell', 'variable'],
        ]),
        $text('.'),
    ]),
]);

echo (new HtmlWriter())->write($document) . "\n";

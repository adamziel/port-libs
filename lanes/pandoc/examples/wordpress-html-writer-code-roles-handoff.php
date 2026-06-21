<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Reviewer diagnostics: '),
        new AstNode('code', ['text' => 'core/image']),
        $text(', '),
        new AstNode('code', ['text' => 'Missing alt text', 'classes' => ['sample']]),
        $text(', and '),
        new AstNode('code', ['text' => 'post_title', 'classes' => ['variable']]),
        $text('.'),
    ]),
]);

echo (new HtmlWriter())->write($document) . "\n";

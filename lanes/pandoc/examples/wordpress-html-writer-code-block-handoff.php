<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Reviewer source snippet before block import:'),
    ]),
    new AstNode('code_block', [
        'id' => 'source-filter',
        'attributes' => [
            'data-source' => 'classic-widget',
        ],
        'text' => "if (\$post_id > 0) {\n    clean_post_cache(\$post_id);\n}",
    ]),
]);

echo "HTML preview:\n";
echo (new HtmlWriter())->write($document) . "\n\n";

echo "WordPress blocks:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

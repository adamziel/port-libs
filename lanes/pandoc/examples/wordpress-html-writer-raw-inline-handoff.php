<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$raw = static fn (string $format, string $source): AstNode => new AstNode('raw_inline', [
    'format' => $format,
    'text' => $source,
]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Inline review: '),
        $raw('html', '<span class="source-note">trusted <em>HTML</em></span>'),
        $text(', '),
        $raw('html5', '<mark data-review="publish">ready</mark>'),
        $raw('tex', '\cite{wp-import}'),
        $text('.'),
    ]),
]);

$preview = (new HtmlWriter())->write($document);
$wordpress = new AstNode('document', [], [
    new AstNode('raw_html', [
        'html' => '<section class="pandoc-raw-inline-review" data-pandoc-source="html-writer-raw-inline">'
            . $preview
            . '</section>',
    ]),
]);

echo "HTML raw-inline preview:\n";
echo $preview . "\n\n";

echo "WordPress review block:\n";
echo (new WordPressBlockWriter())->write($wordpress) . "\n";

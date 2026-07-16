<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('HTML styled review: '),
        new AstNode('underline', [], [
            $text('manual '),
            new AstNode('emph', [], [$text('check')]),
        ]),
        $text(', '),
        new AstNode('strikeout', [], [$text('legacy shortcode')]),
        $text(', '),
        new AstNode('small_caps', [], [$text('source glossary')]),
        $text(', H'),
        new AstNode('subscript', [], [$text('2')]),
        $text('O, and note'),
        new AstNode('superscript', [], [
            new AstNode('link', [
                'url' => '/wp-admin/post.php?post=42&action=edit',
            ], [$text('42')]),
        ]),
        $text('.'),
    ]),
]);

$preview = (new HtmlWriter())->write($document);
$wordpress = new AstNode('document', [], [
    new AstNode('raw_html', [
        'html' => '<section class="pandoc-inline-review" data-pandoc-source="html-writer-styled-inlines">'
            . $preview
            . '</section>',
    ]),
]);

echo "HTML styled-inline preview:\n";
echo $preview . "\n\n";

echo "WordPress review block:\n";
echo (new WordPressBlockWriter())->write($wordpress) . "\n";

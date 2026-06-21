<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('HTML span-like review: '),
        new AstNode('span', [
            'id' => 'shortcut-source',
            'classes' => ['source-note', 'underline', 'smallcaps', 'kbd'],
            'attributes' => ['data-pandoc-review' => 'shortcut'],
        ], [$text('Ctrl+Alt+P')]),
        $text(' opens '),
        new AstNode('span', [
            'classes' => ['mark', 'review-highlight'],
        ], [$text('publish preview')]),
        $text(', while '),
        new AstNode('span', [
            'classes' => ['abbr', 'dfn'],
            'attributes' => ['title' => 'HyperText Markup Language'],
        ], [$text('HTML')]),
        $text(' stays defined for reviewers.'),
    ]),
]);

$preview = (new HtmlWriter())->write($document);
$wordpress = new AstNode('document', [], [
    new AstNode('raw_html', [
        'html' => '<section class="pandoc-spanlike-review" data-pandoc-source="html-writer-spanlike">'
            . $preview
            . '</section>',
    ]),
]);

echo "HTML span-like preview:\n";
echo $preview . "\n\n";

echo "WordPress review block:\n";
echo (new WordPressBlockWriter())->write($wordpress) . "\n";

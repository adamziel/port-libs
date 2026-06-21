<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

$document = new AstNode('document', [], [
    $paragraph([$text('HTML media preview before WordPress import:')]),
    $paragraph([
        new AstNode('image', [
            'url' => 'https://example.test/uploads/release-walkthrough.mp4',
        ], [$text('Release walkthrough')]),
    ]),
    $paragraph([
        new AstNode('image', [
            'url' => 'https://example.test/uploads/release-audio.mp3',
        ], [$text('Release audio transcript')]),
    ]),
    $paragraph([
        new AstNode('image', [
            'url' => 'https://example.test/uploads/release-notes.pdf',
            'title' => 'Release notes PDF',
        ]),
    ]),
]);

$preview = (new HtmlWriter())->write($document);
$wordpress = new AstNode('document', [], [
    new AstNode('raw_html', [
        'html' => '<section class="pandoc-media-review" data-pandoc-source="html-writer-media">'
            . $preview
            . '</section>',
    ]),
]);

echo "HTML media preview:\n";
echo $preview . "\n\n";

echo "WordPress review block:\n";
echo (new WordPressBlockWriter())->write($wordpress) . "\n";

<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);

$document = new AstNode('document', [], [
    new AstNode('paragraph', [], [
        $text('Legacy excerpt:'),
        new AstNode('softbreak'),
        new AstNode('emph', [], [$text('keep the source line fold')]),
        $text(' for reviewer context.'),
    ]),
    new AstNode('paragraph', [], [
        $text('Checklist'),
        new AstNode('linebreak'),
        $text('Confirm media attribution.'),
    ]),
]);

$compactPreview = (new HtmlWriter(['writerWrapText' => 'wrap-none']))->write($document);
$preservedPreview = (new HtmlWriter(['writerWrapText' => 'wrap-preserve']))->write($document);
$wordpress = new AstNode('document', [], [
    new AstNode('raw_html', [
        'html' => '<section class="pandoc-softbreak-review" data-pandoc-source="html-writer-softbreak">'
            . $preservedPreview
            . '</section>',
    ]),
]);

echo "Compact HTML preview:\n";
echo $compactPreview . "\n\n";

echo "Source-line-preserving HTML preview:\n";
echo $preservedPreview . "\n\n";

echo "WordPress review block:\n";
echo (new WordPressBlockWriter())->write($wordpress) . "\n";

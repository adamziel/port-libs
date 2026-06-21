<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);
$underline = static fn (array $children): AstNode => new AstNode('underline', [], $children);
$strikeout = static fn (array $children): AstNode => new AstNode('strikeout', [], $children);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Reviewer marks '),
        $underline([
            $text('inserted source context'),
            $note([
                $paragraph([$text('First insert-note paragraph.')]),
                $paragraph([$text('Second insert-note paragraph.')]),
            ]),
            $text(' before publish'),
        ]),
        $text(' and '),
        $strikeout([
            $text('stale shortcode'),
            $note([
                $plain([$text('Keep source deletion note.')]),
            ]),
            $text(' safely removed'),
        ]),
        $text('.'),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

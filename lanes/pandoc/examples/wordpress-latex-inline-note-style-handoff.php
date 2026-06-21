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
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);

$document = new AstNode('document', [], [
    $paragraph([
        $text('Reviewer keeps '),
        $emph([
            $text('source emphasis'),
            $note([
                $paragraph([$text('First reviewer paragraph.')]),
                $paragraph([$text('Second reviewer paragraph.')]),
            ]),
            $text(' visible'),
        ]),
        $text(' and '),
        $strong([
            $text('strong source text'),
            $note([
                $plain([$text('Single reviewer note.')]),
            ]),
            $text(' intact'),
        ]),
        $text('.'),
    ]),
]);

echo "LaTeX reviewer export:\n";
echo (new LatexWriter())->write($document) . "\n\n";

echo "WordPress block handoff:\n";
echo (new WordPressBlockWriter())->write($document) . "\n";

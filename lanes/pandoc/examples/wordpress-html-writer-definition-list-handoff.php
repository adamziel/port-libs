<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$term = static fn (array $children = [], string $fallback = ''): AstNode => new AstNode('term', ['text' => $fallback], $children);
$definition = static fn (array $blocks): AstNode => new AstNode('definition', [], $blocks);
$paragraph = static fn (string $text): AstNode => new AstNode('paragraph', [], [
    new AstNode('text', ['text' => $text]),
]);
$item = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    ['term' => $term->attr('text', '')],
    array_merge([$term], $definitions)
);

$document = new AstNode('document', [], [
    new AstNode('definition_list', [], [
        $item($term([$text('Source status')], 'Source status'), [
            $definition([$paragraph('Ready for WordPress block import')]),
        ]),
        $item($term(), [
            $definition([$paragraph('Legacy export supplied a blank glossary term; preserve it for reviewer audit.')]),
        ]),
    ]),
]);

echo (new HtmlWriter())->write($document) . "\n";

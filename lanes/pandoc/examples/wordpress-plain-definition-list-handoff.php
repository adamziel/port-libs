<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$term = static fn (array $children, string $fallback): AstNode => new AstNode('term', ['text' => $fallback], $children);
$definition = static fn (array $blocks): AstNode => new AstNode('definition', [], $blocks);
$item = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    ['term' => $term->attr('text', '')],
    array_merge([$term], $definitions)
);

$document = new AstNode('document', [], [
    new AstNode('heading', ['level' => 2, 'id' => 'plain-import-glossary'], [
        $text('Plain Import Glossary'),
    ]),
    new AstNode('definition_list', [], [
        $item($term([
            new AstNode('emph', [], [$text('Reusable')]),
            $text(' block'),
        ], 'Reusable block'), [
            $definition([$plain([
                $text('Synced pattern candidate from '),
                new AstNode('link', ['url' => '/wp-admin/post.php?post=42&action=edit'], [$text('source edit')]),
                $text('.'),
            ])]),
            $definition([$plain([
                $text('Needs '),
                new AstNode('strong', [], [$text('editor')]),
                $text(' confirmation before publish.'),
            ])]),
        ]),
        $item($term([$text('Shortcode cleanup')], 'Shortcode cleanup'), [
            $definition([
                $paragraph([
                    $text('Review legacy shortcode packet without leaking Markdown markers.'),
                ]),
                new AstNode('code_block', ['text' => '[gallery ids="12,13"]']),
                new AstNode('blockquote', [], [
                    $paragraph([$text('Original reviewer note stays visibly indented.')]),
                ]),
            ]),
        ]),
    ]),
]);

echo (new MarkdownWriter(['variant' => 'plain']))->write($document) . "\n";

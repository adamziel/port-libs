<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $text): AstNode => new AstNode('text', ['text' => $text]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$term = static fn (string $value): AstNode => new AstNode('term', ['text' => $value], [$text($value)]);
$definition = static fn (array $blocks): AstNode => new AstNode('definition', [], $blocks);
$item = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    ['term' => $term->attr('text', '')],
    array_merge([$term], $definitions)
);

$document = new AstNode('document', [], [
    new AstNode('heading', ['level' => 2, 'id' => 'import-glossary'], [$text('Import Glossary')]),
    new AstNode('paragraph', [], [
        $text('Definition-list Markdown keeps reviewer glossary terms editable before block conversion.'),
    ]),
    new AstNode('definition_list', [], [
        $item($term('Reusable block'), [
            $definition([$plain('Synced pattern candidate from the legacy source.')]),
            $definition([$plain('Needs editor confirmation before publish.')]),
        ]),
        $item($term('Shortcode cleanup'), [
            $definition([
                $paragraph('Review imported shortcodes and preserve the source batch marker.'),
                new AstNode('code_block', [
                    'text' => '[gallery ids="12,13"]',
                    'classes' => ['shortcode'],
                    'attributes' => ['source' => 'wp-export-42'],
                ]),
            ]),
        ]),
    ]),
    new AstNode('definition_list', [], [
        $item($term('Review packet'), [
            $definition([$plain('Kept separate from the glossary so downstream Pandoc imports do not merge reviewer sections.')]),
        ]),
    ]),
]);

echo (new MarkdownWriter())->write($document) . "\n";

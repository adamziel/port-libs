<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$nodeText = static function (AstNode $node) use (&$nodeText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $parts = [];
    foreach ($node->children as $child) {
        $text = $nodeText($child);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
};

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$markers = [
    'dash bullet' => [
        'source' => '- ',
        'list' => 'bullet_list',
        'attrs' => ['marker' => '-'],
    ],
    'plus bullet' => [
        'source' => '+ ',
        'list' => 'bullet_list',
        'attrs' => ['marker' => '+'],
    ],
    'star bullet' => [
        'source' => '* ',
        'list' => 'bullet_list',
        'attrs' => ['marker' => '*'],
    ],
    'decimal period' => [
        'source' => '1. ',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    ],
    'decimal paren' => [
        'source' => '1) ',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'one_paren'],
    ],
];

$quoteVariants = [
    'compact paragraph' => [
        'lines' => ['> quoted boundary'],
        'children' => ['paragraph'],
        'text' => 'quoted boundary',
    ],
    'one-space paragraph' => [
        'lines' => [' > one-space boundary'],
        'children' => ['paragraph'],
        'text' => 'one-space boundary',
    ],
    'compact continuation' => [
        'lines' => ['> quoted boundary', '> continuation'],
        'children' => ['paragraph'],
        'text' => 'quoted boundary continuation',
    ],
    'one-space continuation' => [
        'lines' => [' > one-space boundary', ' > continuation'],
        'children' => ['paragraph'],
        'text' => 'one-space boundary continuation',
    ],
    'marked blank paragraph split' => [
        'lines' => ['> first paragraph', '>', '> second paragraph'],
        'children' => ['paragraph', 'paragraph'],
        'text' => 'first paragraph second paragraph',
    ],
    'nested dash bullet' => [
        'lines' => ['> - nested bullet'],
        'children' => ['bullet_list'],
        'text' => 'nested bullet',
    ],
    'nested plus bullet' => [
        'lines' => ['> + nested plus'],
        'children' => ['bullet_list'],
        'text' => 'nested plus',
    ],
    'nested star bullet' => [
        'lines' => ['> * nested star'],
        'children' => ['bullet_list'],
        'text' => 'nested star',
    ],
    'nested ordered list' => [
        'lines' => ['> 1. nested ordered'],
        'children' => ['ordered_list'],
        'text' => 'nested ordered',
    ],
    'paragraph then nested bullet' => [
        'lines' => ['> quote intro', '> - nested bullet'],
        'children' => ['paragraph', 'bullet_list'],
        'text' => 'quote intro nested bullet',
    ],
    'paragraph then nested quote' => [
        'lines' => ['> quote intro', '> > nested quote'],
        'children' => ['paragraph', 'blockquote'],
        'text' => 'quote intro nested quote',
    ],
    'compact nested quote' => [
        'lines' => ['>> compact nested quote'],
        'children' => ['blockquote'],
        'text' => 'compact nested quote',
    ],
    'thematic break only' => [
        'lines' => ['> ***'],
        'children' => ['horizontal_rule'],
        'text' => '',
    ],
    'paragraph then thematic break' => [
        'lines' => ['> quote intro', '> ***'],
        'children' => ['paragraph', 'horizontal_rule'],
        'text' => 'quote intro',
    ],
    'strong paragraph' => [
        'lines' => ['> quoted **strong** boundary'],
        'children' => ['paragraph'],
        'text' => 'quoted strong boundary',
    ],
];

$tests = [
    'records commonmark reader list blockquote boundary surge mapped-case count' =>
        static function (TestRunner $t) use ($markers, $quoteVariants): void {
            $t->same(75, count($markers) * count($quoteVariants));
        },
];

foreach ($markers as $markerName => $marker) {
    foreach ($quoteVariants as $variantName => $variant) {
        $tests['maps commonmark reader list blockquote boundary surge ' . $markerName . ' before ' . $variantName] =
            static function (TestRunner $t) use ($marker, $variant, $nodeText, $childTypes): void {
                $markdown = $marker['source'] . 'item' . "\n"
                    . implode("\n", $variant['lines'])
                    . "\n\nAfter boundary.";
                $document = (new MarkdownReader(['format' => 'commonmark']))->read($markdown);
                $list = $document->children[0] ?? new AstNode('missing');
                $item = $list->children[0] ?? new AstNode('missing');
                $quote = $document->children[1] ?? new AstNode('missing');
                $after = $document->children[2] ?? new AstNode('missing');

                $t->same([$marker['list'], 'blockquote', 'paragraph'], array_map(
                    static fn (AstNode $node): string => $node->type,
                    $document->children
                ), $markdown);
                $t->same(1, count($list->children), $markdown);
                $t->same('item', $nodeText($item), $markdown);
                foreach ($marker['attrs'] as $attr => $expected) {
                    $t->same($expected, $list->attr($attr), $markdown . ' list attr ' . $attr);
                }

                $t->same($variant['children'], $childTypes($quote), $markdown);
                $t->same($variant['text'], $nodeText($quote), $markdown);
                $t->same('After boundary.', $after->attr('text'), $markdown);
            };
    }
}

return $tests;

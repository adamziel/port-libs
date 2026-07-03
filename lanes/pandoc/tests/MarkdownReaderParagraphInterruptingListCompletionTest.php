<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$listItemText = static function (AstNode $item) use ($inlineText): string {
    return trim($inlineText($item));
};

$tests = [];

$interruptingCases = [
    'dash bullet' => ["Lead\n- dash item", 'bullet_list', ['dash item'], []],
    'indented bullet' => ["Lead\n   - indented item", 'bullet_list', ['indented item'], []],
    'decimal ordered period from one' => ["Lead\n1. decimal item", 'ordered_list', ['decimal item'], ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period']],
    'decimal ordered paren from one' => ["Lead\n1) paren item", 'ordered_list', ['paren item'], ['start' => 1, 'style' => 'decimal', 'delimiter' => 'one_paren']],
    'pandoc default ordered' => ["Lead\n#. default item", 'ordered_list', ['default item'], ['start' => 1, 'style' => 'default', 'delimiter' => 'default']],
    'parenthesized decimal ordered' => ["Lead\n(1) parenthesized item", 'ordered_list', ['parenthesized item'], ['start' => 1, 'style' => 'decimal', 'delimiter' => 'two_parens']],
];

foreach ($interruptingCases as $name => [$markdown, $listType, $items, $attrs]) {
    $tests['maps paragraph-interrupting list ' . $name] =
        static function (TestRunner $t) use ($markdown, $listType, $items, $attrs, $listItemText): void {
            $document = (new MarkdownReader())->read($markdown);
            $list = $document->children[1] ?? new AstNode('missing');

            $t->same(['paragraph', $listType], array_map(static fn (AstNode $node): string => $node->type, $document->children));
            $t->same('Lead', $document->children[0]->attr('text'));
            $t->same($items, array_map($listItemText, $list->children));
            foreach ($attrs as $attr => $expected) {
                $t->same($expected, $list->attr($attr));
            }
        };
}

$nonInterruptingCases = [
    'ordered marker starting from two' => ["Lead\n2. not a paragraph-interrupting list", 'Lead 2. not a paragraph-interrupting list'],
    'year-like ordered marker' => ["Lead\n1986. still paragraph text", 'Lead 1986. still paragraph text'],
];

foreach ($nonInterruptingCases as $name => [$markdown, $expectedText]) {
    $tests['keeps non-one ordered marker in paragraph ' . $name] =
        static function (TestRunner $t) use ($markdown, $expectedText, $inlineText): void {
            $document = (new MarkdownReader())->read($markdown);

            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
            $t->same($expectedText, trim($inlineText($document->children[0])));
        };
}

$tests['records paragraph-interrupting list completion mapped-case count'] =
    static function (TestRunner $t) use ($interruptingCases, $nonInterruptingCases): void {
        $t->same(8, count($interruptingCases) + count($nonInterruptingCases));
    };

return $tests;

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
    'markdown extension dash bullet' => [
        ['format' => 'markdown+lists_without_preceding_blankline'],
        "Lead\n- dash item",
        'bullet_list',
        ['dash item'],
        [],
    ],
    'markdown extension decimal ordered from one' => [
        ['format' => 'markdown+lists_without_preceding_blankline'],
        "Lead\n1. decimal item",
        'ordered_list',
        ['decimal item'],
        ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    ],
    'markdown extension decimal ordered from two' => [
        ['format' => 'markdown+lists_without_preceding_blankline'],
        "Lead\n2. decimal item",
        'ordered_list',
        ['decimal item'],
        ['start' => 2, 'style' => 'decimal', 'delimiter' => 'period'],
    ],
    'commonmark dash bullet' => [
        ['format' => 'commonmark'],
        "Lead\n- dash item",
        'bullet_list',
        ['dash item'],
        [],
    ],
    'commonmark decimal ordered from one' => [
        ['format' => 'commonmark'],
        "Lead\n1. decimal item",
        'ordered_list',
        ['decimal item'],
        ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    ],
    'commonmark x dash bullet' => [
        ['format' => 'commonmark_x'],
        "Lead\n- dash item",
        'bullet_list',
        ['dash item'],
        [],
    ],
    'commonmark x decimal ordered from one' => [
        ['format' => 'commonmark_x'],
        "Lead\n1. decimal item",
        'ordered_list',
        ['decimal item'],
        ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    ],
    'gfm dash bullet' => [
        ['format' => 'gfm'],
        "Lead\n- dash item",
        'bullet_list',
        ['dash item'],
        [],
    ],
    'gfm decimal ordered from one' => [
        ['format' => 'gfm'],
        "Lead\n1. decimal item",
        'ordered_list',
        ['decimal item'],
        ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    ],
];

foreach ($interruptingCases as $name => [$options, $markdown, $listType, $items, $attrs]) {
    $tests['maps paragraph-interrupting list ' . $name] =
        static function (TestRunner $t) use ($options, $markdown, $listType, $items, $attrs, $listItemText): void {
            $document = (new MarkdownReader($options))->read($markdown);
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
    'markdown dash bullet' => [['format' => 'markdown'], "Lead\n- not a paragraph-interrupting list", 'Lead - not a paragraph-interrupting list'],
    'markdown ordered marker from one' => [['format' => 'markdown'], "Lead\n1. not a paragraph-interrupting list", 'Lead 1. not a paragraph-interrupting list'],
    'markdown strict dash bullet' => [['format' => 'markdown_strict'], "Lead\n- not a paragraph-interrupting list", 'Lead - not a paragraph-interrupting list'],
    'markdown mmd dash bullet' => [['format' => 'markdown_mmd'], "Lead\n- not a paragraph-interrupting list", 'Lead - not a paragraph-interrupting list'],
    'markdown phpextra dash bullet' => [['format' => 'markdown_phpextra'], "Lead\n- not a paragraph-interrupting list", 'Lead - not a paragraph-interrupting list'],
    'markdown disabled extension dash bullet' => [['format' => 'markdown-lists_without_preceding_blankline'], "Lead\n- not a paragraph-interrupting list", 'Lead - not a paragraph-interrupting list'],
    'commonmark ordered marker starting from two' => [['format' => 'commonmark'], "Lead\n2. not a paragraph-interrupting list", 'Lead 2. not a paragraph-interrupting list'],
    'commonmark x ordered marker starting from two' => [['format' => 'commonmark_x'], "Lead\n2. not a paragraph-interrupting list", 'Lead 2. not a paragraph-interrupting list'],
    'gfm ordered marker starting from two' => [['format' => 'gfm'], "Lead\n2. not a paragraph-interrupting list", 'Lead 2. not a paragraph-interrupting list'],
    'markdown year-like ordered marker' => [['format' => 'markdown'], "Lead\n1986. still paragraph text", 'Lead 1986. still paragraph text'],
];

foreach ($nonInterruptingCases as $name => [$options, $markdown, $expectedText]) {
    $tests['keeps paragraph-interrupting list disabled ' . $name] =
        static function (TestRunner $t) use ($options, $markdown, $expectedText, $inlineText): void {
            $document = (new MarkdownReader($options))->read($markdown);

            $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
            $t->same($expectedText, trim($inlineText($document->children[0])));
        };
}

$tests['records paragraph-interrupting list completion mapped-case count'] =
    static function (TestRunner $t) use ($interruptingCases, $nonInterruptingCases): void {
        $t->same(19, count($interruptingCases) + count($nonInterruptingCases));
    };

return $tests;

<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$inlines = static function (array $parts) use ($softbreak, $space, $text): array {
    $nodes = [];
    foreach ($parts as $part) {
        if ($part === ' ') {
            $nodes[] = $space();
        } elseif ($part === "\n") {
            $nodes[] = $softbreak();
        } else {
            $nodes[] = $text($part);
        }
    }

    return $nodes;
};

$inlineText = null;
$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text') {
        return (string) $node->attr('text', '');
    }

    if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$firstList = static fn (AstNode $doc): AstNode => $doc->children[0] ?? new AstNode('missing');
$orderedListSummaries = null;
$orderedListSummaries = static function (AstNode $node) use (&$orderedListSummaries): array {
    $summaries = [];
    if ($node->type === 'ordered_list') {
        $summaries[] = [
            'start' => (int) $node->attr('start'),
            'style' => $node->attr('style'),
            'delimiter' => $node->attr('delimiter'),
        ];
    }

    foreach ($node->children as $child) {
        array_push($summaries, ...$orderedListSummaries($child));
    }

    return $summaries;
};

$fancyDecimalRoman = $document([
    $orderedList(
        [
            $listItem([
                $paragraph($inlines(['begins', ' ', 'with', ' ', '2'])),
            ]),
            $listItem([
                $paragraph($inlines(['and', ' ', 'now', ' ', '3'])),
                $paragraph($inlines(['with', ' ', 'a', ' ', 'continuation'])),
                $orderedList(
                    [
                        $listItem([
                            $plain($inlines([
                                'sublist',
                                ' ',
                                'with',
                                ' ',
                                'roman',
                                ' ',
                                'numerals,',
                                "\n",
                                'starting',
                                ' ',
                                'with',
                                ' ',
                                '4',
                            ])),
                        ]),
                        $listItem([
                            $plain($inlines(['more', ' ', 'items'])),
                            $orderedList(
                                [
                                    $listItem([$plain($inlines(['a', ' ', 'subsublist']))]),
                                    $listItem([$plain($inlines(['a', ' ', 'subsublist']))]),
                                ],
                                ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'two_parens']
                            ),
                        ]),
                    ],
                    ['start' => 4, 'style' => 'lower_roman', 'delimiter' => 'period']
                ),
            ]),
        ],
        ['start' => 2, 'style' => 'decimal', 'delimiter' => 'two_parens', 'loose' => true]
    ),
]);

$nestedFancyMarkers = $document([
    $orderedList(
        [
            $listItem([
                $plain($inlines(['Upper', ' ', 'Alpha'])),
                $orderedList(
                    [
                        $listItem([
                            $plain($inlines(['Upper', ' ', 'Roman.'])),
                            $orderedList(
                                [
                                    $listItem([
                                        $plain($inlines(['Decimal', ' ', 'start', ' ', 'with', ' ', '6'])),
                                        $orderedList(
                                            [
                                                $listItem([$plain($inlines(['Lower', ' ', 'alpha', ' ', 'with', ' ', 'paren']))]),
                                            ],
                                            ['start' => 3, 'style' => 'lower_alpha', 'delimiter' => 'one_paren']
                                        ),
                                    ]),
                                ],
                                ['start' => 6, 'style' => 'decimal', 'delimiter' => 'two_parens']
                            ),
                        ]),
                    ],
                    ['start' => 1, 'style' => 'upper_roman', 'delimiter' => 'period']
                ),
            ]),
        ],
        ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period']
    ),
]);

$defaultAutoMarkers = $document([
    $orderedList(
        [
            $listItem([$plain($inlines(['Autonumber.']))]),
            $listItem([
                $plain($inlines(['More.'])),
                $orderedList(
                    [
                        $listItem([$plain($inlines(['Nested.']))]),
                    ],
                    ['start' => 1, 'style' => 'default', 'delimiter' => 'default']
                ),
            ]),
        ],
        ['start' => 1, 'style' => 'default', 'delimiter' => 'default']
    ),
]);

$readerNestedFancyMarkers = (new MarkdownReader())->read(implode("\n", [
    'A.  Upper Alpha',
    '    I.  Upper Roman.',
    '        (6) Decimal start with 6',
    '            c)  Lower alpha with paren',
]));

$cases = [
    'decimal roman alpha loose continuation' => [
        'document' => $fancyDecimalRoman,
        'expected' => implode("\n", [
            '(2) begins with 2',
            '',
            '(3) and now 3',
            '',
            '    with a continuation',
            '',
            '    iv. sublist with roman numerals, starting with 4',
            '    v.  more items',
            '        (A) a subsublist',
            '        (B) a subsublist',
        ]),
        'root' => ['start' => 2, 'style' => 'decimal', 'delimiter' => 'two_parens'],
        'firstText' => 'begins with 2',
        'orderedLists' => [
            ['start' => 2, 'style' => 'decimal', 'delimiter' => 'two_parens'],
            ['start' => 4, 'style' => 'lower_roman', 'delimiter' => 'period'],
            ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'two_parens'],
        ],
    ],
    'nested upper alpha roman decimal lower alpha' => [
        'document' => $nestedFancyMarkers,
        'expected' => implode("\n", [
            'A.  Upper Alpha',
            '    I.  Upper Roman.',
            '        (6) Decimal start with 6',
            '            c)  Lower alpha with paren',
        ]),
        'root' => ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period'],
        'firstText' => 'Upper Alpha',
        'orderedLists' => [
            ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period'],
            ['start' => 1, 'style' => 'upper_roman', 'delimiter' => 'period'],
            ['start' => 6, 'style' => 'decimal', 'delimiter' => 'two_parens'],
            ['start' => 3, 'style' => 'lower_alpha', 'delimiter' => 'one_paren'],
        ],
    ],
    'default autonumber nested marker' => [
        'document' => $defaultAutoMarkers,
        'expected' => implode("\n", [
            '#.  Autonumber.',
            '#.  More.',
            '    #.  Nested.',
        ]),
        'root' => ['start' => 1, 'style' => 'default', 'delimiter' => 'default'],
        'firstText' => 'Autonumber.',
        'orderedLists' => [
            ['start' => 1, 'style' => 'default', 'delimiter' => 'default'],
            ['start' => 1, 'style' => 'default', 'delimiter' => 'default'],
        ],
    ],
    'reader-shaped nested fancy markers' => [
        'document' => $readerNestedFancyMarkers,
        'expected' => implode("\n", [
            'A.  Upper Alpha',
            '  I.  Upper Roman.',
            '    (6) Decimal start with 6',
            '      c)  Lower alpha with paren',
        ]),
        'root' => ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period'],
        'firstText' => 'Upper Alpha',
        'orderedLists' => [
            ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period'],
            ['start' => 1, 'style' => 'upper_roman', 'delimiter' => 'period'],
            ['start' => 6, 'style' => 'decimal', 'delimiter' => 'two_parens'],
            ['start' => 3, 'style' => 'lower_alpha', 'delimiter' => 'one_paren'],
        ],
    ],
];

$tests = [
    'records markdown writer fancy-list fixture completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(4, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer fancy-list fixture completion ' . $label] =
        static function (TestRunner $t) use ($case, $firstList, $inlineText, $label, $orderedListSummaries): void {
            $markdown = (new MarkdownWriter(['softBreak' => 'space']))->write($case['document']);

            $t->same($case['expected'], $markdown, $label);

            $roundTrip = (new MarkdownReader(['softBreak' => 'space']))->read($markdown);
            $list = $firstList($roundTrip);
            $firstItem = $list->children[0] ?? new AstNode('missing');
            $firstBlock = $firstItem->children[0] ?? new AstNode('missing');

            $t->same('ordered_list', $list->type, $label);
            $t->same($case['root']['start'], (int) $list->attr('start'), $label);
            $t->same($case['root']['style'], $list->attr('style'), $label);
            $t->same($case['root']['delimiter'], $list->attr('delimiter'), $label);
            $t->same($case['firstText'], $inlineText($firstBlock), $label);
            $t->same($case['orderedLists'], $orderedListSummaries($roundTrip), $label);
        };
}

return $tests;

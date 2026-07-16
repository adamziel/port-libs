<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$collectOrderedLists = static function (AstNode $node) use (&$collectOrderedLists): array {
    $lists = [];
    if ($node->type === 'ordered_list') {
        $lists[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($lists, ...$collectOrderedLists($child));
    }

    return $lists;
};

$itemTexts = static fn (AstNode $list): array => array_map(
    static fn (AstNode $item): mixed => $item->attr('text'),
    $list->children
);

$cases = [
    'compact decimal type list' => [
        'html' => '<ol type="1"><li>One</li><li>Two</li></ol>',
        'lists' => [
            ['start' => 1, 'style' => 'decimal', 'loose' => false, 'items' => ['One', 'Two']],
        ],
        'markdown' => "1.  One\n2.  Two",
        'wordpress' => '<ol><li>One</li><li>Two</li></ol>',
    ],
    'started decimal type list' => [
        'html' => '<ol type="1" start="7"><li>Seven</li></ol>',
        'lists' => [
            ['start' => 7, 'style' => 'decimal', 'loose' => false, 'items' => ['Seven']],
        ],
        'markdown' => '7.  Seven',
        'wordpress' => '<ol start="7"><li>Seven</li></ol>',
    ],
    'trimmed decimal type list' => [
        'html' => '<ol type=" 1 "><li>Trimmed decimal</li></ol>',
        'lists' => [
            ['start' => 1, 'style' => 'decimal', 'loose' => false, 'items' => ['Trimmed decimal']],
        ],
        'markdown' => '1.  Trimmed decimal',
        'wordpress' => '<ol><li>Trimmed decimal</li></ol>',
    ],
    'type beats roman class list' => [
        'html' => '<ol type="1" class="lower-roman"><li>Decimal override</li></ol>',
        'lists' => [
            ['start' => 1, 'style' => 'decimal', 'loose' => false, 'items' => ['Decimal override']],
        ],
        'markdown' => '1.  Decimal override',
        'wordpress' => '<ol><li>Decimal override</li></ol>',
    ],
    'type beats css alpha list' => [
        'html' => '<ol type="1" style="list-style-type: upper-alpha;"><li>Decimal CSS override</li></ol>',
        'lists' => [
            ['start' => 1, 'style' => 'decimal', 'loose' => false, 'items' => ['Decimal CSS override']],
        ],
        'markdown' => '1.  Decimal CSS override',
        'wordpress' => '<ol><li>Decimal CSS override</li></ol>',
    ],
    'nested decimal type list' => [
        'html' => '<ol type="A"><li>Alpha<ol type="1" start="4"><li>Nested decimal</li></ol></li></ol>',
        'lists' => [
            ['start' => 1, 'style' => 'upper_alpha', 'loose' => false, 'items' => ['AlphaNested decimal']],
            ['start' => 4, 'style' => 'decimal', 'loose' => false, 'items' => ['Nested decimal']],
        ],
        'markdown' => '4.  Nested decimal',
        'wordpress' => '<ol type="A"><li>Alpha<ol start="4"><li>Nested decimal</li></ol></li></ol>',
    ],
    'loose decimal type list' => [
        'html' => '<ol type="1"><li><p>Loose decimal</p><p>Continuation</p></li></ol>',
        'lists' => [
            ['start' => 1, 'style' => 'decimal', 'loose' => true, 'items' => ['Loose decimalContinuation']],
        ],
        'markdown' => "1.  Loose decimal\n\n    Continuation",
        'wordpress' => '<ol><li><p>Loose decimal</p><p>Continuation</p></li></ol>',
    ],
    'zero start keeps decimal type' => [
        'html' => '<ol type="1" start="0"><li>Zero start</li></ol>',
        'lists' => [
            ['start' => 0, 'style' => 'decimal', 'loose' => false, 'items' => ['Zero start']],
        ],
        'markdown' => '0.  Zero start',
        'wordpress' => '<li>Zero start</li>',
    ],
    'negative start keeps decimal type' => [
        'html' => '<ol type="1" start="-2"><li>Negative start</li></ol>',
        'lists' => [
            ['start' => -2, 'style' => 'decimal', 'loose' => false, 'items' => ['Negative start']],
        ],
        'wordpress' => '<li>Negative start</li>',
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream html reader ordered-list decimal type completion ' . $name] =
        static function (TestRunner $t) use ($case, $collectOrderedLists, $itemTexts, $name): void {
            $document = (new MarkdownReader())->read($case['html']);
            $lists = $collectOrderedLists($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(count($case['lists']), count($lists), $name . ' ordered list count');
            foreach ($case['lists'] as $index => $expected) {
                $list = $lists[$index] ?? new AstNode('missing');
                $t->same('ordered_list', $list->type, $name . ' list ' . $index . ' type');
                $t->same($expected['start'], $list->attr('start'), $name . ' list ' . $index . ' start');
                $t->same($expected['style'], $list->attr('style'), $name . ' list ' . $index . ' style');
                $t->same('default', $list->attr('delimiter'), $name . ' list ' . $index . ' delimiter');
                $t->same($expected['loose'], $list->attr('loose'), $name . ' list ' . $index . ' looseness');
                $t->same($expected['items'], $itemTexts($list), $name . ' list ' . $index . ' item text');
            }

            if (isset($case['markdown'])) {
            }
            if (isset($case['wordpress'])) {
                $t->contains($case['wordpress'], $blocks, $name . ' wordpress ordered-list output');
            }
        };
}

return $tests;

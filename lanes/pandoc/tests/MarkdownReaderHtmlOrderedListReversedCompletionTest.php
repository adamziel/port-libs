<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
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
    'compact reversed list' => [
        'html' => '<ol reversed><li>Three</li><li>Two</li><li>One</li></ol>',
        'lists' => [
            ['start' => 1, 'style' => 'default', 'reversed' => true, 'items' => ['Three', 'Two', 'One']],
        ],
        'markdown' => "1.  Three\n2.  Two\n3.  One",
        'wordpress' => '<ol reversed><li>Three</li><li>Two</li><li>One</li></ol>',
        'comment' => '<!-- wp:list {"ordered":true,"reversed":true} -->',
    ],
    'started styled reversed list' => [
        'html' => '<ol reversed start="5" type="A"><li>Five</li><li>Four</li></ol>',
        'lists' => [
            ['start' => 5, 'style' => 'upper_alpha', 'reversed' => true, 'items' => ['Five', 'Four']],
        ],
        'markdown' => "E.  Five\nF.  Four",
        'wordpress' => '<ol start="5" type="A" reversed><li>Five</li><li>Four</li></ol>',
        'comment' => '<!-- wp:list {"ordered":true,"start":5,"reversed":true} -->',
    ],
    'nested decimal reversed list' => [
        'html' => '<ol type="i"><li>Parent<ol reversed start="4" type="1"><li>Four</li><li>Three</li></ol></li></ol>',
        'lists' => [
            ['start' => 1, 'style' => 'lower_roman', 'reversed' => false, 'items' => ['Parent FourThree']],
            ['start' => 4, 'style' => 'decimal', 'reversed' => true, 'items' => ['Four', 'Three']],
        ],
        'markdown' => "  4.  Four\n  5.  Three",
        'wordpress' => '<ol type="i"><li>Parent<ol start="4" reversed><li>Four</li><li>Three</li></ol></li></ol>',
        'comment' => '<!-- wp:list {"ordered":true} -->',
    ],
    'data attribute does not imply reversed list' => [
        'html' => '<ol data-reversed="true"><li>Forward</li></ol>',
        'lists' => [
            ['start' => 1, 'style' => 'default', 'reversed' => false, 'items' => ['Forward']],
        ],
        'markdown' => '1.  Forward',
        'wordpress' => '<ol><li>Forward</li></ol>',
        'comment' => '<!-- wp:list {"ordered":true} -->',
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream html reader ordered-list reversed attribute completion ' . $name] =
        static function (TestRunner $t) use ($case, $collectOrderedLists, $itemTexts, $name): void {
            $document = (new MarkdownReader())->read($case['html']);
            $lists = $collectOrderedLists($document);
            $markdown = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(count($case['lists']), count($lists), $name . ' ordered list count');
            foreach ($case['lists'] as $index => $expected) {
                $list = $lists[$index] ?? new AstNode('missing');
                $t->same('ordered_list', $list->type, $name . ' list ' . $index . ' type');
                $t->same($expected['start'], $list->attr('start'), $name . ' list ' . $index . ' start');
                $t->same($expected['style'], $list->attr('style'), $name . ' list ' . $index . ' style');
                $t->same('default', $list->attr('delimiter'), $name . ' list ' . $index . ' delimiter');
                $t->same('html', $list->attr('sourceFormat'), $name . ' list ' . $index . ' source format');
                $t->same($expected['reversed'], (bool) $list->attr('reversed', false), $name . ' list ' . $index . ' reversed');
                $t->same($expected['items'], $itemTexts($list), $name . ' list ' . $index . ' item text');
                if ($expected['reversed']) {
                    $t->same('', $list->attr('attributes')['reversed'] ?? null, $name . ' source reversed attribute');
                    $t->same('', $list->attr('htmlAttributes')['reversed'] ?? null, $name . ' html reversed attribute');
                }
            }

            $t->contains($case['markdown'], $markdown, $name . ' markdown handoff marker');
            $t->contains($case['wordpress'], $blocks, $name . ' wordpress ordered-list output');
            $t->contains($case['comment'], $blocks, $name . ' wordpress list block attributes');
        };
}

$tests['records html reader reversed ordered-list completion mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(4, count($cases));
    };

return $tests;

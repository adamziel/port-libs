<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

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

    return trim($text);
};

$itemText = static function (AstNode $item) use ($inlineText): string {
    $parts = [];
    foreach ($item->children as $child) {
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list') {
            continue;
        }
        $parts[] = $inlineText($child);
    }

    return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
};

$markerFamilies = [
    'decimal period' => [
        'first' => '1. ',
        'second' => '2. ',
        'indent' => '   ',
        'style' => 'decimal',
        'delimiter' => 'period',
        'start' => 1,
    ],
    'decimal paren' => [
        'first' => '1) ',
        'second' => '2) ',
        'indent' => '   ',
        'style' => 'decimal',
        'delimiter' => 'one_paren',
        'start' => 1,
    ],
    'parenthesized decimal' => [
        'first' => '(1) ',
        'second' => '(2) ',
        'indent' => '    ',
        'style' => 'decimal',
        'delimiter' => 'two_parens',
        'start' => 1,
    ],
    'default period' => [
        'first' => '#. ',
        'second' => '#. ',
        'indent' => '   ',
        'style' => 'default',
        'delimiter' => 'default',
        'start' => 1,
    ],
    'default paren' => [
        'first' => '#) ',
        'second' => '#) ',
        'indent' => '   ',
        'style' => 'default',
        'delimiter' => 'default',
        'start' => 1,
    ],
    'numbered example' => [
        'first' => '(@) ',
        'second' => '(@) ',
        'indent' => '    ',
        'style' => 'example',
        'delimiter' => 'two_parens',
        'start' => 1,
    ],
    'labeled numbered example' => [
        'first' => '(@review-first) ',
        'second' => '(@review-second) ',
        'indent' => '                ',
        'style' => 'example',
        'delimiter' => 'two_parens',
        'start' => 1,
    ],
    'upper alpha period' => [
        'first' => 'A.  ',
        'second' => 'B.  ',
        'indent' => '    ',
        'style' => 'upper_alpha',
        'delimiter' => 'period',
        'start' => 1,
    ],
    'lower alpha paren' => [
        'first' => 'a)  ',
        'second' => 'b)  ',
        'indent' => '    ',
        'style' => 'lower_alpha',
        'delimiter' => 'one_paren',
        'start' => 1,
    ],
    'upper roman period' => [
        'first' => 'I.  ',
        'second' => 'II.  ',
        'indent' => '    ',
        'style' => 'upper_roman',
        'delimiter' => 'period',
        'start' => 1,
    ],
    'lower roman period' => [
        'first' => 'i.  ',
        'second' => 'ii.  ',
        'indent' => '    ',
        'style' => 'lower_roman',
        'delimiter' => 'period',
        'start' => 1,
    ],
];

$variants = [
    'unchecked checked inline' => static fn (array $marker): array => [
        'markdown' => $marker['first'] . '[ ] todo' . "\n" . $marker['second'] . '[x] done',
        'states' => [false, true],
        'texts' => ['todo', 'done'],
        'loose' => false,
    ],
    'uppercase checked and empty task' => static fn (array $marker): array => [
        'markdown' => $marker['first'] . '[X] approved' . "\n" . $marker['second'] . '[ ]',
        'states' => [true, false],
        'texts' => ['approved', ''],
        'loose' => false,
    ],
    'loose paragraph continuation' => static fn (array $marker): array => [
        'markdown' => $marker['first'] . '[ ] queue' . "\n\n" . $marker['indent'] . 'second paragraph'
            . "\n" . $marker['second'] . '[x] shipped',
        'states' => [false, true],
        'texts' => ['queue second paragraph', 'shipped'],
        'loose' => true,
        'firstChildTypes' => ['paragraph', 'paragraph'],
    ],
    'blockquote continuation' => static fn (array $marker): array => [
        'markdown' => $marker['first'] . '[ ] review' . "\n" . $marker['indent'] . '> quoted note'
            . "\n" . $marker['second'] . '[x] close',
        'states' => [false, true],
        'texts' => ['review quoted note', 'close'],
        'loose' => false,
        'firstChildTypes' => ['text', 'blockquote'],
    ],
    'nested bullet task continuation' => static fn (array $marker): array => [
        'markdown' => $marker['first'] . '[x] parent' . "\n" . $marker['indent'] . '- [ ] child'
            . "\n" . $marker['second'] . '[ ] sibling',
        'states' => [true, false],
        'texts' => ['parent', 'sibling'],
        'loose' => false,
        'firstChildTypes' => ['text', 'bullet_list'],
        'nestedTask' => false,
    ],
];

$surgeCases = [];
foreach ($markerFamilies as $markerLabel => $marker) {
    foreach ($variants as $variantLabel => $variant) {
        $surgeCases[$markerLabel . ' ' . $variantLabel] = array_merge(
            ['marker' => $marker, 'markerLabel' => $markerLabel, 'variantLabel' => $variantLabel],
            $variant($marker)
        );
    }
}

$tests = [];

foreach ($surgeCases as $name => $case) {
    $tests['maps gfm ordered task list reader surge ' . $name] =
        static function (TestRunner $t) use ($case, $itemText): void {
            $document = (new MarkdownReader())->read($case['markdown']);
            $list = $document->children[0] ?? new AstNode('missing');
            $items = $list->children;

            $t->same('ordered_list', $list->type, $case['markerLabel']);
            $t->same(true, $list->attr('taskList', false), $case['markerLabel']);
            $t->same($case['marker']['start'], $list->attr('start'), $case['markerLabel']);
            $t->same($case['marker']['style'], $list->attr('style'), $case['markerLabel']);
            $t->same($case['marker']['delimiter'], $list->attr('delimiter'), $case['markerLabel']);
            $t->same((bool) $case['loose'], (bool) $list->attr('loose'), $case['markerLabel']);
            $t->same(2, count($items), $case['markerLabel']);
            $t->same($case['states'], array_map(
                static fn (AstNode $item): ?bool => $item->attr('taskChecked', null),
                $items
            ), $case['markerLabel']);
            $t->same($case['texts'], array_map($itemText, $items), $case['markerLabel']);

            if (isset($case['firstChildTypes'])) {
                $t->same($case['firstChildTypes'], array_map(
                    static fn (AstNode $child): string => $child->type,
                    $items[0]->children
                ), $case['markerLabel']);
            }
            if (array_key_exists('nestedTask', $case)) {
                $nested = $items[0]->children[1] ?? new AstNode('missing');
                $t->same('bullet_list', $nested->type, $case['markerLabel']);
                $t->same(true, $nested->attr('taskList', false), $case['markerLabel']);
                $t->same($case['nestedTask'], $nested->children[0]->attr('taskChecked', null), $case['markerLabel']);
            }

            $markdown = (new MarkdownWriter())->write($document);
            $roundTrip = (new MarkdownReader())->read($markdown);
            $roundTripList = $roundTrip->children[0] ?? new AstNode('missing');
            $t->same('ordered_list', $roundTripList->type, $case['markerLabel'] . ' writer roundtrip type');
            $t->same(true, $roundTripList->attr('taskList', false), $case['markerLabel'] . ' writer roundtrip task metadata');
            $t->same($case['states'], array_map(
                static fn (AstNode $item): ?bool => $item->attr('taskChecked', null),
                $roundTripList->children
            ), $case['markerLabel'] . ' writer roundtrip states');

            $wordpress = (new WordPressBlockWriter())->write($document);
            $t->contains('<!-- wp:list {"ordered":true', $wordpress, $case['markerLabel']);
            $t->contains('class="task-list"', $wordpress, $case['markerLabel']);
            $t->contains('<input type="checkbox"', $wordpress, $case['markerLabel']);
            if (in_array(true, $case['states'], true)) {
                $t->contains('checked=""', $wordpress, $case['markerLabel']);
            }
        };
}

$tests['records gfm ordered task list reader surge mapped-case count'] =
    static function (TestRunner $t) use ($surgeCases): void {
        $t->same(55, count($surgeCases));
    };

$tests['preserves unordered task list wordpress class while adding ordered task list class'] =
    static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $unordered = $reader->read("- [ ] todo\n- [x] done");
        $ordered = $reader->read("1. [ ] todo\n2. [x] done");

        $t->same(true, $unordered->children[0]->attr('taskList', false));
        $t->same(true, $ordered->children[0]->attr('taskList', false));
        $t->contains('<ul class="task-list">', (new WordPressBlockWriter())->write($unordered));
        $t->contains('<ol class="task-list">', (new WordPressBlockWriter())->write($ordered));
    };

return $tests;

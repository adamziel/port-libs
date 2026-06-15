<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array|string $value): AstNode => new AstNode(
    'paragraph',
    [],
    is_array($value) ? $value : [$text($value)]
);
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_replace(['text' => $value], $attrs)
);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$line = static fn (array|string $value = ''): AstNode => new AstNode(
    'line',
    [],
    is_array($value) ? $value : ($value === '' ? [] : [$text($value)])
);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$itemText = static function (AstNode $item) use ($plainText): string {
    $parts = [];
    foreach ($item->children as $child) {
        if (in_array($child->type, ['bullet_list', 'ordered_list', 'definition_list'], true)) {
            continue;
        }
        $part = trim($plainText($child));
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return implode(' ', $parts);
};

$firstList = static fn (AstNode $document): AstNode => $document->children[0] ?? new AstNode('missing');
$firstItem = static fn (AstNode $list): AstNode => $list->children[0] ?? new AstNode('missing');

$taskContinuationCases = [
    '01 bullet unchecked second paragraph' => [
        $document([$bulletList([$listItem([$paragraph('alpha'), $paragraph('beta')], ['taskChecked' => false])])]),
        "- [ ] alpha\n\n  beta",
        'bullet_list',
        'alpha beta',
    ],
    '02 bullet checked second paragraph' => [
        $document([$bulletList([$listItem([$paragraph('alpha'), $paragraph('done')], ['taskChecked' => true])])]),
        "- [x] alpha\n\n  done",
        'bullet_list',
        'alpha done',
        true,
    ],
    '03 plus marker unchecked second paragraph' => [
        $document([$bulletList([$listItem([$paragraph('plus'), $paragraph('carry')], ['taskChecked' => false])])]),
        "+ [ ] plus\n\n  carry",
        'bullet_list',
        'plus carry',
        false,
        ['bulletListMarker' => 'plus'],
    ],
    '04 star marker checked second paragraph' => [
        $document([$bulletList([$listItem([$paragraph('star'), $paragraph('carry')], ['taskChecked' => true])])]),
        "* [x] star\n\n  carry",
        'bullet_list',
        'star carry',
        true,
        ['bulletListMarker' => 'star'],
    ],
    '05 decimal task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('one'), $paragraph('two')], ['taskChecked' => false])])]),
        "1.  [ ] one\n\n    two",
        'ordered_list',
        'one two',
    ],
    '06 decimal checked task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('one'), $paragraph('two')], ['taskChecked' => true])])]),
        "1.  [x] one\n\n    two",
        'ordered_list',
        'one two',
        true,
    ],
    '07 decimal start nine task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('nine'), $paragraph('carry')], ['taskChecked' => false])], ['start' => 9])]),
        "9.  [ ] nine\n\n    carry",
        'ordered_list',
        'nine carry',
    ],
    '08 decimal one paren task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('paren'), $paragraph('carry')], ['taskChecked' => false])], ['delimiter' => 'one_paren'])]),
        "1)  [ ] paren\n\n    carry",
        'ordered_list',
        'paren carry',
    ],
    '09 decimal two parens task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('paren'), $paragraph('carry')], ['taskChecked' => false])], ['delimiter' => 'two_parens'])]),
        "(1) [ ] paren\n\n    carry",
        'ordered_list',
        'paren carry',
    ],
    '10 lower alpha task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('alpha'), $paragraph('carry')], ['taskChecked' => false])], ['style' => 'lower_alpha'])]),
        "a.  [ ] alpha\n\n    carry",
        'ordered_list',
        'alpha carry',
    ],
    '11 upper alpha task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('alpha'), $paragraph('carry')], ['taskChecked' => true])], ['style' => 'upper_alpha'])]),
        "A.  [x] alpha\n\n    carry",
        'ordered_list',
        'alpha carry',
        true,
    ],
    '12 lower roman task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('roman'), $paragraph('carry')], ['taskChecked' => false])], ['style' => 'lower_roman', 'start' => 4])]),
        "iv. [ ] roman\n\n    carry",
        'ordered_list',
        'roman carry',
    ],
    '13 upper roman task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('roman'), $paragraph('carry')], ['taskChecked' => true])], ['style' => 'upper_roman', 'start' => 4])]),
        "IV. [x] roman\n\n    carry",
        'ordered_list',
        'roman carry',
        true,
    ],
    '14 default ordered task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('auto'), $paragraph('carry')], ['taskChecked' => false])], ['style' => 'default'])]),
        "#.  [ ] auto\n\n    carry",
        'ordered_list',
        'auto carry',
    ],
    '15 numbered example task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('example'), $paragraph('carry')], ['taskChecked' => false])], ['style' => 'example'])]),
        "(@) [ ] example\n\n    carry",
        'ordered_list',
        'example carry',
    ],
    '16 labeled numbered example task second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('example'), $paragraph('carry')], ['taskChecked' => true, 'exampleLabel' => 'task-a'])], ['style' => 'example'])]),
        "(@task-a) [x] example\n\n          carry",
        'ordered_list',
        'example carry',
        true,
    ],
    '17 bullet softbreak paragraph continuation' => [
        $document([$bulletList([$listItem([$paragraph([$text('alpha'), new AstNode('softbreak'), $text('beta')])], ['taskChecked' => false])])]),
        "- [ ] alpha\n  beta",
        'bullet_list',
        'alpha beta',
    ],
    '18 bullet hardbreak paragraph continuation' => [
        $document([$bulletList([$listItem([$paragraph([$text('alpha'), new AstNode('linebreak'), $text('beta')])], ['taskChecked' => false])])]),
        "- [ ] alpha\\\n  beta",
        'bullet_list',
        'alpha\\ beta',
    ],
    '19 ordered softbreak paragraph continuation' => [
        $document([$orderedList([$listItem([$paragraph([$text('alpha'), new AstNode('softbreak'), $text('beta')])], ['taskChecked' => false])])]),
        "1.  [ ] alpha\n    beta",
        'ordered_list',
        'alpha beta',
    ],
    '20 ordered hardbreak paragraph continuation' => [
        $document([$orderedList([$listItem([$paragraph([$text('alpha'), new AstNode('linebreak'), $text('beta')])], ['taskChecked' => false])])]),
        "1.  [ ] alpha\\\n    beta",
        'ordered_list',
        'alpha\\ beta',
    ],
    '21 bullet three task paragraphs' => [
        $document([$bulletList([$listItem([$paragraph('one'), $paragraph('two'), $paragraph('three')], ['taskChecked' => false])])]),
        "- [ ] one\n\n  two\n\n  three",
        'bullet_list',
        'one two three',
    ],
    '22 ordered three task paragraphs' => [
        $document([$orderedList([$listItem([$paragraph('one'), $paragraph('two'), $paragraph('three')], ['taskChecked' => true])])]),
        "1.  [x] one\n\n    two\n\n    three",
        'ordered_list',
        'one two three',
        true,
    ],
    '23 bullet task paragraph before nested bullet' => [
        $document([$bulletList([$listItem([$paragraph('task'), $paragraph('detail'), $bulletList([$listItem([$text('nested')])])], ['taskChecked' => false])])]),
        "- [ ] task\n\n  detail\n  - nested",
        'bullet_list',
        'task detail',
    ],
    '24 ordered task paragraph before nested bullet' => [
        $document([$orderedList([$listItem([$paragraph('task'), $paragraph('detail'), $bulletList([$listItem([$text('nested')])])], ['taskChecked' => false])])]),
        "1.  [ ] task\n\n    detail\n    - nested",
        'ordered_list',
        'task detail',
    ],
    '25 bullet task paragraph before nested ordered' => [
        $document([$bulletList([$listItem([$paragraph('task'), $paragraph('detail'), $orderedList([$listItem([$text('nested')])])], ['taskChecked' => false])])]),
        "- [ ] task\n\n  detail\n  1.  nested",
        'bullet_list',
        'task detail',
    ],
    '26 ordered task paragraph before nested ordered' => [
        $document([$orderedList([$listItem([$paragraph('task'), $paragraph('detail'), $orderedList([$listItem([$text('nested')])])], ['taskChecked' => false])])]),
        "1.  [ ] task\n\n    detail\n    1.  nested",
        'ordered_list',
        'task detail',
    ],
    '27 bullet task paragraph before blockquote' => [
        $document([$bulletList([$listItem([$paragraph('task'), $paragraph('detail'), $blockquote([$paragraph('quoted')])], ['taskChecked' => false])])]),
        "- [ ] task\n\n  detail\n  > quoted",
        'bullet_list',
        'task detail quoted',
    ],
    '28 ordered task paragraph before blockquote' => [
        $document([$orderedList([$listItem([$paragraph('task'), $paragraph('detail'), $blockquote([$paragraph('quoted')])], ['taskChecked' => false])])]),
        "1.  [ ] task\n\n    detail\n    > quoted",
        'ordered_list',
        'task detail quoted',
    ],
    '29 bullet task paragraph before indented code' => [
        $document([$bulletList([$listItem([$paragraph('task'), $paragraph('detail'), $codeBlock('echo task')], ['taskChecked' => false])])]),
        "- [ ] task\n\n  detail\n      echo task",
        'bullet_list',
        'task detail',
    ],
    '30 ordered task paragraph before indented code' => [
        $document([$orderedList([$listItem([$paragraph('task'), $paragraph('detail'), $codeBlock('echo task')], ['taskChecked' => false])])]),
        "1.  [ ] task\n\n    detail\n        echo task",
        'ordered_list',
        'task detail',
    ],
    '31 bullet task paragraph before fenced code' => [
        $document([$bulletList([$listItem([$paragraph('task'), $paragraph('detail'), $codeBlock('echo task', ['classes' => ['php']])], ['taskChecked' => false])])]),
        "- [ ] task\n\n  detail\n  ```php\n  echo task\n  ```",
        'bullet_list',
        'task detail',
    ],
    '32 ordered task paragraph before fenced code' => [
        $document([$orderedList([$listItem([$paragraph('task'), $paragraph('detail'), $codeBlock('echo task', ['classes' => ['php']])], ['taskChecked' => false])])]),
        "1.  [ ] task\n\n    detail\n    ```php\n    echo task\n    ```",
        'ordered_list',
        'task detail',
    ],
    '33 bullet loose task item second paragraph' => [
        $document([$bulletList([$listItem([$paragraph('loose'), $paragraph('detail')], ['taskChecked' => false, 'loose' => true])])]),
        "- [ ] loose\n\n  detail",
        'bullet_list',
        'loose detail',
    ],
    '34 ordered loose task item second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('loose'), $paragraph('detail')], ['taskChecked' => true, 'loose' => true])])]),
        "1.  [x] loose\n\n    detail",
        'ordered_list',
        'loose detail',
        true,
    ],
    '35 bullet loose task list second paragraph' => [
        $document([$bulletList([$listItem([$paragraph('loose'), $paragraph('detail')], ['taskChecked' => false])], ['loose' => true])]),
        "- [ ] loose\n\n  detail",
        'bullet_list',
        'loose detail',
    ],
    '36 ordered loose task list second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('loose'), $paragraph('detail')], ['taskChecked' => false])], ['loose' => true])]),
        "1.  [ ] loose\n\n    detail",
        'ordered_list',
        'loose detail',
    ],
    '37 bullet task multi-line second paragraph' => [
        $document([$bulletList([$listItem([$paragraph('alpha'), $paragraph([$text('beta'), new AstNode('softbreak'), $text('gamma')])], ['taskChecked' => false])])]),
        "- [ ] alpha\n\n  beta\n  gamma",
        'bullet_list',
        'alpha beta gamma',
    ],
    '38 ordered task multi-line second paragraph' => [
        $document([$orderedList([$listItem([$paragraph('alpha'), $paragraph([$text('beta'), new AstNode('softbreak'), $text('gamma')])], ['taskChecked' => false])])]),
        "1.  [ ] alpha\n\n    beta\n    gamma",
        'ordered_list',
        'alpha beta gamma',
    ],
    '39 bullet task raw markdown-looking paragraph' => [
        $document([$bulletList([$listItem([$paragraph('alpha'), $paragraph('# still paragraph')], ['taskChecked' => false])])]),
        "- [ ] alpha\n\n  \\# still paragraph",
        'bullet_list',
        'alpha # still paragraph',
    ],
    '40 ordered task raw markdown-looking paragraph' => [
        $document([$orderedList([$listItem([$paragraph('alpha'), $paragraph('- still paragraph')], ['taskChecked' => false])])]),
        "1.  [ ] alpha\n\n    \\- still paragraph",
        'ordered_list',
        'alpha - still paragraph',
    ],
    '41 bullet task definition-marker-looking paragraph' => [
        $document([$bulletList([$listItem([$paragraph('alpha'), $paragraph(': still paragraph')], ['taskChecked' => false])])]),
        "- [ ] alpha\n\n  \\: still paragraph",
        'bullet_list',
        'alpha : still paragraph',
    ],
    '42 ordered task default-marker-looking paragraph' => [
        $document([$orderedList([$listItem([$paragraph('alpha'), $paragraph('#. still paragraph')], ['taskChecked' => false])])]),
        "1.  [ ] alpha\n\n    \\#. still paragraph",
        'ordered_list',
        'alpha #. still paragraph',
    ],
    '43 bullet checked paragraph before line block' => [
        $document([$bulletList([$listItem([$paragraph('alpha'), $paragraph('beta'), $lineBlock([$line('one'), $line('two')])], ['taskChecked' => true])])]),
        "- [x] alpha\n\n  beta\n  | one\n  | two",
        'bullet_list',
        'alpha beta onetwo',
        true,
    ],
    '44 ordered checked paragraph before line block' => [
        $document([$orderedList([$listItem([$paragraph('alpha'), $paragraph('beta'), $lineBlock([$line('one'), $line('two')])], ['taskChecked' => true])])]),
        "1.  [x] alpha\n\n    beta\n    | one\n    | two",
        'ordered_list',
        'alpha beta onetwo',
        true,
    ],
    '45 bullet task empty first paragraph then continuation' => [
        $document([$orderedList([$listItem([$paragraph('ten'), $paragraph('carry')], ['taskChecked' => false])], ['start' => 10])]),
        "10. [ ] ten\n\n    carry",
        'ordered_list',
        'ten carry',
    ],
    '46 ordered task empty first paragraph then continuation' => [
        $document([$orderedList([$listItem([$paragraph('hundred'), $paragraph('carry')], ['taskChecked' => false])], ['start' => 100])]),
        "100. [ ] hundred\n\n     carry",
        'ordered_list',
        'hundred carry',
    ],
    '47 bullet task paragraph after empty marker text' => [
        $document([$bulletList([$listItem([$paragraph('beta')], ['taskChecked' => false])])]),
        "- [ ] beta",
        'bullet_list',
        'beta',
    ],
    '48 ordered task paragraph after empty marker text' => [
        $document([$orderedList([$listItem([$paragraph('beta')], ['taskChecked' => true])])]),
        "1.  [x] beta",
        'ordered_list',
        'beta',
        true,
    ],
    '49 bullet task paragraph after inline child' => [
        $document([$bulletList([$listItem([$text('inline'), $paragraph('beta')], ['taskChecked' => false])])]),
        "- [ ] inline\n\n  beta",
        'bullet_list',
        'inline beta',
    ],
    '50 ordered task paragraph after inline child' => [
        $document([$orderedList([$listItem([$text('inline'), $paragraph('beta')], ['taskChecked' => false])])]),
        "1.  [ ] inline\n\n    beta",
        'ordered_list',
        'inline beta',
    ],
];

$tests = [];

$tests['records upstream markdown writer task list continuation surge mapped-case count'] =
    static function (TestRunner $t) use ($taskContinuationCases): void {
        $t->same(50, count($taskContinuationCases));
    };

foreach ($taskContinuationCases as $name => $case) {
    $tests['maps upstream markdown writer task list continuation surge ' . $name] =
        static function (TestRunner $t) use ($case, $firstList, $firstItem, $itemText): void {
            [$doc, $expected, $listType, $expectedText, $checked, $options] = [
                $case[0],
                $case[1],
                $case[2],
                $case[3],
                $case[4] ?? false,
                $case[5] ?? [],
            ];

            $markdown = (new MarkdownWriter($options))->write($doc);
            $t->same($expected, $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $list = $firstList($roundTrip);
            $item = $firstItem($list);
            $childTypes = array_map(static fn (AstNode $node): string => $node->type, $item->children);

            $t->same($listType, $list->type);
            $t->same($checked, $item->attr('taskChecked'));
            $t->same($expectedText, $itemText($item));
            $t->true(!in_array('code_block', array_slice($childTypes, 0, 2), true), 'Task paragraph continuation must not become code');
        };
}

return $tests;

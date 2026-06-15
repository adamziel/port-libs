<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$plainText = static fn (string $value): AstNode => $plain([$text($value)]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$code = static fn (string $value, array $attrs = []): AstNode => new AstNode('code_block', array_merge(['text' => $value], $attrs));
$item = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bullet = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$ordered = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$doc = static fn (array $children): AstNode => new AstNode('document', [], $children);
$line = static fn (string $value): AstNode => new AstNode('line', [], [$text($value)]);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$definition = static fn (array $children): AstNode => new AstNode('definition', [], $children);
$definitionTerm = static fn (string $value): AstNode => new AstNode('definition_term', [], [$text($value)]);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$definitionList = static fn (array $items): AstNode => new AstNode('definition_list', [], $items);
$div = static fn (array $children, array $attrs = []): AstNode => new AstNode('div', $attrs, $children);

$link = static fn (string $label, string $url): AstNode => new AstNode('link', ['url' => $url], [$text($label)]);
$emph = static fn (string $value): AstNode => new AstNode('emph', [], [$text($value)]);
$strong = static fn (string $value): AstNode => new AstNode('strong', [], [$text($value)]);
$inlineCode = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);

$plainListCases = [
    'bullet plain single item' => [
        $doc([$bullet([$item([$plainText('alpha')])])]),
        '- alpha',
    ],
    'bullet plain two items' => [
        $doc([$bullet([$item([$plainText('alpha')]), $item([$plainText('beta')])])]),
        "- alpha\n- beta",
    ],
    'bullet plain plus marker option' => [
        $doc([$bullet([$item([$plainText('alpha')])])]),
        '+ alpha',
        ['bulletListMarker' => 'plus'],
    ],
    'bullet plain star marker option' => [
        $doc([$bullet([$item([$plainText('alpha')])])]),
        '* alpha',
        ['bulletListMarker' => 'star'],
    ],
    'bullet empty plain item keeps blank marker' => [
        $doc([$bullet([$item([$plain([])])])]),
        '-',
    ],
    'bullet plain softbreak continuation' => [
        $doc([$bullet([$item([$plain([$text('alpha'), new AstNode('softbreak'), $text('beta')])])])]),
        "- alpha\n  beta",
    ],
    'bullet plain hardbreak continuation' => [
        $doc([$bullet([$item([$plain([$text('alpha'), new AstNode('linebreak'), $text('beta')])])])]),
        "- alpha\\\n  beta",
    ],
    'bullet plain inline code' => [
        $doc([$bullet([$item([$plain([$inlineCode('wp cli')])])])]),
        '- `wp cli`',
    ],
    'bullet plain emph and strong' => [
        $doc([$bullet([$item([$plain([$emph('em'), $text(' and '), $strong('strong')])])])]),
        '- *em* and **strong**',
    ],
    'bullet plain link label' => [
        $doc([$bullet([$item([$plain([$link('source', '/source')])])])]),
        '- [source](/source)',
    ],
    'bullet plain escapes ordered marker text' => [
        $doc([$bullet([$item([$plainText('1. not ordered')])])]),
        '- 1\. not ordered',
    ],
    'bullet plain escapes bullet marker text' => [
        $doc([$bullet([$item([$plainText('- not nested')])])]),
        '- \- not nested',
    ],
    'task plain unchecked item' => [
        $doc([$bullet([$item([$plainText('todo')], ['taskChecked' => false])])]),
        '- [ ] todo',
    ],
    'task plain checked item' => [
        $doc([$bullet([$item([$plainText('done')], ['taskChecked' => true])])]),
        '- [x] done',
    ],
    'task plain softbreak continuation' => [
        $doc([$bullet([$item([$plain([$text('done'), new AstNode('softbreak'), $text('continued')])], ['taskChecked' => true])])]),
        "- [x] done\n      continued",
    ],
    'task plain inline code' => [
        $doc([$bullet([$item([$plain([$text('run '), $inlineCode('wp test')])], ['taskChecked' => false])])]),
        '- [ ] run `wp test`',
    ],
    'bullet plain parent with nested bullet plain child' => [
        $doc([$bullet([$item([$plainText('parent'), $bullet([$item([$plainText('child')])])])])]),
        "- parent\n  - child",
    ],
    'bullet plain parent with nested ordered plain child' => [
        $doc([$bullet([$item([$plainText('parent'), $ordered([$item([$plainText('child')])])])])]),
        "- parent\n  1.  child",
    ],
    'bullet plain parent with nested task child' => [
        $doc([$bullet([$item([$plainText('parent'), $bullet([$item([$plainText('child')], ['taskChecked' => false])])])])]),
        "- parent\n  - [ ] child",
    ],
    'bullet plain then indented code block' => [
        $doc([$bullet([$item([$plainText('parent'), $code('echo alpha')])])]),
        "- parent\n      echo alpha",
    ],
    'bullet plain then fenced code block' => [
        $doc([$bullet([$item([$plainText('parent'), $code('echo alpha', ['classes' => ['php']])])])]),
        "- parent\n  ```php\n  echo alpha\n  ```",
    ],
    'bullet plain then blockquote' => [
        $doc([$bullet([$item([$plainText('parent'), $blockquote([$paragraph('quoted')])])])]),
        "- parent\n  > quoted",
    ],
    'bullet plain then line block' => [
        $doc([$bullet([$item([$plainText('parent'), $lineBlock([$line('first'), $line('second')])])])]),
        "- parent\n  | first\n  | second",
    ],
    'bullet plain then definition list' => [
        $doc([$bullet([$item([$plainText('parent'), $definitionList([
            $definitionItem($definitionTerm('Term'), [$definition([$paragraph('Definition')])]),
        ])])])]),
        "- parent\n  Term\n  :   Definition",
    ],
    'bullet plain then fenced div block' => [
        $doc([$bullet([$item([$plainText('parent'), $div([$paragraph('Body')], ['classes' => ['review']])])])]),
        "- parent\n  ::: {.review}\n  Body\n  :::",
    ],
    'bullet loose list with plain items' => [
        $doc([$bullet([$item([$plainText('one')]), $item([$plainText('two')])], ['loose' => true])]),
        "- one\n\n- two",
    ],
    'bullet loose plain item in tight list' => [
        $doc([$bullet([$item([$plainText('one')]), $item([$plainText('two')], ['loose' => true]), $item([$plainText('three')])])]),
        "- one\n\n- two\n- three",
    ],
    'bullet plain before paragraph continuation' => [
        $doc([$bullet([$item([$plainText('alpha'), $paragraph('beta')])])]),
        "- alpha\n\n  beta",
    ],
    'bullet paragraph before plain continuation' => [
        $doc([$bullet([$item([$paragraph('alpha'), $plainText('beta')])])]),
        "- alpha\n\n  beta",
    ],
    'bullet empty plain before nested bullet' => [
        $doc([$bullet([$item([$plain([]), $bullet([$item([$plainText('child')])])])])]),
        "-\n  - child",
    ],
    'bullet empty plain before code block' => [
        $doc([$bullet([$item([$plain([]), $code('echo alpha')])])]),
        "-\n      echo alpha",
    ],
    'ordered plain decimal item' => [
        $doc([$ordered([$item([$plainText('one')])])]),
        '1.  one',
    ],
    'ordered plain decimal two items' => [
        $doc([$ordered([$item([$plainText('one')]), $item([$plainText('two')])])]),
        "1.  one\n2.  two",
    ],
    'ordered plain decimal start offset' => [
        $doc([$ordered([$item([$plainText('four')]), $item([$plainText('five')])], ['start' => 4])]),
        "4.  four\n5.  five",
    ],
    'ordered plain one paren delimiter' => [
        $doc([$ordered([$item([$plainText('one')])], ['delimiter' => 'one_paren'])]),
        '1)  one',
    ],
    'ordered plain two parens delimiter' => [
        $doc([$ordered([$item([$plainText('one')])], ['delimiter' => 'two_parens'])]),
        '(1) one',
    ],
    'ordered plain lower alpha marker' => [
        $doc([$ordered([$item([$plainText('alpha')])], ['style' => 'lower_alpha'])]),
        'a.  alpha',
    ],
    'ordered plain upper alpha rollover marker' => [
        $doc([$ordered([$item([$plainText('upper')])], ['style' => 'upper_alpha', 'start' => 28])]),
        'AB. upper',
    ],
    'ordered plain lower roman marker' => [
        $doc([$ordered([$item([$plainText('four')])], ['style' => 'lower_roman', 'start' => 4])]),
        'iv. four',
    ],
    'ordered plain upper roman two parens marker' => [
        $doc([$ordered([$item([$plainText('nine')])], ['style' => 'upper_roman', 'delimiter' => 'two_parens', 'start' => 9])]),
        '(IX) nine',
    ],
    'ordered plain default period marker' => [
        $doc([$ordered([$item([$plainText('default')])], ['style' => 'default'])]),
        '#.  default',
    ],
    'ordered plain default paren marker' => [
        $doc([$ordered([$item([$plainText('default')])], ['style' => 'default', 'delimiter' => 'one_paren'])]),
        '#)  default',
    ],
    'ordered plain numbered example marker' => [
        $doc([$ordered([$item([$plainText('example')])], ['style' => 'example'])]),
        '(@) example',
    ],
    'ordered plain labeled numbered example marker' => [
        $doc([$ordered([$item([$plainText('labelled')], ['exampleLabel' => 'review'])], ['style' => 'example'])]),
        '(@review) labelled',
    ],
    'ordered task plain checked item' => [
        $doc([$ordered([$item([$plainText('done')], ['taskChecked' => true])])]),
        '1.  [x] done',
    ],
    'ordered task plain unchecked softbreak continuation' => [
        $doc([$ordered([$item([$plain([$text('todo'), new AstNode('softbreak'), $text('continued')])], ['taskChecked' => false])])]),
        "1.  [ ] todo\n        continued",
    ],
    'ordered plain parent with nested bullet' => [
        $doc([$ordered([$item([$plainText('parent'), $bullet([$item([$plainText('child')])])])])]),
        "1.  parent\n    - child",
    ],
    'ordered plain parent with nested ordered' => [
        $doc([$ordered([$item([$plainText('parent'), $ordered([$item([$plainText('child')])])])])]),
        "1.  parent\n    1.  child",
    ],
    'ordered plain then indented code block' => [
        $doc([$ordered([$item([$plainText('parent'), $code('echo alpha')])])]),
        "1.  parent\n        echo alpha",
    ],
    'ordered plain then fenced code block' => [
        $doc([$ordered([$item([$plainText('parent'), $code('echo alpha', ['classes' => ['php']])])])]),
        "1.  parent\n    ```php\n    echo alpha\n    ```",
    ],
    'ordered plain then blockquote' => [
        $doc([$ordered([$item([$plainText('parent'), $blockquote([$paragraph('quoted')])])])]),
        "1.  parent\n    > quoted",
    ],
    'ordered loose list with plain items' => [
        $doc([$ordered([$item([$plainText('one')]), $item([$plainText('two')])], ['loose' => true])]),
        "1.  one\n\n2.  two",
    ],
    'ordered loose plain item in tight list' => [
        $doc([$ordered([$item([$plainText('one')]), $item([$plainText('two')], ['loose' => true]), $item([$plainText('three')])])]),
        "1.  one\n\n2.  two\n3.  three",
    ],
    'ordered plain before paragraph continuation' => [
        $doc([$ordered([$item([$plainText('alpha'), $paragraph('beta')])])]),
        "1.  alpha\n\n    beta",
    ],
    'ordered empty plain before nested bullet' => [
        $doc([$ordered([$item([$plain([]), $bullet([$item([$plainText('child')])])])])]),
        "1.\n    - child",
    ],
];

$tests = [];

foreach ($plainListCases as $label => $case) {
    $tests['maps upstream markdown writer plain list completion surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            [$document, $expected, $options] = [$case[0], $case[1], $case[2] ?? []];

            $t->same($expected, (new MarkdownWriter($options))->write($document));
        };
}

return $tests;

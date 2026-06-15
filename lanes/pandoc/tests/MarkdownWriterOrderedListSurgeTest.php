<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$codeBlock = static fn (string $value): AstNode => new AstNode('code_block', ['text' => $value]);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$textItem = static fn (string $value, array $attrs = []): AstNode => $listItem([$text($value)], $attrs);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

/**
 * @return array{style:string, delimiter:string, start?:int, firstNumber?:int}
 */
$readerExpectation = static function (string $style, string $delimiter, int $start = 1, ?int $firstNumber = null): array {
    return [
        'style' => $style,
        'delimiter' => $delimiter,
        'start' => $start,
        'firstNumber' => $firstNumber ?? $start,
    ];
};

$orderedListSurgeCases = [
    'decimal period baseline keeps padded marker width' => [
        'doc' => $document([$orderedList([$textItem('one'), $textItem('two')])]),
        'expected' => "1.  one\n2.  two",
        'reader' => $readerExpectation('decimal', 'period'),
    ],
    'decimal constructor spelling normalizes' => [
        'doc' => $document([$orderedList([$textItem('three')], ['style' => 'Decimal', 'start' => 3])]),
        'expected' => '3.  three',
        'reader' => $readerExpectation('decimal', 'period', 3),
    ],
    'unknown ordered style falls back to decimal' => [
        'doc' => $document([$orderedList([$textItem('fallback')], ['style' => 'unsupported-style', 'start' => 6])]),
        'expected' => '6.  fallback',
        'reader' => $readerExpectation('decimal', 'period', 6),
    ],
    'empty ordered style falls back to decimal' => [
        'doc' => $document([$orderedList([$textItem('empty')], ['style' => '', 'start' => 4])]),
        'expected' => '4.  empty',
        'reader' => $readerExpectation('decimal', 'period', 4),
    ],
    'decimal zero start remains explicit' => [
        'doc' => $document([$orderedList([$textItem('zero')], ['style' => 'decimal', 'start' => 0])]),
        'expected' => '0.  zero',
        'reader' => $readerExpectation('decimal', 'period', 0),
    ],
    'decimal negative start clamps to zero marker' => [
        'doc' => $document([$orderedList([$textItem('negative')], ['style' => 'decimal', 'start' => -4])]),
        'expected' => '0.  negative',
        'reader' => $readerExpectation('decimal', 'period', 0),
    ],
    'decimal one paren delimiter' => [
        'doc' => $document([$orderedList([$textItem('three')], ['style' => 'decimal', 'delimiter' => 'one_paren', 'start' => 3])]),
        'expected' => '3)  three',
        'reader' => $readerExpectation('decimal', 'one_paren', 3),
    ],
    'decimal OneParen constructor delimiter' => [
        'doc' => $document([$orderedList([$textItem('four')], ['style' => 'Decimal', 'delimiter' => 'OneParen', 'start' => 4])]),
        'expected' => '4)  four',
        'reader' => $readerExpectation('decimal', 'one_paren', 4),
    ],
    'decimal hyphenated one paren delimiter' => [
        'doc' => $document([$orderedList([$textItem('five')], ['style' => 'decimal', 'delimiter' => 'one-paren', 'start' => 5])]),
        'expected' => '5)  five',
        'reader' => $readerExpectation('decimal', 'one_paren', 5),
    ],
    'decimal two parens delimiter' => [
        'doc' => $document([$orderedList([$textItem('six')], ['style' => 'decimal', 'delimiter' => 'two_parens', 'start' => 6])]),
        'expected' => '(6) six',
        'reader' => $readerExpectation('decimal', 'two_parens', 6),
    ],
    'decimal TwoParens constructor delimiter' => [
        'doc' => $document([$orderedList([$textItem('seven')], ['style' => 'Decimal', 'delimiter' => 'TwoParens', 'start' => 7])]),
        'expected' => '(7) seven',
        'reader' => $readerExpectation('decimal', 'two_parens', 7),
    ],
    'decimal unknown delimiter falls back to period' => [
        'doc' => $document([$orderedList([$textItem('period')], ['style' => 'decimal', 'delimiter' => 'unsupported', 'start' => 8])]),
        'expected' => '8.  period',
        'reader' => $readerExpectation('decimal', 'period', 8),
    ],
    'period equivalent default delimiter adjacent lists receive separator' => [
        'doc' => $document([
            $orderedList([$textItem('first')], ['style' => 'lower_alpha', 'delimiter' => 'DefaultDelim']),
            $orderedList([$textItem('second')], ['style' => 'lower_alpha', 'delimiter' => 'Period']),
        ]),
        'expected' => "a.  first\n\n<!-- -->\n\na.  second",
    ],
    'lower alpha period marker' => [
        'doc' => $document([$orderedList([$textItem('alpha')], ['style' => 'lower_alpha'])]),
        'expected' => 'a.  alpha',
        'reader' => $readerExpectation('lower_alpha', 'period'),
    ],
    'lower alpha hyphenated style marker' => [
        'doc' => $document([$orderedList([$textItem('beta')], ['style' => 'lower-alpha', 'start' => 2])]),
        'expected' => 'b.  beta',
        'reader' => $readerExpectation('lower_alpha', 'period', 2),
    ],
    'LowerAlpha constructor rollover marker' => [
        'doc' => $document([$orderedList([$textItem('rollover')], ['style' => 'LowerAlpha', 'start' => 27])]),
        'expected' => 'aa. rollover',
    ],
    'lower alpha one paren marker' => [
        'doc' => $document([$orderedList([$textItem('gamma')], ['style' => 'lower_alpha', 'delimiter' => 'one_paren', 'start' => 3])]),
        'expected' => 'c)  gamma',
        'reader' => $readerExpectation('lower_alpha', 'one_paren', 3),
    ],
    'lower alpha two parens marker' => [
        'doc' => $document([$orderedList([$textItem('delta')], ['style' => 'lower_alpha', 'delimiter' => 'two_parens', 'start' => 4])]),
        'expected' => '(d) delta',
    ],
    'upper alpha period marker' => [
        'doc' => $document([$orderedList([$textItem('alpha')], ['style' => 'upper_alpha'])]),
        'expected' => 'A.  alpha',
        'reader' => $readerExpectation('upper_alpha', 'period'),
    ],
    'upper alpha hyphenated style marker' => [
        'doc' => $document([$orderedList([$textItem('beta')], ['style' => 'upper-alpha', 'start' => 2])]),
        'expected' => 'B.  beta',
        'reader' => $readerExpectation('upper_alpha', 'period', 2),
    ],
    'UpperAlpha constructor rollover one paren marker' => [
        'doc' => $document([$orderedList([$textItem('rollover')], ['style' => 'UpperAlpha', 'delimiter' => 'OneParen', 'start' => 28])]),
        'expected' => 'AB) rollover',
    ],
    'upper alpha two parens marker' => [
        'doc' => $document([$orderedList([$textItem('gamma')], ['style' => 'upper_alpha', 'delimiter' => 'two_parens', 'start' => 3])]),
        'expected' => '(C) gamma',
    ],
    'lower roman period marker' => [
        'doc' => $document([$orderedList([$textItem('four')], ['style' => 'lower_roman', 'start' => 4])]),
        'expected' => 'iv. four',
        'reader' => $readerExpectation('lower_roman', 'period', 4),
    ],
    'LowerRoman constructor one paren marker' => [
        'doc' => $document([$orderedList([$textItem('nine')], ['style' => 'LowerRoman', 'delimiter' => 'one_paren', 'start' => 9])]),
        'expected' => 'ix) nine',
    ],
    'lower roman hyphenated two parens marker' => [
        'doc' => $document([$orderedList([$textItem('twelve')], ['style' => 'lower-roman', 'delimiter' => 'two-parens', 'start' => 12])]),
        'expected' => '(xii) twelve',
    ],
    'upper roman period marker' => [
        'doc' => $document([$orderedList([$textItem('four')], ['style' => 'upper_roman', 'start' => 4])]),
        'expected' => 'IV. four',
        'reader' => $readerExpectation('upper_roman', 'period', 4),
    ],
    'UpperRoman constructor one paren marker' => [
        'doc' => $document([$orderedList([$textItem('nine')], ['style' => 'UpperRoman', 'delimiter' => 'OneParen', 'start' => 9])]),
        'expected' => 'IX) nine',
    ],
    'upper roman hyphenated two parens marker' => [
        'doc' => $document([$orderedList([$textItem('twelve')], ['style' => 'upper-roman', 'delimiter' => 'TwoParens', 'start' => 12])]),
        'expected' => '(XII) twelve',
    ],
    'lower alpha large rollover marker' => [
        'doc' => $document([$orderedList([$textItem('az')], ['style' => 'lower_alpha', 'start' => 52])]),
        'expected' => 'az. az',
    ],
    'upper alpha large rollover marker' => [
        'doc' => $document([$orderedList([$textItem('BA')], ['style' => 'UpperAlpha', 'start' => 53])]),
        'expected' => 'BA. BA',
    ],
    'default style emits pandoc auto marker' => [
        'doc' => $document([$orderedList([$textItem('auto')], ['style' => 'default'])]),
        'expected' => '#.  auto',
        'reader' => $readerExpectation('default', 'default'),
    ],
    'DefaultStyle constructor emits pandoc auto marker' => [
        'doc' => $document([$orderedList([$textItem('auto')], ['style' => 'DefaultStyle'])]),
        'expected' => '#.  auto',
        'reader' => $readerExpectation('default', 'default'),
    ],
    'default style ignores start for auto marker' => [
        'doc' => $document([$orderedList([$textItem('auto')], ['style' => 'default', 'start' => 9])]),
        'expected' => '#.  auto',
        'reader' => $readerExpectation('default', 'default'),
    ],
    'default style one paren marker' => [
        'doc' => $document([$orderedList([$textItem('auto')], ['style' => 'default', 'delimiter' => 'one_paren'])]),
        'expected' => '#)  auto',
        'reader' => $readerExpectation('default', 'default'),
    ],
    'DefaultStyle OneParen constructor marker' => [
        'doc' => $document([$orderedList([$textItem('auto')], ['style' => 'DefaultStyle', 'delimiter' => 'OneParen'])]),
        'expected' => '#)  auto',
        'reader' => $readerExpectation('default', 'default'),
    ],
    'DefaultStyle DefaultDelim constructor marker' => [
        'doc' => $document([$orderedList([$textItem('auto')], ['style' => 'DefaultStyle', 'delimiter' => 'DefaultDelim'])]),
        'expected' => '#.  auto',
        'reader' => $readerExpectation('default', 'default'),
    ],
    'default style unsupported delimiter falls back to period marker' => [
        'doc' => $document([$orderedList([$textItem('auto')], ['style' => 'default', 'delimiter' => 'unsupported'])]),
        'expected' => '#.  auto',
        'reader' => $readerExpectation('default', 'default'),
    ],
    'default style separates from decimal list without html comment' => [
        'doc' => $document([
            $orderedList([$textItem('decimal')]),
            $orderedList([$textItem('auto')], ['style' => 'default']),
        ]),
        'expected' => "1.  decimal\n\n#.  auto",
    ],
    'example style emits numbered example marker' => [
        'doc' => $document([$orderedList([$textItem('first example')], ['style' => 'example'])]),
        'expected' => '(@) first example',
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'Example constructor emits numbered example marker' => [
        'doc' => $document([$orderedList([$textItem('constructor example')], ['style' => 'Example'])]),
        'expected' => '(@) constructor example',
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example delimiter is forced to numbered example marker' => [
        'doc' => $document([$orderedList([$textItem('forced example')], ['style' => 'example', 'delimiter' => 'Period'])]),
        'expected' => '(@) forced example',
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example start is assigned by reader sequence' => [
        'doc' => $document([$orderedList([$textItem('first'), $textItem('second')], ['style' => 'example', 'start' => 4])]),
        'expected' => "(@) first\n(@) second",
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example item exampleLabel emits labeled marker' => [
        'doc' => $document([$orderedList([$textItem('labeled', ['exampleLabel' => 'review-1'])], ['style' => 'example'])]),
        'expected' => '(@review-1) labeled',
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example item label emits labeled marker' => [
        'doc' => $document([$orderedList([$textItem('labeled', ['label' => 'queue_2'])], ['style' => 'example'])]),
        'expected' => '(@queue_2) labeled',
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example unsafe label falls back to unlabeled marker' => [
        'doc' => $document([$orderedList([$textItem('unsafe', ['exampleLabel' => 'bad label'])], ['style' => 'example'])]),
        'expected' => '(@) unsafe',
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example empty label falls back to unlabeled marker' => [
        'doc' => $document([$orderedList([$textItem('empty', ['exampleLabel' => ''])], ['style' => 'Example'])]),
        'expected' => '(@) empty',
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example labeled references resolve through reader handoff' => [
        'doc' => $document([
            $orderedList([$textItem('first'), $textItem('second', ['exampleLabel' => 'second-example'])], ['style' => 'example']),
            $paragraph('See (@second-example).'),
        ]),
        'expected' => "(@) first\n(@second-example) second\n\nSee (@second-example).",
        'referenceText' => 'See (2).',
    ],
    'example list followed by decimal list needs no separator' => [
        'doc' => $document([
            $orderedList([$textItem('example')], ['style' => 'example']),
            $orderedList([$textItem('decimal')]),
        ]),
        'expected' => "(@) example\n\n1.  decimal",
    ],
    'adjacent example lists receive separator' => [
        'doc' => $document([
            $orderedList([$textItem('first')], ['style' => 'example']),
            $orderedList([$textItem('second')], ['style' => 'Example']),
        ]),
        'expected' => "(@) first\n\n<!-- -->\n\n(@) second",
    ],
    'example task item keeps task marker after example marker' => [
        'doc' => $document([$orderedList([$textItem('done', ['taskChecked' => true])], ['style' => 'example'])]),
        'expected' => '(@) [x] done',
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example paragraph item keeps continuation indent' => [
        'doc' => $document([$orderedList([$listItem([$paragraph('alpha'), $paragraph('beta')])], ['style' => 'example'])]),
        'expected' => "(@) alpha\n\n    beta",
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example code item keeps continuation indent' => [
        'doc' => $document([$orderedList([$listItem([$text('alpha'), $codeBlock('echo alpha')])], ['style' => 'example'])]),
        'expected' => "(@) alpha\n        echo alpha",
    ],
    'default style code item keeps continuation indent' => [
        'doc' => $document([$orderedList([$listItem([$text('auto'), $codeBlock('echo auto')])], ['style' => 'DefaultStyle'])]),
        'expected' => "#.  auto\n        echo auto",
    ],
    'upper alpha nested bullet keeps normalized outer marker' => [
        'doc' => $document([$orderedList([
            $listItem([$text('outer'), $bulletList([$textItem('inner')])]),
        ], ['style' => 'UpperAlpha', 'delimiter' => 'OneParen', 'start' => 2])]),
        'expected' => "B)  outer\n    - inner",
        'reader' => $readerExpectation('upper_alpha', 'one_paren', 2),
    ],
    'default nested bullet keeps auto marker' => [
        'doc' => $document([$orderedList([
            $listItem([$text('outer'), $bulletList([$textItem('inner')])]),
        ], ['style' => 'DefaultStyle'])]),
        'expected' => "#.  outer\n    - inner",
        'reader' => $readerExpectation('default', 'default'),
    ],
    'example nested bullet keeps example marker' => [
        'doc' => $document([$orderedList([
            $listItem([$text('outer'), $bulletList([$textItem('inner')])]),
        ], ['style' => 'Example'])]),
        'expected' => "(@) outer\n    - inner",
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'example blockquote body keeps example marker' => [
        'doc' => $document([$orderedList([
            $listItem([$text('quoted'), $blockquote([$paragraph('inside')])]),
        ], ['style' => 'example'])]),
        'expected' => "(@) quoted\n    > inside",
    ],
    'upper roman blockquote body keeps constructor marker' => [
        'doc' => $document([$orderedList([
            $listItem([$text('quoted'), $blockquote([$paragraph('inside')])]),
        ], ['style' => 'UpperRoman', 'delimiter' => 'Period', 'start' => 6])]),
        'expected' => "VI. quoted\n    > inside",
        'reader' => $readerExpectation('upper_roman', 'period', 6),
    ],
    'lower alpha loose list keeps normalized markers' => [
        'doc' => $document([$orderedList([$textItem('one'), $textItem('two')], ['style' => 'LowerAlpha', 'loose' => true])]),
        'expected' => "a.  one\n\nb.  two",
        'reader' => $readerExpectation('lower_alpha', 'period'),
    ],
    'example loose list keeps numbered example markers' => [
        'doc' => $document([$orderedList([$textItem('one'), $textItem('two')], ['style' => 'Example', 'loose' => true])]),
        'expected' => "(@) one\n\n(@) two",
    ],
    'default loose list keeps auto markers' => [
        'doc' => $document([$orderedList([$textItem('one'), $textItem('two')], ['style' => 'DefaultStyle', 'loose' => true])]),
        'expected' => "#.  one\n\n#.  two",
        'reader' => $readerExpectation('default', 'default'),
    ],
    'example labels stay unique across adjacent items' => [
        'doc' => $document([$orderedList([
            $textItem('alpha', ['exampleLabel' => 'alpha']),
            $textItem('beta', ['exampleLabel' => 'beta']),
        ], ['style' => 'Example'])]),
        'expected' => "(@alpha) alpha\n(@beta) beta",
        'reader' => $readerExpectation('example', 'two_parens'),
    ],
    'default marker followed by code block still gets separator' => [
        'doc' => $document([
            $orderedList([$textItem('auto')], ['style' => 'DefaultStyle']),
            $codeBlock('echo auto'),
        ]),
        'expected' => "#.  auto\n\n<!-- -->\n\n    echo auto",
    ],
];

$tests = [];
foreach ($orderedListSurgeCases as $label => $case) {
    $tests['maps upstream markdown writer ordered list marker surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter())->write($case['doc']);
            $t->same($case['expected'], $markdown);

            if (isset($case['reader'])) {
                $roundTrip = (new MarkdownReader())->read($markdown);
                $list = $roundTrip->children[0] ?? new AstNode('missing');
                $firstItem = $list->children[0] ?? new AstNode('missing');

                $t->same('ordered_list', $list->type, $markdown);
                $t->same($case['reader']['style'], $list->attr('style'), $markdown);
                $t->same($case['reader']['delimiter'], $list->attr('delimiter'), $markdown);
                $t->same($case['reader']['start'], $list->attr('start'), $markdown);
                $t->same($case['reader']['firstNumber'], $firstItem->attr('number'), $markdown);
            }

            if (isset($case['referenceText'])) {
                $roundTrip = (new MarkdownReader())->read($markdown);
                $paragraph = $roundTrip->children[1] ?? new AstNode('missing');
                $t->same($case['referenceText'], $paragraph->attr('text'), $markdown);
            }
        };
}

return $tests;

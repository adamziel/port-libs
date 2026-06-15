<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$plainText = static fn (string $value): AstNode => $plain([$text($value)]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$emph = static fn (string $value): AstNode => new AstNode('emph', [], [$text($value)]);
$strong = static fn (string $value): AstNode => new AstNode('strong', [], [$text($value)]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_replace(['text' => $value], $attrs)
);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$plainItem = static fn (string $value, array $attrs = []): AstNode => $listItem([$plainText($value)], $attrs);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

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
    $parts = [];
    foreach ($item->children as $child) {
        if (in_array($child->type, ['bullet_list', 'ordered_list', 'definition_list', 'code_block', 'blockquote'], true)) {
            continue;
        }

        $part = trim($inlineText($child));
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return trim(implode(' ', $parts));
};

$cases = [
    '01 bullet plain compact item' => [
        'doc' => $document([$bulletList([$plainItem('alpha')])]),
        'expected' => '- alpha',
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    '02 bullet plain compact siblings' => [
        'doc' => $document([$bulletList([$plainItem('alpha'), $plainItem('beta')])]),
        'expected' => "- alpha\n- beta",
        'type' => 'bullet_list',
        'items' => ['alpha', 'beta'],
    ],
    '03 plus bullet plain compact item' => [
        'doc' => $document([$bulletList([$plainItem('alpha')])]),
        'options' => ['bulletListMarker' => 'plus'],
        'expected' => '+ alpha',
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    '04 star bullet plain compact item' => [
        'doc' => $document([$bulletList([$plainItem('alpha')])]),
        'options' => ['bulletListMarker' => 'star'],
        'expected' => '* alpha',
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    '05 bullet plain softbreak continuation' => [
        'doc' => $document([$bulletList([$listItem([$plain([$text('alpha'), $softbreak(), $text('beta')])])])]),
        'expected' => "- alpha\n  beta",
        'type' => 'bullet_list',
        'items' => ['alpha beta'],
    ],
    '06 bullet plain hardbreak continuation' => [
        'doc' => $document([$bulletList([$listItem([$plain([$text('alpha'), $linebreak(), $text('beta')])])])]),
        'expected' => "- alpha\\\n  beta",
        'type' => 'bullet_list',
        'items' => ['alpha\\ beta'],
    ],
    '07 bullet plain inline emphasis survives compact marker' => [
        'doc' => $document([$bulletList([$listItem([$plain([$emph('alpha'), $text(' and '), $strong('beta')])])])]),
        'expected' => '- *alpha* and **beta**',
        'type' => 'bullet_list',
        'items' => ['alpha and beta'],
    ],
    '08 bullet plain inline code survives compact marker' => [
        'doc' => $document([$bulletList([$listItem([$plain([$text('run '), $code('wp cli')])])])]),
        'expected' => '- run `wp cli`',
        'type' => 'bullet_list',
        'items' => ['run wp cli'],
    ],
    '09 empty bullet plain remains a real item' => [
        'doc' => $document([$bulletList([$listItem([$plain([])])])]),
        'expected' => '-',
        'type' => 'bullet_list',
        'items' => [''],
    ],
    '10 bullet plain list-looking text stays literal' => [
        'doc' => $document([$bulletList([$plainItem('- imported marker')])]),
        'expected' => '- \\- imported marker',
        'type' => 'bullet_list',
        'items' => ['- imported marker'],
    ],
    '11 unchecked task plain compact item' => [
        'doc' => $document([$bulletList([$plainItem('todo', ['taskChecked' => false])])]),
        'expected' => '- [ ] todo',
        'type' => 'bullet_list',
        'items' => ['todo'],
        'tasks' => [false],
    ],
    '12 checked task plain compact item' => [
        'doc' => $document([$bulletList([$plainItem('done', ['taskChecked' => true])])]),
        'expected' => '- [x] done',
        'type' => 'bullet_list',
        'items' => ['done'],
        'tasks' => [true],
    ],
    '13 plus task plain compact item' => [
        'doc' => $document([$bulletList([$plainItem('todo', ['taskChecked' => false])])]),
        'options' => ['bulletListMarker' => 'plus'],
        'expected' => '+ [ ] todo',
        'type' => 'bullet_list',
        'items' => ['todo'],
        'tasks' => [false],
    ],
    '14 task plain softbreak continuation aligns after checkbox' => [
        'doc' => $document([$bulletList([$listItem([$plain([$text('alpha'), $softbreak(), $text('beta')])], ['taskChecked' => true])])]),
        'expected' => "- [x] alpha\n  beta",
        'type' => 'bullet_list',
        'items' => ['alpha beta'],
        'tasks' => [true],
    ],
    '15 task plain nested bullet remains under item' => [
        'doc' => $document([$bulletList([$listItem([$plainText('parent'), $bulletList([$plainItem('child')])], ['taskChecked' => false])])]),
        'expected' => "- [ ] parent\n  - child",
        'type' => 'bullet_list',
        'items' => ['parent'],
        'tasks' => [false],
        'nested' => ['type' => 'bullet_list', 'items' => ['child']],
    ],
    '16 task plain indented code remains under item' => [
        'doc' => $document([$bulletList([$listItem([$plainText('parent'), $codeBlock('echo task')], ['taskChecked' => true])])]),
        'expected' => "- [x] parent\n      echo task",
        'type' => 'bullet_list',
        'items' => ['parent'],
        'tasks' => [true],
        'nested' => ['type' => 'code_block', 'text' => 'echo task'],
    ],
    '17 task plain second block makes loose item' => [
        'doc' => $document([$bulletList([$listItem([$plainText('first'), $plainText('second')], ['taskChecked' => false])])]),
        'expected' => "- [ ] first\n\n  second",
        'type' => 'bullet_list',
        'items' => ['first second'],
        'tasks' => [false],
        'loose' => true,
    ],
    '18 two task plain items stay compact siblings' => [
        'doc' => $document([$bulletList([$plainItem('todo', ['taskChecked' => false]), $plainItem('done', ['taskChecked' => true])])]),
        'expected' => "- [ ] todo\n- [x] done",
        'type' => 'bullet_list',
        'items' => ['todo', 'done'],
        'tasks' => [false, true],
    ],
    '19 ordered decimal plain compact item' => [
        'doc' => $document([$orderedList([$plainItem('one')])]),
        'expected' => '1.  one',
        'type' => 'ordered_list',
        'items' => ['one'],
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    ],
    '20 ordered decimal plain start offset' => [
        'doc' => $document([$orderedList([$plainItem('three')], ['start' => 3])]),
        'expected' => '3.  three',
        'type' => 'ordered_list',
        'items' => ['three'],
        'attrs' => ['start' => 3, 'style' => 'decimal', 'delimiter' => 'period'],
    ],
    '21 ordered decimal one paren plain item' => [
        'doc' => $document([$orderedList([$plainItem('one')], ['delimiter' => 'one_paren'])]),
        'expected' => '1)  one',
        'type' => 'ordered_list',
        'items' => ['one'],
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'one_paren'],
    ],
    '22 ordered decimal two parens plain item' => [
        'doc' => $document([$orderedList([$plainItem('one')], ['delimiter' => 'two_parens'])]),
        'expected' => '(1) one',
        'type' => 'ordered_list',
        'items' => ['one'],
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'two_parens'],
    ],
    '23 ordered lower alpha plain item' => [
        'doc' => $document([$orderedList([$plainItem('beta')], ['style' => 'lower_alpha', 'start' => 2])]),
        'expected' => 'b.  beta',
        'type' => 'ordered_list',
        'items' => ['beta'],
        'attrs' => ['start' => 2, 'style' => 'lower_alpha', 'delimiter' => 'period'],
    ],
    '24 ordered upper alpha plain item' => [
        'doc' => $document([$orderedList([$plainItem('beta')], ['style' => 'upper_alpha', 'start' => 2])]),
        'expected' => 'B.  beta',
        'type' => 'ordered_list',
        'items' => ['beta'],
        'attrs' => ['start' => 2, 'style' => 'upper_alpha', 'delimiter' => 'period'],
    ],
    '25 ordered lower roman plain item' => [
        'doc' => $document([$orderedList([$plainItem('four')], ['style' => 'lower_roman', 'start' => 4])]),
        'expected' => 'iv. four',
        'type' => 'ordered_list',
        'items' => ['four'],
        'attrs' => ['start' => 4, 'style' => 'lower_roman', 'delimiter' => 'period'],
    ],
    '26 ordered upper roman plain item' => [
        'doc' => $document([$orderedList([$plainItem('four')], ['style' => 'upper_roman', 'start' => 4])]),
        'expected' => 'IV. four',
        'type' => 'ordered_list',
        'items' => ['four'],
        'attrs' => ['start' => 4, 'style' => 'upper_roman', 'delimiter' => 'period'],
    ],
    '27 ordered default plain item' => [
        'doc' => $document([$orderedList([$plainItem('auto')], ['style' => 'default'])]),
        'expected' => '#.  auto',
        'type' => 'ordered_list',
        'items' => ['auto'],
        'attrs' => ['start' => 1, 'style' => 'default', 'delimiter' => 'default'],
    ],
    '28 ordered default one paren plain item' => [
        'doc' => $document([$orderedList([$plainItem('auto')], ['style' => 'default', 'delimiter' => 'one_paren'])]),
        'expected' => '#)  auto',
        'type' => 'ordered_list',
        'items' => ['auto'],
        'attrs' => ['start' => 1, 'style' => 'default', 'delimiter' => 'default'],
    ],
    '29 numbered example plain item' => [
        'doc' => $document([$orderedList([$plainItem('example')], ['style' => 'example'])]),
        'expected' => '(@) example',
        'type' => 'ordered_list',
        'items' => ['example'],
        'attrs' => ['start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
    ],
    '30 numbered example plain item label attribute' => [
        'doc' => $document([$orderedList([$plainItem('example', ['exampleLabel' => 'review'])], ['style' => 'example'])]),
        'expected' => '(@review) example',
        'type' => 'ordered_list',
        'items' => ['example'],
        'attrs' => ['start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
    ],
    '31 numbered example plain item legacy label' => [
        'doc' => $document([$orderedList([$plainItem('example', ['label' => 'legacy'])], ['style' => 'example'])]),
        'expected' => '(@legacy) example',
        'type' => 'ordered_list',
        'items' => ['example'],
        'attrs' => ['start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
    ],
    '32 numbered example plain item data label' => [
        'doc' => $document([$orderedList([$plainItem('example', ['attributes' => ['data-example-label' => 'data-review']])], ['style' => 'example'])]),
        'expected' => '(@data-review) example',
        'type' => 'ordered_list',
        'items' => ['example'],
        'attrs' => ['start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
    ],
    '33 ordered task plain item' => [
        'doc' => $document([$orderedList([$plainItem('todo', ['taskChecked' => false])])]),
        'expected' => '1.  [ ] todo',
        'type' => 'ordered_list',
        'items' => ['todo'],
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
        'tasks' => [false],
    ],
    '34 numbered example task plain item' => [
        'doc' => $document([$orderedList([$plainItem('done', ['taskChecked' => true])], ['style' => 'example'])]),
        'expected' => '(@) [x] done',
        'type' => 'ordered_list',
        'items' => ['done'],
        'attrs' => ['start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
        'tasks' => [true],
    ],
    '35 lower alpha loose plain list' => [
        'doc' => $document([$orderedList([$plainItem('alpha'), $plainItem('beta')], ['style' => 'lower_alpha', 'loose' => true])]),
        'expected' => "a.  alpha\n\nb.  beta",
        'type' => 'ordered_list',
        'items' => ['alpha', 'beta'],
        'attrs' => ['start' => 1, 'style' => 'lower_alpha', 'delimiter' => 'period'],
        'loose' => true,
    ],
    '36 adjacent same ordered plain lists receive separator' => [
        'doc' => $document([$orderedList([$plainItem('one')]), $orderedList([$plainItem('two')])]),
        'expected' => "1.  one\n\n<!-- -->\n\n1.  two",
        'type' => 'ordered_list',
        'items' => ['one'],
        'blockTypes' => ['ordered_list', 'ordered_list'],
    ],
    '37 adjacent different ordered plain styles avoid separator' => [
        'doc' => $document([$orderedList([$plainItem('one')]), $orderedList([$plainItem('alpha')], ['style' => 'lower_alpha'])]),
        'expected' => "1.  one\n\na.  alpha",
        'type' => 'ordered_list',
        'items' => ['one'],
        'blockTypes' => ['ordered_list', 'ordered_list'],
    ],
    '38 numbered example plain reference resolves after round trip' => [
        'doc' => $document([$orderedList([$plainItem('example', ['exampleLabel' => 'review'])], ['style' => 'example']), $paragraph('See (@review).')]),
        'expected' => "(@review) example\n\nSee (@review).",
        'type' => 'ordered_list',
        'items' => ['example'],
        'attrs' => ['start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
        'referenceText' => 'See (1).',
    ],
    '39 bullet plain parent nested bullet' => [
        'doc' => $document([$bulletList([$listItem([$plainText('parent'), $bulletList([$plainItem('child')])])])]),
        'expected' => "- parent\n  - child",
        'type' => 'bullet_list',
        'items' => ['parent'],
        'nested' => ['type' => 'bullet_list', 'items' => ['child']],
    ],
    '40 bullet plain parent nested ordered' => [
        'doc' => $document([$bulletList([$listItem([$plainText('parent'), $orderedList([$plainItem('child')])])])]),
        'expected' => "- parent\n  1.  child",
        'type' => 'bullet_list',
        'items' => ['parent'],
        'nested' => ['type' => 'ordered_list', 'items' => ['child'], 'attrs' => ['style' => 'decimal']],
    ],
    '41 bullet plain parent nested numbered example' => [
        'doc' => $document([$bulletList([$listItem([$plainText('parent'), $orderedList([$plainItem('child')], ['style' => 'example'])])])]),
        'expected' => "- parent\n  (@) child",
        'type' => 'bullet_list',
        'items' => ['parent'],
        'nested' => ['type' => 'ordered_list', 'items' => ['child'], 'attrs' => ['style' => 'example']],
    ],
    '42 ordered plain parent nested bullet' => [
        'doc' => $document([$orderedList([$listItem([$plainText('parent'), $bulletList([$plainItem('child')])])])]),
        'expected' => "1.  parent\n    - child",
        'type' => 'ordered_list',
        'items' => ['parent'],
        'nested' => ['type' => 'bullet_list', 'items' => ['child']],
    ],
    '43 ordered plain parent indented code' => [
        'doc' => $document([$orderedList([$listItem([$plainText('parent'), $codeBlock('echo ordered')])])]),
        'expected' => "1.  parent\n        echo ordered",
        'type' => 'ordered_list',
        'items' => ['parent'],
        'nested' => ['type' => 'code_block', 'text' => 'echo ordered'],
    ],
    '44 ordered plain parent blockquote' => [
        'doc' => $document([$orderedList([$listItem([$plainText('parent'), $blockquote([$paragraph('quote')])])])]),
        'expected' => "1.  parent\n    > quote",
        'type' => 'ordered_list',
        'items' => ['parent'],
        'nested' => ['type' => 'blockquote', 'text' => 'quote'],
    ],
    '45 bullet plain parent attributed fenced code' => [
        'doc' => $document([$bulletList([$listItem([$plainText('parent'), $codeBlock('echo fenced', ['classes' => ['php']])])])]),
        'expected' => "- parent\n  ```php\n  echo fenced\n  ```",
        'type' => 'bullet_list',
        'items' => ['parent'],
        'nested' => ['type' => 'code_block', 'text' => 'echo fenced', 'classes' => ['php']],
    ],
    '46 bullet plain parent fenced code option' => [
        'doc' => $document([$bulletList([$listItem([$plainText('parent'), $codeBlock('echo fenced')])])]),
        'options' => ['fencedCodeBlocks' => true],
        'expected' => "- parent\n  ```\n  echo fenced\n  ```",
        'type' => 'bullet_list',
        'items' => ['parent'],
        'nested' => ['type' => 'code_block', 'text' => 'echo fenced'],
    ],
    '47 bullet plain parent blockquote' => [
        'doc' => $document([$bulletList([$listItem([$plainText('parent'), $blockquote([$paragraph('quote')])])])]),
        'expected' => "- parent\n  > quote",
        'type' => 'bullet_list',
        'items' => ['parent'],
        'nested' => ['type' => 'blockquote', 'text' => 'quote'],
    ],
    '48 bullet plain then paragraph block' => [
        'doc' => $document([$bulletList([$listItem([$plainText('first'), $paragraph('second')])])]),
        'expected' => "- first\n\n  second",
        'type' => 'bullet_list',
        'items' => ['first second'],
        'loose' => true,
    ],
    '49 bullet two plain blocks stay loose item' => [
        'doc' => $document([$bulletList([$listItem([$plainText('first'), $plainText('second')])])]),
        'expected' => "- first\n\n  second",
        'type' => 'bullet_list',
        'items' => ['first second'],
        'loose' => true,
    ],
    '50 empty bullet plain owns nested bullet' => [
        'doc' => $document([$bulletList([$listItem([$plain([]), $bulletList([$plainItem('child')])])])]),
        'expected' => "-\n  - child",
        'type' => 'bullet_list',
        'items' => [''],
        'nested' => ['type' => 'bullet_list', 'items' => ['child']],
    ],
    '51 numbered example plain parent indented code' => [
        'doc' => $document([$orderedList([$listItem([$plainText('parent'), $codeBlock('echo example')])], ['style' => 'example'])]),
        'expected' => "(@) parent\n        echo example",
        'type' => 'ordered_list',
        'items' => ['parent'],
        'attrs' => ['style' => 'example'],
        'nested' => ['type' => 'code_block', 'text' => 'echo example'],
    ],
    '52 task plain parent blockquote' => [
        'doc' => $document([$bulletList([$listItem([$plainText('parent'), $blockquote([$paragraph('quote')])], ['taskChecked' => true])])]),
        'expected' => "- [x] parent\n  > quote",
        'type' => 'bullet_list',
        'items' => ['parent'],
        'tasks' => [true],
        'nested' => ['type' => 'blockquote', 'text' => 'quote'],
    ],
    '53 bullet plain list before indented code separator' => [
        'doc' => $document([$bulletList([$plainItem('alpha')]), $codeBlock('echo alpha')]),
        'expected' => "- alpha\n\n<!-- -->\n\n    echo alpha",
        'type' => 'bullet_list',
        'items' => ['alpha'],
        'blockTypes' => ['bullet_list', 'code_block'],
    ],
    '54 ordered plain list before indented code separator' => [
        'doc' => $document([$orderedList([$plainItem('alpha')]), $codeBlock('echo alpha')]),
        'expected' => "1.  alpha\n\n<!-- -->\n\n    echo alpha",
        'type' => 'ordered_list',
        'items' => ['alpha'],
        'blockTypes' => ['ordered_list', 'code_block'],
    ],
    '55 adjacent bullet plain lists receive separator' => [
        'doc' => $document([$bulletList([$plainItem('alpha')]), $bulletList([$plainItem('beta')])]),
        'expected' => "- alpha\n\n<!-- -->\n\n- beta",
        'type' => 'bullet_list',
        'items' => ['alpha'],
        'blockTypes' => ['bullet_list', 'bullet_list'],
    ],
    '56 adjacent definition-style bullet plain handoff keeps separator' => [
        'doc' => $document([$bulletList([$plainItem('term')]), $bulletList([$plainItem('definition')], ['loose' => true])]),
        'expected' => "- term\n\n<!-- -->\n\n- definition",
        'type' => 'bullet_list',
        'items' => ['term'],
        'blockTypes' => ['bullet_list', 'bullet_list'],
    ],
    '57 bullet plain atx-looking text stays item text' => [
        'doc' => $document([$bulletList([$plainItem('# imported heading')])]),
        'expected' => '- \\# imported heading',
        'type' => 'bullet_list',
        'items' => ['# imported heading'],
    ],
    '58 ordered plain numeric-looking text stays item text' => [
        'doc' => $document([$orderedList([$plainItem('1. imported marker')])]),
        'expected' => '1.  1\\. imported marker',
        'type' => 'ordered_list',
        'items' => ['1. imported marker'],
    ],
    '59 bullet plain fenced-div-looking text stays item text' => [
        'doc' => $document([$bulletList([$plainItem('::: imported div')])]),
        'expected' => '- \\::: imported div',
        'type' => 'bullet_list',
        'items' => ['::: imported div'],
    ],
    '60 bullet plain colon text stays compact item text' => [
        'doc' => $document([$bulletList([$plainItem('alpha: imported rule')])]),
        'expected' => '- alpha: imported rule',
        'type' => 'bullet_list',
        'items' => ['alpha: imported rule'],
    ],
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['maps upstream markdown writer plain list item surge ' . $name] =
        static function (TestRunner $t) use ($case, $listItemText, $inlineText): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['doc']);
            $t->same($case['expected'], $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            if (isset($case['blockTypes'])) {
                $t->same($case['blockTypes'], array_map(static fn (AstNode $node): string => $node->type, $roundTrip->children));
            }

            $list = $roundTrip->children[0] ?? new AstNode('missing');
            $t->same($case['type'], $list->type, $markdown);
            $t->same($case['items'], array_map($listItemText, $list->children), $markdown);
            $t->same((bool) ($case['loose'] ?? false), (bool) $list->attr('loose'), $markdown);

            foreach (($case['attrs'] ?? []) as $attr => $value) {
                $t->same($value, $list->attr($attr), $markdown);
            }

            if (isset($case['tasks'])) {
                $t->same($case['tasks'], array_map(
                    static fn (AstNode $item): ?bool => $item->attr('taskChecked', null),
                    $list->children
                ), $markdown);
            }

            if (isset($case['nested'])) {
                $nestedCase = $case['nested'];
                $nested = null;
                foreach (($list->children[0] ?? new AstNode('missing'))->children as $child) {
                    if ($child->type === $nestedCase['type']) {
                        $nested = $child;
                        break;
                    }
                }

                $t->true($nested instanceof AstNode, 'Expected nested ' . $nestedCase['type'] . " in:\n" . $markdown);
                if ($nested instanceof AstNode) {
                    $t->same($nestedCase['type'], $nested->type, $markdown);
                    if (isset($nestedCase['items'])) {
                        $t->same($nestedCase['items'], array_map($listItemText, $nested->children), $markdown);
                    }
                    if (isset($nestedCase['text'])) {
                        $actualText = $nested->type === 'code_block'
                            ? (string) $nested->attr('text', '')
                            : trim($inlineText($nested));
                        $t->same($nestedCase['text'], $actualText, $markdown);
                    }
                    if (isset($nestedCase['classes'])) {
                        $t->same($nestedCase['classes'], $nested->attr('classes'), $markdown);
                    }
                    foreach (($nestedCase['attrs'] ?? []) as $attr => $value) {
                        $t->same($value, $nested->attr($attr), $markdown);
                    }
                }
            }

            if (isset($case['referenceText'])) {
                $paragraph = $roundTrip->children[1] ?? new AstNode('missing');
                $t->same($case['referenceText'], $paragraph->attr('text'), $markdown);
            }
        };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_replace(['text' => $value], $attrs)
);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$line = static fn (string $value = ''): AstNode => $value === ''
    ? new AstNode('line')
    : new AstNode('line', [], [$text($value)]);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$plainItem = static fn (string $value, array $attrs = []): AstNode => $listItem([$plain([$text($value)])], $attrs);

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

$leadingItemText = static function (AstNode $item) use ($inlineText): string {
    $text = '';
    foreach ($item->children as $child) {
        if (in_array($child->type, ['bullet_list', 'ordered_list', 'definition_list', 'code_block', 'blockquote', 'line_block'], true)) {
            break;
        }

        if ($child->type === 'paragraph' || $child->type === 'plain') {
            return trim($inlineText($child));
        }

        $text .= $inlineText($child);
    }

    return trim($text);
};

$tests = [];

$blockCases = [
    'bullet plain item writes after marker' => [
        'document' => $document([$bulletList([$plainItem('alpha')])]),
        'expected' => '- alpha',
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    'bullet plain two items stay compact' => [
        'document' => $document([$bulletList([$plainItem('alpha'), $plainItem('beta')])]),
        'expected' => "- alpha\n- beta",
        'type' => 'bullet_list',
        'items' => ['alpha', 'beta'],
    ],
    'bullet plain plus marker option' => [
        'document' => $document([$bulletList([$plainItem('alpha'), $plainItem('beta')])]),
        'expected' => "+ alpha\n+ beta",
        'options' => ['bulletListMarker' => 'plus'],
        'type' => 'bullet_list',
        'items' => ['alpha', 'beta'],
    ],
    'bullet plain star marker option' => [
        'document' => $document([$bulletList([$plainItem('alpha'), $plainItem('beta')])]),
        'expected' => "* alpha\n* beta",
        'options' => ['bulletListMarker' => 'star'],
        'type' => 'bullet_list',
        'items' => ['alpha', 'beta'],
    ],
    'task plain unchecked item writes after checkbox' => [
        'document' => $document([$bulletList([$plainItem('todo', ['taskChecked' => false])])]),
        'expected' => '- [ ] todo',
        'type' => 'bullet_list',
        'items' => ['todo'],
    ],
    'task plain checked item writes after checkbox' => [
        'document' => $document([$bulletList([$plainItem('done', ['taskChecked' => true])])]),
        'expected' => '- [x] done',
        'type' => 'bullet_list',
        'items' => ['done'],
    ],
    'task plain mixed items stay compact' => [
        'document' => $document([$bulletList([
            $plainItem('todo', ['taskChecked' => false]),
            $plainItem('done', ['taskChecked' => true]),
        ])]),
        'expected' => "- [ ] todo\n- [x] done",
        'type' => 'bullet_list',
        'items' => ['todo', 'done'],
    ],
    'plain softbreak continuation indents under marker' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha'), new AstNode('softbreak'), $text('beta')])])])]),
        'expected' => "- alpha\n  beta",
        'type' => 'bullet_list',
        'items' => ['alpha beta'],
    ],
    'plain hardbreak continuation indents under marker' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha'), new AstNode('linebreak'), $text('beta')])])])]),
        'expected' => "- alpha\\\n  beta",
        'type' => 'bullet_list',
        'items' => ['alpha\\ beta'],
    ],
    'plain softbreak space option stays on marker line' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha'), new AstNode('softbreak'), $text('beta')])])])]),
        'expected' => '- alpha beta',
        'options' => ['softBreak' => 'space'],
        'type' => 'bullet_list',
        'items' => ['alpha beta'],
    ],
    'plain inline code stays compact' => [
        'document' => $document([$bulletList([$listItem([$plain([new AstNode('code', ['text' => 'wp cli'])])])])]),
        'expected' => '- `wp cli`',
        'type' => 'bullet_list',
        'items' => ['wp cli'],
    ],
    'plain emphasis stays compact' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('review '), new AstNode('emph', [], [$text('packet')])])])])]),
        'expected' => '- review *packet*',
        'type' => 'bullet_list',
        'items' => ['review packet'],
    ],
    'plain strong stays compact' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('review '), new AstNode('strong', [], [$text('packet')])])])])]),
        'expected' => '- review **packet**',
        'type' => 'bullet_list',
        'items' => ['review packet'],
    ],
    'plain ordered-looking text escapes under bullet' => [
        'document' => $document([$bulletList([$plainItem('1. not ordered')])]),
        'expected' => '- 1\\. not ordered',
        'type' => 'bullet_list',
        'items' => ['1. not ordered'],
    ],
    'plain bullet-looking text escapes under bullet' => [
        'document' => $document([$bulletList([$plainItem('- not nested')])]),
        'expected' => '- \\- not nested',
        'type' => 'bullet_list',
        'items' => ['- not nested'],
    ],
    'plain fenced-div-looking text escapes under bullet' => [
        'document' => $document([$bulletList([$plainItem('::: not a div')])]),
        'expected' => '- \\::: not a div',
        'type' => 'bullet_list',
        'items' => ['::: not a div'],
    ],
    'plain hash-looking text stays item content' => [
        'document' => $document([$bulletList([$plainItem('# not heading')])]),
        'expected' => '- \\# not heading',
        'type' => 'bullet_list',
        'items' => ['# not heading'],
    ],
    'plain html-looking text escapes safely' => [
        'document' => $document([$bulletList([$plainItem('<review> packet')])]),
        'expected' => '- \\<review\\> packet',
        'type' => 'bullet_list',
        'items' => ['<review> packet'],
    ],
    'plain unicode text stays compact' => [
        'document' => $document([$bulletList([$plainItem('naive cafe resume')])]),
        'expected' => '- naive cafe resume',
        'type' => 'bullet_list',
        'items' => ['naive cafe resume'],
    ],
    'plain entity-sensitive text stays compact' => [
        'document' => $document([$bulletList([$plainItem('AT&T < R&D')])]),
        'expected' => '- AT&T \\< R&D',
        'type' => 'bullet_list',
        'items' => ['AT&T < R&D'],
    ],
];

$orderedMarkerCases = [
    ['default', 'default', 1, '#.  '],
    ['default', 'period', 1, '#.  '],
    ['default', 'one_paren', 1, '#)  '],
    ['default', 'two_parens', 1, '#.  '],
    ['decimal', 'default', 0, '0.  '],
    ['decimal', 'period', 3, '3.  '],
    ['decimal', 'one_paren', 3, '3)  '],
    ['decimal', 'two_parens', 3, '(3) '],
    ['lower_alpha', 'default', 1, 'a.  '],
    ['lower_alpha', 'period', 27, 'aa. ', [], false],
    ['lower_alpha', 'one_paren', 28, 'ab) ', [], false],
    ['lower_alpha', 'two_parens', 2, '(b) ', [], false],
    ['upper_alpha', 'default', 1, 'A.  '],
    ['upper_alpha', 'period', 27, 'AA. ', [], false],
    ['upper_alpha', 'one_paren', 28, 'AB) ', [], false],
    ['upper_alpha', 'two_parens', 2, '(B) ', [], false],
    ['lower_roman', 'default', 1, 'i.  '],
    ['lower_roman', 'period', 4, 'iv. '],
    ['lower_roman', 'one_paren', 9, 'ix) ', [], false],
    ['lower_roman', 'two_parens', 12, '(xii) ', [], false],
    ['upper_roman', 'default', 1, 'I.  '],
    ['upper_roman', 'period', 4, 'IV. '],
    ['upper_roman', 'one_paren', 9, 'IX) ', [], false],
    ['upper_roman', 'two_parens', 12, '(XII) ', [], false],
    ['example', 'default', 1, '(@) '],
    ['example', 'period', 1, '(@) '],
    ['example', 'one_paren', 1, '(@) '],
    ['example', 'two_parens', 1, '(@case-two-parens) ', ['exampleLabel' => 'case-two-parens']],
];

foreach ($orderedMarkerCases as $index => $case) {
    [$style, $delimiter, $start, $marker] = $case;
    $itemAttrs = $case[4] ?? [];
    $roundTrip = $case[5] ?? true;
    $label = sprintf('%02d %s %s start %d', $index + 1, $style, $delimiter, $start);
    $blockCases['ordered plain marker ' . $label] = [
        'document' => $document([
            $orderedList([$plainItem('alpha', $itemAttrs)], [
                'style' => $style,
                'delimiter' => $delimiter,
                'start' => $start,
            ]),
        ]),
        'expected' => $marker . 'alpha',
        'type' => 'ordered_list',
        'items' => ['alpha'],
        'roundTrip' => $roundTrip,
    ];
}

$continuationCases = [
    'bullet plain before code block' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha')]), $codeBlock('echo alpha')])])]),
        'expected' => "- alpha\n      echo alpha",
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    'ordered plain before code block' => [
        'document' => $document([$orderedList([$listItem([$plain([$text('alpha')]), $codeBlock('echo alpha')])])]),
        'expected' => "1.  alpha\n        echo alpha",
        'type' => 'ordered_list',
        'items' => ['alpha'],
    ],
    'task plain before code block' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha')]), $codeBlock('echo alpha')], ['taskChecked' => true])])]),
        'expected' => "- [x] alpha\n      echo alpha",
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    'bullet plain before nested bullet' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha')]), $bulletList([$plainItem('beta')])])])]),
        'expected' => "- alpha\n  - beta",
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    'ordered plain before nested bullet' => [
        'document' => $document([$orderedList([$listItem([$plain([$text('alpha')]), $bulletList([$plainItem('beta')])])])]),
        'expected' => "1.  alpha\n    - beta",
        'type' => 'ordered_list',
        'items' => ['alpha'],
    ],
    'bullet plain before nested ordered' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha')]), $orderedList([$plainItem('beta')])])])]),
        'expected' => "- alpha\n  1.  beta",
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    'bullet plain before blockquote' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha')]), $blockquote([$paragraph([$text('quote')])])])])]),
        'expected' => "- alpha\n  > quote",
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    'bullet plain before second paragraph' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha')]), $paragraph([$text('beta')])])])]),
        'expected' => "- alpha\n\n  beta",
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    'bullet paragraph before second plain' => [
        'document' => $document([$bulletList([$listItem([$paragraph([$text('alpha')]), $plain([$text('beta')])])])]),
        'expected' => "- alpha\n\n  beta",
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    'bullet two plain blocks keep block boundary' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha')]), $plain([$text('beta')])])])]),
        'expected' => "- alpha\n\n  beta",
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
    'ordered two plain blocks keep block boundary' => [
        'document' => $document([$orderedList([$listItem([$plain([$text('alpha')]), $plain([$text('beta')])])])]),
        'expected' => "1.  alpha\n\n    beta",
        'type' => 'ordered_list',
        'items' => ['alpha'],
    ],
    'bullet plain before line block' => [
        'document' => $document([$bulletList([$listItem([$plain([$text('alpha')]), $lineBlock([$line('one'), $line('two')])])])]),
        'expected' => "- alpha\n  | one\n  | two",
        'type' => 'bullet_list',
        'items' => ['alpha'],
    ],
];

$blockCases = array_merge($blockCases, $continuationCases);

foreach ($blockCases as $label => $case) {
    $tests['maps upstream markdown writer plain list item surge ' . $label] =
        static function (TestRunner $t) use ($case, $leadingItemText): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);
            $t->same($case['expected'], $markdown);
            if (($case['roundTrip'] ?? true) === false) {
                return;
            }

            $roundTrip = (new MarkdownReader())->read($markdown);
            $list = $roundTrip->children[0] ?? null;
            $t->true($list instanceof AstNode, 'Expected a list after MarkdownWriter round trip');
            if (!$list instanceof AstNode) {
                return;
            }

            $t->same($case['type'], $list->type);
            $t->same($case['items'], array_map($leadingItemText, $list->children));
        };
}

return $tests;

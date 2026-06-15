<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$strong = static fn (array $children): AstNode => new AstNode('strong', [], $children);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$link = static fn (array $children, string $url = '/target'): AstNode => new AstNode('link', ['url' => $url], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$textParagraph = static fn (string $value): AstNode => $paragraph([$text($value)]);
$heading = static fn (string $value): AstNode => new AstNode('heading', ['level' => 1, 'text' => $value], [$text($value)]);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$rawHtml = static fn (string $html): AstNode => new AstNode('raw_html', ['html' => $html]);
$lineBlock = static fn (array $values): AstNode => new AstNode(
    'line_block',
    [],
    array_map(static fn (string $value): AstNode => new AstNode('line', ['text' => $value]), $values)
);
$definitionList = static function (string $term, string $definition) use ($text, $paragraph): AstNode {
    return new AstNode('definition_list', [], [
        new AstNode('definition_item', [], [
            new AstNode('definition_term', [], [$text($term)]),
            new AstNode('definition', [], [$paragraph([$text($definition)])]),
        ]),
    ]);
};
$plainItem = static fn (string $value): AstNode => new AstNode('list_item', [], [$text($value)]);
$taskItem = static fn (array $children, bool $checked = false, array $attrs = []): AstNode => new AstNode(
    'list_item',
    array_replace($attrs, ['taskChecked' => $checked]),
    $children
);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$bulletTaskDoc = static fn (array $children, bool $checked = false, array $attrs = []): AstNode => $document([
    $bulletList([$taskItem($children, $checked)], $attrs),
]);
$orderedTaskDoc = static fn (array $children, bool $checked = false, array $attrs = []): AstNode => $document([
    $orderedList([$taskItem($children, $checked)], $attrs),
]);

$taskExpected = static function (string $marker, bool $checked, string $first, array $following = []): string {
    $indent = str_repeat(' ', strlen($marker));
    $lines = [$marker . ($checked ? '[x] ' : '[ ] ') . $first];
    foreach ($following as $line) {
        $lines[] = $line === null ? '' : $indent . $line;
    }

    return implode("\n", $lines);
};

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'line') {
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
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list') {
            continue;
        }
        $part = trim($inlineText($child));
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return trim(implode(' ', $parts));
};

$containsType = static function (AstNode $node, string $type) use (&$containsType): bool {
    if ($node->type === $type) {
        return true;
    }
    foreach ($node->children as $child) {
        if ($containsType($child, $type)) {
            return true;
        }
    }

    return false;
};

$cases = [
    '01 unchecked softbreak continuation' => [
        'doc' => $bulletTaskDoc([$text('alpha'), $softbreak(), $text('beta')]),
        'expected' => $taskExpected('- ', false, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
    ],
    '02 checked softbreak continuation' => [
        'doc' => $bulletTaskDoc([$text('alpha'), $softbreak(), $text('beta')], true),
        'expected' => $taskExpected('- ', true, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
    ],
    '03 unchecked hardbreak continuation' => [
        'doc' => $bulletTaskDoc([$text('alpha'), $linebreak(), $text('beta')]),
        'expected' => $taskExpected('- ', false, 'alpha\\', ['beta']),
        'texts' => ['alpha\\ beta'],
        'taskStates' => [false],
    ],
    '04 checked hardbreak continuation' => [
        'doc' => $bulletTaskDoc([$text('alpha'), $linebreak(), $text('beta')], true),
        'expected' => $taskExpected('- ', true, 'alpha\\', ['beta']),
        'texts' => ['alpha\\ beta'],
        'taskStates' => [true],
    ],
    '05 unchecked literal newline text continuation' => [
        'doc' => $bulletTaskDoc([$text("alpha\nbeta")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
    ],
    '06 checked literal newline text continuation' => [
        'doc' => $bulletTaskDoc([$text("alpha\nbeta")], true),
        'expected' => $taskExpected('- ', true, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
    ],
    '07 plus marker task continuation' => [
        'doc' => $bulletTaskDoc([$text('alpha'), $softbreak(), $text('beta')]),
        'expected' => $taskExpected('+ ', false, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
        'options' => ['bulletListMarker' => 'plus'],
    ],
    '08 star marker task continuation' => [
        'doc' => $bulletTaskDoc([$text('alpha'), $softbreak(), $text('beta')], true),
        'expected' => $taskExpected('* ', true, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
        'options' => ['bulletListMarker' => 'star'],
    ],
    '09 continuation bullet marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n- beta")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\- beta']),
        'texts' => ['alpha - beta'],
        'taskStates' => [false],
    ],
    '10 continuation decimal marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n1. beta")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['1\\. beta']),
        'texts' => ['alpha 1. beta'],
        'taskStates' => [false],
    ],
    '11 continuation default ordered marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n#. beta")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\#. beta']),
        'texts' => ['alpha #. beta'],
        'taskStates' => [false],
    ],
    '12 continuation parenthesized marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n(1) beta")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\(1) beta']),
        'texts' => ['alpha (1) beta'],
        'taskStates' => [false],
    ],
    '13 continuation definition marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n: beta")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\: beta']),
        'texts' => ['alpha : beta'],
        'taskStates' => [false],
    ],
    '14 continuation heading marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n# beta")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\# beta']),
        'texts' => ['alpha # beta'],
        'taskStates' => [false],
    ],
    '15 continuation blockquote marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n> beta")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\> beta']),
        'texts' => ['alpha > beta'],
        'taskStates' => [false],
    ],
    '16 continuation html-looking marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n<div>")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['&lt;div\\>']),
        'taskStates' => [false],
    ],
    '17 continuation ellipsis remains literal text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n...")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\...']),
        'texts' => ['alpha ...'],
        'taskStates' => [false],
    ],
    '18 continuation dash run remains literal text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n-- range")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\-- range']),
        'texts' => ['alpha -- range'],
        'taskStates' => [false],
    ],
    '19 continuation fenced div marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n::: note")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\::: note']),
        'texts' => ['alpha ::: note'],
        'taskStates' => [false],
    ],
    '20 continuation image opener remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n![image]")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\![image\\]']),
        'texts' => ['alpha ![image]'],
        'taskStates' => [false],
    ],
    '21 continuation strikeout delimiter remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n~~mark~~")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\~\\~mark\\~\\~']),
        'taskStates' => [false],
    ],
    '22 continuation entity remains literal text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n&copy;")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\&copy;']),
        'taskStates' => [false],
    ],
    '23 continuation brackets remain paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\n[label]")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['\\[label\\]']),
        'taskStates' => [false],
    ],
    '24 continuation pipe remains paragraph text' => [
        'doc' => $bulletTaskDoc([$text("alpha\na | b")]),
        'expected' => $taskExpected('- ', false, 'alpha', ['a \\| b']),
        'texts' => ['alpha a | b'],
        'taskStates' => [false],
    ],
    '25 strong inline softbreak continuation' => [
        'doc' => $bulletTaskDoc([$strong([$text('alpha'), $softbreak(), $text('beta')])]),
        'expected' => $taskExpected('- ', false, '**alpha', ['beta**']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
    ],
    '26 emphasis inline softbreak continuation' => [
        'doc' => $bulletTaskDoc([$emph([$text('alpha'), $softbreak(), $text('beta')])], true),
        'expected' => $taskExpected('- ', true, '*alpha', ['beta*']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
    ],
    '27 link label softbreak continuation' => [
        'doc' => $bulletTaskDoc([$link([$text('alpha'), $softbreak(), $text('beta')])]),
        'expected' => $taskExpected('- ', false, '[alpha', ['beta](/target)']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
    ],
    '28 code inline after softbreak continuation' => [
        'doc' => $bulletTaskDoc([$text('alpha'), $softbreak(), $code('beta')]),
        'expected' => $taskExpected('- ', false, 'alpha', ['`beta`']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
    ],
    '29 two softbreak continuations stay paragraphs' => [
        'doc' => $bulletTaskDoc([$text('alpha'), $softbreak(), $text('beta'), $softbreak(), $text('gamma')]),
        'expected' => $taskExpected('- ', false, 'alpha', ['beta', 'gamma']),
        'texts' => ['alpha beta gamma'],
        'taskStates' => [false],
    ],
    '30 checked mixed break continuations stay paragraphs' => [
        'doc' => $bulletTaskDoc([$text('alpha'), $linebreak(), $text('beta'), $softbreak(), $text('gamma')], true),
        'expected' => $taskExpected('- ', true, 'alpha\\', ['beta', 'gamma']),
        'texts' => ['alpha\\ beta gamma'],
        'taskStates' => [true],
    ],
    '31 paragraph child softbreak continuation' => [
        'doc' => $bulletTaskDoc([$paragraph([$text('alpha'), $softbreak(), $text('beta')])]),
        'expected' => $taskExpected('- ', false, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
    ],
    '32 checked paragraph child hardbreak continuation' => [
        'doc' => $bulletTaskDoc([$paragraph([$text('alpha'), $linebreak(), $text('beta')])], true),
        'expected' => $taskExpected('- ', true, 'alpha\\', ['beta']),
        'texts' => ['alpha\\ beta'],
        'taskStates' => [true],
    ],
    '33 second paragraph continuation' => [
        'doc' => $bulletTaskDoc([$textParagraph('alpha'), $textParagraph('beta')]),
        'expected' => $taskExpected('- ', false, 'alpha', [null, 'beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
    ],
    '34 checked second paragraph continuation' => [
        'doc' => $bulletTaskDoc([$textParagraph('alpha'), $textParagraph('beta')], true),
        'expected' => $taskExpected('- ', true, 'alpha', [null, 'beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
    ],
    '35 second paragraph bullet marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$textParagraph('alpha'), $textParagraph('- beta')]),
        'expected' => $taskExpected('- ', false, 'alpha', [null, '\\- beta']),
        'texts' => ['alpha - beta'],
        'taskStates' => [false],
    ],
    '36 second paragraph ordered marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$textParagraph('alpha'), $textParagraph('1. beta')]),
        'expected' => $taskExpected('- ', false, 'alpha', [null, '1\\. beta']),
        'texts' => ['alpha 1. beta'],
        'taskStates' => [false],
    ],
    '37 second paragraph definition marker remains paragraph text' => [
        'doc' => $bulletTaskDoc([$textParagraph('alpha'), $textParagraph(': beta')]),
        'expected' => $taskExpected('- ', false, 'alpha', [null, '\\: beta']),
        'texts' => ['alpha : beta'],
        'taskStates' => [false],
    ],
    '38 blockquote after task paragraph remains blockquote' => [
        'doc' => $bulletTaskDoc([$textParagraph('lead'), $blockquote([$textParagraph('quote')])]),
        'expected' => $taskExpected('- ', false, 'lead', ['> quote']),
        'texts' => ['lead quote'],
        'taskStates' => [false],
    ],
    '39 heading after task paragraph remains heading block' => [
        'doc' => $bulletTaskDoc([$textParagraph('lead'), $heading('Heading')]),
        'expected' => $taskExpected('- ', false, 'lead', ['# Heading']),
        'texts' => ['lead Heading'],
        'taskStates' => [false],
    ],
    '40 horizontal rule after task paragraph remains block' => [
        'doc' => $bulletTaskDoc([$textParagraph('lead'), new AstNode('horizontal_rule')]),
        'expected' => $taskExpected('- ', false, 'lead', ['* * *']),
        'texts' => ['lead'],
        'taskStates' => [false],
    ],
    '41 raw html after task paragraph remains raw block' => [
        'doc' => $bulletTaskDoc([$textParagraph('lead'), $rawHtml('<section>raw</section>')]),
        'expected' => $taskExpected('- ', false, 'lead', ['<section>raw</section>']),
        'texts' => ['lead'],
        'taskStates' => [false],
    ],
    '42 line block after task paragraph remains line block' => [
        'doc' => $bulletTaskDoc([$textParagraph('lead'), $lineBlock(['alpha', 'beta'])]),
        'expected' => $taskExpected('- ', false, 'lead', ['| alpha', '| beta']),
        'taskStates' => [false],
    ],
    '43 definition list after task paragraph remains definition list' => [
        'doc' => $bulletTaskDoc([$textParagraph('lead'), $definitionList('Term', 'Definition')]),
        'expected' => $taskExpected('- ', false, 'lead', ['Term', ':   Definition']),
        'taskStates' => [false],
    ],
    '44 nested bullet after task paragraph remains nested list' => [
        'doc' => $bulletTaskDoc([$textParagraph('lead'), $bulletList([$plainItem('child')])]),
        'expected' => $taskExpected('- ', false, 'lead', ['- child']),
        'texts' => ['lead'],
        'taskStates' => [false],
    ],
    '45 nested ordered after task paragraph remains nested list' => [
        'doc' => $bulletTaskDoc([$textParagraph('lead'), $orderedList([$plainItem('child')])]),
        'expected' => $taskExpected('- ', false, 'lead', ['1.  child']),
        'texts' => ['lead'],
        'taskStates' => [false],
    ],
    '46 nested checked task after task paragraph remains nested task list' => [
        'doc' => $bulletTaskDoc([$textParagraph('lead'), $bulletList([$taskItem([$text('child')], true)])]),
        'expected' => $taskExpected('- ', false, 'lead', ['- [x] child']),
        'texts' => ['lead'],
        'taskStates' => [false],
    ],
    '47 plus marker second paragraph continuation' => [
        'doc' => $bulletTaskDoc([$textParagraph('alpha'), $textParagraph('beta')]),
        'expected' => $taskExpected('+ ', false, 'alpha', [null, 'beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
        'options' => ['bulletListMarker' => 'plus'],
    ],
    '48 star marker paragraph softbreak continuation' => [
        'doc' => $bulletTaskDoc([$paragraph([$text('alpha'), $softbreak(), $text('beta')])], true),
        'expected' => $taskExpected('* ', true, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
        'options' => ['bulletListMarker' => 'star'],
    ],
    '49 ordered task softbreak continuation' => [
        'doc' => $orderedTaskDoc([$text('alpha'), $softbreak(), $text('beta')]),
        'expected' => $taskExpected('1.  ', false, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
        'listType' => 'ordered_list',
    ],
    '50 ordered checked softbreak continuation' => [
        'doc' => $orderedTaskDoc([$text('alpha'), $softbreak(), $text('beta')], true),
        'expected' => $taskExpected('1.  ', true, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
        'listType' => 'ordered_list',
    ],
    '51 ordered start ten task continuation' => [
        'doc' => $orderedTaskDoc([$text('alpha'), $softbreak(), $text('beta')], false, ['start' => 10]),
        'expected' => $taskExpected('10. ', false, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
        'listType' => 'ordered_list',
    ],
    '52 ordered start one hundred second paragraph continuation' => [
        'doc' => $orderedTaskDoc([$textParagraph('alpha'), $textParagraph('beta')], true, ['start' => 100]),
        'expected' => $taskExpected('100. ', true, 'alpha', [null, 'beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
        'listType' => 'ordered_list',
    ],
    '53 ordered one paren task continuation' => [
        'doc' => $orderedTaskDoc([$text('alpha'), $softbreak(), $text('beta')], false, ['delimiter' => 'one_paren']),
        'expected' => $taskExpected('1)  ', false, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
        'listType' => 'ordered_list',
    ],
    '54 ordered two parens task continuation' => [
        'doc' => $orderedTaskDoc([$text('alpha'), $softbreak(), $text('beta')], true, ['delimiter' => 'two_parens']),
        'expected' => $taskExpected('(1) ', true, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
        'listType' => 'ordered_list',
    ],
    '55 lower alpha ordered task continuation' => [
        'doc' => $orderedTaskDoc([$text('alpha'), $softbreak(), $text('beta')], false, ['style' => 'lower_alpha']),
        'expected' => $taskExpected('a.  ', false, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
        'listType' => 'ordered_list',
    ],
    '56 upper alpha ordered task continuation' => [
        'doc' => $orderedTaskDoc([$text('alpha'), $softbreak(), $text('beta')], true, ['style' => 'upper_alpha']),
        'expected' => $taskExpected('A.  ', true, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
        'listType' => 'ordered_list',
    ],
    '57 lower roman ordered task continuation' => [
        'doc' => $orderedTaskDoc([$text('alpha'), $softbreak(), $text('beta')], false, ['style' => 'lower_roman', 'start' => 4]),
        'expected' => $taskExpected('iv. ', false, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
        'listType' => 'ordered_list',
    ],
    '58 upper roman ordered task continuation' => [
        'doc' => $orderedTaskDoc([$text('alpha'), $softbreak(), $text('beta')], true, ['style' => 'upper_roman', 'start' => 9]),
        'expected' => $taskExpected('IX. ', true, 'alpha', ['beta']),
        'texts' => ['alpha beta'],
        'taskStates' => [true],
        'listType' => 'ordered_list',
    ],
    '59 ordered task nested bullet after continuation' => [
        'doc' => $orderedTaskDoc([$paragraph([$text('alpha'), $softbreak(), $text('beta')]), $bulletList([$plainItem('child')])]),
        'expected' => $taskExpected('1.  ', false, 'alpha', ['beta', '- child']),
        'texts' => ['alpha beta'],
        'taskStates' => [false],
        'listType' => 'ordered_list',
    ],
    '60 ordered task continuation marker remains paragraph text' => [
        'doc' => $orderedTaskDoc([$text("alpha\n- beta")], true, ['start' => 10]),
        'expected' => $taskExpected('10. ', true, 'alpha', ['\\- beta']),
        'texts' => ['alpha - beta'],
        'taskStates' => [true],
        'listType' => 'ordered_list',
    ],
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['maps upstream markdown writer task-list continuation surge ' . $name] =
        static function (TestRunner $t) use ($case, $listItemText, $containsType): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['doc']);
            $t->same($case['expected'], $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $list = $roundTrip->children[0] ?? null;
            $t->true($list instanceof AstNode, 'Expected first roundtrip block to be a list');
            if (!$list instanceof AstNode) {
                return;
            }

            $t->same($case['listType'] ?? 'bullet_list', $list->type);
            if (isset($case['texts'])) {
                $t->same($case['texts'], array_map($listItemText, $list->children));
            }
            $t->same($case['taskStates'], array_map(
                static fn (AstNode $item): mixed => $item->attr('taskChecked', null),
                $list->children
            ));

            foreach ($list->children as $item) {
                $t->same(false, $containsType($item, 'code_block'), 'Task continuation must not roundtrip as indented code');
            }
        };
}

return $tests;

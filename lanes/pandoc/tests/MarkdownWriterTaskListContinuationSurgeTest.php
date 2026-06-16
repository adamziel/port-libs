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
$paragraph = static fn (array|string $value): AstNode => new AstNode(
    'paragraph',
    [],
    is_array($value) ? $value : [$text($value)]
);
$textParagraph = static fn (string $value): AstNode => $paragraph($value);
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_replace(['text' => $value], $attrs)
);
$heading = static fn (string $value): AstNode => new AstNode('heading', ['level' => 1, 'text' => $value], [$text($value)]);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$rawHtml = static fn (string $html): AstNode => new AstNode('raw_html', ['html' => $html]);
$line = static fn (array|string $value = ''): AstNode => new AstNode(
    'line',
    [],
    is_array($value) ? $value : ($value === '' ? [] : [$text($value)])
);
$lineBlock = static fn (array $values): AstNode => new AstNode(
    'line_block',
    [],
    array_map(static fn (AstNode|string $value): AstNode => $value instanceof AstNode ? $value : new AstNode('line', ['text' => $value]), $values)
);
$definitionList = static function (string $term, string $definition) use ($text, $paragraph): AstNode {
    return new AstNode('definition_list', [], [
        new AstNode('definition_item', [], [
            new AstNode('definition_term', [], [$text($term)]),
            new AstNode('definition', [], [$paragraph($definition)]),
        ]),
    ]);
};
$plainItem = static fn (string $value): AstNode => new AstNode('list_item', [], [$text($value)]);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$taskItem = static fn (array $children, bool $checked = false, array $attrs = []): AstNode => $listItem(
    $children,
    array_replace($attrs, ['taskChecked' => $checked])
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
        $lineText = (string) $node->attr('text', '');
        if ($lineText !== '') {
            return $lineText;
        }
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
$plainText = $inlineText;

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
        'expected' => $taskExpected('- ', false, 'alpha', ['&amp;copy;']),
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

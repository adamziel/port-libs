<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [new AstNode('text', ['text' => $value])]);
$codeBlock = static fn (string $value): AstNode => new AstNode('code_block', ['text' => $value]);
$bulletList = static fn (array $items): AstNode => new AstNode('bullet_list', [], $items);
$orderedList = static fn (array $attrs, array $items): AstNode => new AstNode('ordered_list', $attrs, $items);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$textItem = static fn (string $value, array $attrs = []): AstNode => new AstNode('list_item', $attrs, [new AstNode('text', ['text' => $value])]);
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
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list') {
            continue;
        }

        $part = trim($plainText($child));
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return trim(implode(' ', $parts));
};

$anonymousExampleScenarios = [
    'single plain item' => static fn (string $marker): array => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [$textItem('example item')])]),
        $marker . 'example item',
        ['example item'],
    ],
    'two plain items' => static fn (string $marker): array => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $textItem('first example'),
            $textItem('second example'),
        ])]),
        $marker . "first example\n" . $marker . 'second example',
        ['first example', 'second example'],
    ],
    'task item' => static fn (string $marker): array => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $textItem('review task', ['taskChecked' => false]),
        ])]),
        $marker . '[ ] review task',
        ['review task'],
    ],
    'checked task item' => static fn (string $marker): array => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $textItem('accepted task', ['taskChecked' => true]),
        ])]),
        $marker . '[x] accepted task',
        ['accepted task'],
    ],
    'paragraph continuation' => static fn (string $marker): array => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $listItem([$paragraph('first paragraph'), $paragraph('second paragraph')]),
        ])]),
        $marker . "first paragraph\n\n    second paragraph",
        ['first paragraph second paragraph'],
    ],
    'nested bullet continuation' => static fn (string $marker): array => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $listItem([
                $text('parent example'),
                $bulletList([$textItem('nested bullet')]),
            ]),
        ])]),
        $marker . "parent example\n    - nested bullet",
        ['parent example'],
    ],
    'nested code continuation' => static fn (string $marker): array => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $listItem([$text('code example'), $codeBlock('echo example')]),
        ])]),
        $marker . "code example\n        echo example",
        ['code example'],
    ],
];

$tests = [];

foreach ($anonymousExampleScenarios as $name => $factory) {
    $tests['maps upstream markdown writer numbered example anonymous marker ' . $name] =
        static function (TestRunner $t) use ($factory, $itemText): void {
            [$doc, $expected, $texts] = $factory('(@) ');
            $markdown = (new MarkdownWriter())->write($doc);
            $roundTrip = (new MarkdownReader())->read($markdown);
            $roundTripList = $roundTrip->children[0] ?? new AstNode('missing');

            $t->same($expected, $markdown);
            $t->same('ordered_list', $roundTripList->type);
            $t->same('example', $roundTripList->attr('style'));
            $t->same('two_parens', $roundTripList->attr('delimiter'));
            $t->same($texts, array_map($itemText, $roundTripList->children));
        };
}

for ($index = 1; $index <= 30; $index++) {
    $label = 'case-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $textValue = 'example item ' . $index;
    $attrs = $index % 3 === 0
        ? ['label' => $label]
        : ($index % 2 === 0 ? ['exampleLabel' => $label] : []);
    $marker = $attrs === [] ? '(@) ' : '(@' . $label . ') ';

    $tests['maps upstream markdown writer numbered example marker ' . $label] =
        static function (TestRunner $t) use ($orderedList, $textItem, $document, $textValue, $attrs, $marker, $itemText): void {
            $doc = $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
                $textItem($textValue, $attrs),
            ])]);
            $markdown = (new MarkdownWriter())->write($doc);
            $roundTrip = (new MarkdownReader())->read($markdown);
            $roundTripList = $roundTrip->children[0] ?? new AstNode('missing');

            $t->same($marker . $textValue, $markdown);
            $t->same('ordered_list', $roundTripList->type);
            $t->same('example', $roundTripList->attr('style'));
            $t->same('two_parens', $roundTripList->attr('delimiter'));
            $t->same([$textValue], array_map($itemText, $roundTripList->children));
        };
}

$exampleBlockCases = [
    'two anonymous examples' => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $textItem('first example'),
            $textItem('second example'),
        ])]),
        "(@) first example\n(@) second example",
        ['first example', 'second example'],
    ],
    'labeled reference resolves after list' => [
        $document([
            $orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
                $textItem('labeled example', ['exampleLabel' => 'review']),
            ]),
            $paragraph('See (@review) for details.'),
        ]),
        "(@review) labeled example\n\nSee (@review) for details.",
        ['labeled example'],
        '(1)',
    ],
    'attribute label source' => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $textItem('attribute label example', ['attributes' => ['data-example-label' => 'attr-review']]),
        ])]),
        '(@attr-review) attribute label example',
        ['attribute label example'],
    ],
    'invalid label falls back anonymous' => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $textItem('anonymous fallback', ['exampleLabel' => 'bad label']),
        ])]),
        '(@) anonymous fallback',
        ['anonymous fallback'],
    ],
    'task example item' => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $textItem('example task', ['taskChecked' => true, 'exampleLabel' => 'done-task']),
        ])]),
        '(@done-task) [x] example task',
        ['example task'],
    ],
    'paragraph continuation example' => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $listItem([$paragraph('example paragraph'), $paragraph('example continuation')], ['exampleLabel' => 'para']),
        ])]),
        "(@para) example paragraph\n\n        example continuation",
        ['example paragraph example continuation'],
    ],
    'nested bullet example' => [
        $document([$orderedList(['style' => 'example', 'delimiter' => 'two_parens'], [
            $listItem([
                $text('example parent'),
                $bulletList([$textItem('example nested')]),
            ], ['exampleLabel' => 'nested']),
        ])]),
        "(@nested) example parent\n          - example nested",
        ['example parent'],
    ],
];

foreach ($exampleBlockCases as $name => $case) {
    $tests['maps upstream markdown writer numbered example block ' . $name] =
        static function (TestRunner $t) use ($case, $itemText): void {
            [$doc, $expected, $texts, $resolvedReference] = [$case[0], $case[1], $case[2], $case[3] ?? null];
            $markdown = (new MarkdownWriter())->write($doc);
            $roundTrip = (new MarkdownReader())->read($markdown);
            $roundTripList = $roundTrip->children[0] ?? new AstNode('missing');

            $t->same($expected, $markdown);
            $t->same('ordered_list', $roundTripList->type);
            $t->same('example', $roundTripList->attr('style'));
            $t->same('two_parens', $roundTripList->attr('delimiter'));
            $t->same($texts, array_map($itemText, $roundTripList->children));
            if ($resolvedReference !== null) {
                $t->contains($resolvedReference, $roundTrip->children[1]->attr('text'));
            }
        };
}

for ($index = 1; $index <= 14; $index++) {
    $style = 'example';
    $attrs = ['style' => 'example', 'delimiter' => 'two_parens'];
    $marker = '(@) ';
    $textValue = 'surge matrix item ' . $index;

    $tests['maps upstream markdown writer ordered marker matrix ' . str_pad((string) $index, 2, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($orderedList, $textItem, $document, $attrs, $marker, $textValue, $style): void {
            $doc = $document([$orderedList($attrs, [$textItem($textValue)])]);
            $markdown = (new MarkdownWriter())->write($doc);
            $roundTrip = (new MarkdownReader())->read($markdown);
            $roundTripList = $roundTrip->children[0] ?? new AstNode('missing');

            $t->same($marker . $textValue, $markdown);
            $t->same($style, $roundTripList->attr('style'));
        };
}

return $tests;

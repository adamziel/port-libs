<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [$text($value)]);
$heading = static fn (string $value, int $level = 1): AstNode => new AstNode(
    'heading',
    ['level' => $level],
    [$text($value)]
);
$blockquote = static fn (array $children): AstNode => new AstNode('blockquote', [], $children);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$orderedList = static fn (array $items, array $attrs = []): AstNode => new AstNode('ordered_list', $attrs, $items);
$definition = static fn (array $children): AstNode => new AstNode('definition', [], $children);
$definitionTerm = static fn (string $value): AstNode => new AstNode('definition_term', [], [$text($value)]);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$definitionList = static fn (string $term, string $body): AstNode => new AstNode('definition_list', [], [
    $definitionItem($definitionTerm($term), [$definition([$paragraph($body)])]),
]);
$line = static fn (string $value): AstNode => new AstNode('line', [], [$text($value)]);
$lineBlock = static fn (array $lines): AstNode => new AstNode('line_block', [], $lines);
$div = static fn (array $children, array $attrs = []): AstNode => new AstNode('div', $attrs, $children);
$rawHtml = static fn (string $html): AstNode => new AstNode('raw_html', ['html' => $html]);
$tableCell = static fn (string $value): AstNode => new AstNode('table_cell', ['text' => $value], [$text($value)]);
$tableRow = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);
$table = static function (string $header, string $value) use ($tableCell, $tableRow): AstNode {
    return new AstNode('table', ['alignments' => ['left']], [
        new AstNode('table_head', [], [
            $tableRow([$tableCell($header)]),
        ]),
        new AstNode('table_body', [], [
            $tableRow([$tableCell($value)]),
        ]),
    ]);
};
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

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

    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
};

$blockText = static function (AstNode $node) use (&$blockText, $inlineText): string {
    if (in_array($node->type, ['paragraph', 'plain', 'heading', 'line'], true)) {
        return $inlineText($node);
    }

    if ($node->type === 'blockquote' || $node->type === 'div') {
        return trim(implode(' ', array_map($blockText, $node->children)));
    }

    return '';
};

$semanticSignatures = static function (AstNode $item) use ($blockText): array {
    $signatures = [];

    foreach ($item->children as $child) {
        if ($child->type === 'raw_html' && trim((string) $child->attr('html', '')) === '<!-- -->') {
            continue;
        }

        if ($child->type === 'paragraph' && $blockText($child) === 'outer item') {
            continue;
        }

        if ($child->type === 'bullet_list' || $child->type === 'ordered_list' || $child->type === 'definition_list') {
            $signatures[] = $child->type . ':' . count($child->children);
            continue;
        }

        if ($child->type === 'paragraph' || $child->type === 'plain') {
            $signatures[] = 'paragraph:' . $blockText($child);
            continue;
        }

        if ($child->type === 'heading') {
            $signatures[] = 'heading:' . $child->attr('level', 1) . ':' . $blockText($child);
            continue;
        }

        if ($child->type === 'blockquote') {
            $signatures[] = 'blockquote:' . $blockText($child);
            continue;
        }

        if ($child->type === 'table') {
            $signatures[] = 'table';
            continue;
        }

        if ($child->type === 'raw_html') {
            $signatures[] = 'raw_html:' . trim((string) $child->attr('html', ''));
            continue;
        }

        if ($child->type === 'horizontal_rule') {
            $signatures[] = 'horizontal_rule';
            continue;
        }

        if ($child->type === 'div') {
            $signatures[] = 'div:' . $blockText($child);
            continue;
        }

        if ($child->type === 'line_block') {
            $signatures[] = 'line_block:' . implode('|', array_map($blockText, $child->children));
        }
    }

    return $signatures;
};

$outerListFamilies = [
    'dash bullet outer' => [
        'document' => static fn (array $children): AstNode => $document([$bulletList([
            $listItem(array_merge([$text('outer item')], $children)),
        ])]),
        'options' => [],
    ],
    'decimal ordered outer' => [
        'document' => static fn (array $children): AstNode => $document([$orderedList([
            $listItem(array_merge([$text('outer item')], $children)),
        ])]),
        'options' => [],
    ],
    'numbered example outer' => [
        'document' => static fn (array $children): AstNode => $document([$orderedList([
            $listItem(array_merge([$text('outer item')], $children)),
        ], ['style' => 'example'])]),
        'options' => [],
    ],
];

$previousListFamilies = [
    'after nested bullet list' => [
        'node' => static fn (): AstNode => $bulletList([$listItem([$text('nested bullet')])]),
        'signature' => 'bullet_list:1',
        'differentList' => static fn (): AstNode => $orderedList([$listItem([$text('ordered sibling')])]),
        'differentListSignature' => 'ordered_list:1',
    ],
    'after nested ordered list' => [
        'node' => static fn (): AstNode => $orderedList([$listItem([$text('nested ordered')])]),
        'signature' => 'ordered_list:1',
        'differentList' => static fn (): AstNode => $bulletList([$listItem([$text('bullet sibling')])]),
        'differentListSignature' => 'bullet_list:1',
    ],
    'after nested definition list' => [
        'node' => static fn (): AstNode => $definitionList('Nested term', 'nested definition'),
        'signature' => 'definition_list:1',
        'differentList' => static fn (): AstNode => $bulletList([$listItem([$text('bullet sibling')])]),
        'differentListSignature' => 'bullet_list:1',
    ],
];

$followingBlockCases = [
    'paragraph sibling' => [
        'node' => static fn (array $previous): AstNode => $paragraph('after paragraph'),
        'signature' => static fn (array $previous): string => 'paragraph:after paragraph',
    ],
    'plain sibling' => [
        'node' => static fn (array $previous): AstNode => $plain('after plain'),
        'signature' => static fn (array $previous): string => 'paragraph:after plain',
    ],
    'heading sibling' => [
        'node' => static fn (array $previous): AstNode => $heading('Boundary heading', 2),
        'signature' => static fn (array $previous): string => 'heading:2:Boundary heading',
    ],
    'blockquote sibling' => [
        'node' => static fn (array $previous): AstNode => $blockquote([$paragraph('quoted sibling')]),
        'signature' => static fn (array $previous): string => 'blockquote:quoted sibling',
    ],
    'different list sibling' => [
        'node' => static fn (array $previous): AstNode => $previous['differentList'](),
        'signature' => static fn (array $previous): string => $previous['differentListSignature'],
    ],
    'line block sibling' => [
        'node' => static fn (array $previous): AstNode => $lineBlock([$line('first line'), $line('second line')]),
        'signature' => static fn (array $previous): string => 'line_block:first line|second line',
    ],
    'table sibling' => [
        'node' => static fn (array $previous): AstNode => $table('Boundary', 'sibling'),
        'signature' => static fn (array $previous): string => 'table',
    ],
    'raw html sibling' => [
        'node' => static fn (array $previous): AstNode => $rawHtml('<section>raw sibling</section>'),
        'signature' => static fn (array $previous): string => 'raw_html:<section>raw sibling</section>',
    ],
    'thematic break sibling' => [
        'node' => static fn (array $previous): AstNode => new AstNode('horizontal_rule'),
        'signature' => static fn (array $previous): string => 'horizontal_rule',
    ],
    'fenced div sibling' => [
        'node' => static fn (array $previous): AstNode => $div([$paragraph('Div body')], ['classes' => ['review']]),
        'signature' => static fn (array $previous): string => 'div:Div body',
    ],
];

$tests = [];

foreach ($outerListFamilies as $outerName => $outer) {
    foreach ($previousListFamilies as $previousName => $previous) {
        foreach ($followingBlockCases as $caseName => $case) {
            $tests['maps upstream markdown writer block boundary fifth wave '
                . $outerName . ' ' . $previousName . ' before ' . $caseName] =
                static function (TestRunner $t) use ($outer, $previous, $case, $semanticSignatures): void {
                    $children = [
                        $previous['node'](),
                        $case['node']($previous),
                    ];
                    $markdown = (new MarkdownWriter($outer['options']))->write($outer['document']($children));

                    $t->same(1, substr_count($markdown, '<!-- -->'), $markdown);

                    $roundTrip = (new MarkdownReader())->read($markdown);
                    $list = $roundTrip->children[0] ?? new AstNode('missing');
                    $item = $list->children[0] ?? new AstNode('missing');

                    $t->same(
                        [$previous['signature'], $case['signature']($previous)],
                        $semanticSignatures($item),
                        $markdown
                    );
                };
        }
    }
}

return $tests;

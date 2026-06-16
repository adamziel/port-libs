<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$emph = static fn (string $value): AstNode => new AstNode('emph', [], [$text($value)]);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$paragraph = static fn (string|array $value): AstNode => new AstNode(
    'paragraph',
    [],
    is_array($value) ? $value : [$text($value)]
);
$codeBlock = static fn (string $value): AstNode => new AstNode('code_block', ['text' => $value]);
$definition = static fn (array $children): AstNode => new AstNode('definition', [], $children);
$definitionTerm = static fn (array $children): AstNode => new AstNode('definition_term', [], $children);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$definitionList = static fn (array $items): AstNode => new AstNode('definition_list', [], $items);
$document = static fn (array $items): AstNode => new AstNode('document', [], [$definitionList($items)]);

$inlineText = null;
$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'space' || $node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$firstDefinitionItem = static function (AstNode $doc): AstNode {
    return $doc->children[0]->children[0] ?? new AstNode('missing');
};

$termGroupDoc = $document([
    $definitionItem(
        $definitionTerm([
            $text('Primary'),
            $linebreak(),
            $text('Alias'),
            $linebreak(),
            $emph('Synonym'),
        ]),
        [
            $definition([$paragraph('First')]),
            $definition([$paragraph([$text('Second'), $space(), $emph('body')])]),
        ]
    ),
]);

$detachedBodyDoc = $document([
    $definitionItem(
        $definitionTerm([
            $text('Packet'),
            $linebreak(),
            $code('packet-alias'),
        ]),
        [
            $definition([$codeBlock('echo packet;')]),
        ]
    ),
]);

$profiles = [
    'markdown' => [],
    'commonmark definition lists' => ['format' => 'commonmark+definition_lists'],
    'gfm definition lists' => ['format' => 'gfm+definition_lists'],
];

$fixtureCases = [
    'term group repeated definitions' => [
        'document' => $termGroupDoc,
        'expected' => "Primary\nAlias\n*Synonym*\n:   First\n:   Second *body*",
        'termText' => 'Primary Alias Synonym',
        'definitionTypes' => ['definition', 'definition'],
        'definitionChildTypes' => [['paragraph'], ['paragraph']],
    ],
    'term group detached code definition' => [
        'document' => $detachedBodyDoc,
        'expected' => "Packet\n`packet-alias`\n:\n\n        echo packet;",
        'termText' => 'Packet packet-alias',
        'definitionTypes' => ['definition'],
        'definitionChildTypes' => [['code_block']],
    ],
];

$cases = [];
foreach ($profiles as $profileLabel => $options) {
    foreach ($fixtureCases as $fixtureLabel => $case) {
        $cases[$profileLabel . ' ' . $fixtureLabel] = $case + ['options' => $options];
    }
}

$tests = [
    'records markdown writer definition-list term-group completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(6, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer definition-list term-group completion ' . $label] =
        static function (TestRunner $t) use ($case, $firstDefinitionItem, $inlineText, $label): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            $t->same($case['expected'], $markdown, $markdown);

            $roundTrip = (new MarkdownReader($case['options']))->read($markdown);
            $item = $firstDefinitionItem($roundTrip);
            $term = $item->children[0] ?? new AstNode('missing');
            $definitions = array_slice($item->children, 1);

            $t->same('definition_list', $roundTrip->children[0]->type ?? 'missing', $label);
            $t->same('term', $term->type, $label);
            $t->same($case['termText'], $inlineText($term), $label);
            $t->same(
                $case['definitionTypes'],
                array_map(static fn (AstNode $node): string => $node->type, $definitions),
                $label
            );
            $t->same(
                $case['definitionChildTypes'],
                array_map(
                    static fn (AstNode $node): array => array_map(
                        static fn (AstNode $child): string => $child->type,
                        $node->children
                    ),
                    $definitions
                ),
                $label
            );
        };
}

return $tests;

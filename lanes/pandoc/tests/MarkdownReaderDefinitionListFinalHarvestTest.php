<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$enabledFormats = [
    'markdown' => ['format' => 'markdown'],
    'pandoc' => ['format' => 'pandoc'],
    'commonmark_x' => ['format' => 'commonmark_x'],
    'markdown_phpextra' => ['format' => 'markdown_phpextra'],
    'markdown_mmd' => ['format' => 'markdown_mmd'],
    'markdown_strict+definition_lists' => ['format' => 'markdown_strict+definition_lists'],
    'commonmark+definition_lists' => ['format' => 'commonmark+definition_lists'],
    'gfm+definition_lists' => ['format' => 'gfm+definition_lists'],
    'markdown_github+definition_lists' => ['format' => 'markdown_github+definition_lists'],
];

$disabledFormats = [
    'markdown_strict' => ['format' => 'markdown_strict'],
    'commonmark' => ['format' => 'commonmark'],
    'gfm' => ['format' => 'gfm'],
    'markdown_github' => ['format' => 'markdown_github'],
];

$lineBlockEnabledFormats = [
    'markdown' => true,
    'pandoc' => true,
    'commonmark_x' => true,
];

$definition = static function (array $blocks, bool $loose = false): array {
    $entry = ['blocks' => $blocks];
    if ($loose) {
        $entry['loose'] = true;
    }

    return $entry;
};

$item = static function (string $term, array $definitions, ?array $termTypes = null): array {
    $entry = [
        'term' => $term,
        'definitions' => $definitions,
    ];
    if ($termTypes !== null) {
        $entry['termTypes'] = $termTypes;
    }

    return $entry;
};

$definitionCases = [
    'colon-marker tight' => [
        'markdown' => "term\n: definition",
        'items' => [$item('term', [$definition([['plain', 'definition']])])],
    ],
    'indented colon marker' => [
        'markdown' => "term\n  : spaced marker",
        'items' => [$item('term', [$definition([['plain', 'spaced marker']])])],
    ],
    'tilde marker tight' => [
        'markdown' => "term\n~ alternate marker",
        'items' => [$item('term', [$definition([['plain', 'alternate marker']])])],
    ],
    'loose first definition' => [
        'markdown' => "term\n\n: loose definition",
        'items' => [$item('term', [$definition([['paragraph', 'loose definition']], true)])],
    ],
    'stacked terms' => [
        'markdown' => "term a\nterm b\n: stacked definition",
        'items' => [$item("term a\nterm b", [$definition([['plain', 'stacked definition']])], ['text', 'linebreak', 'text'])],
    ],
    'lazy continuation' => [
        'markdown' => "term\n: first line\ncontinued line",
        'items' => [$item('term', [$definition([['plain', 'first line continued line']])])],
    ],
    'marker-line bullet list' => [
        'markdown' => "term\n: - bullet",
        'items' => [$item('term', [$definition([['bullet_list', 'bullet']])])],
    ],
    'marker-line blockquote' => [
        'markdown' => "term\n: > quoted source",
        'items' => [$item('term', [$definition([['blockquote', 'quoted source']])])],
    ],
    'indented paragraph body' => [
        'markdown' => "term\n:\n    second paragraph",
        'items' => [$item('term', [$definition([['plain', 'second paragraph']])])],
    ],
    'indented code body' => [
        'markdown' => "term\n:\n        code",
        'items' => [$item('term', [$definition([['code_block', 'code']])])],
    ],
    'indented line block body' => [
        'markdown' => "term\n:\n    | line one\n    | line two",
        'items' => [$item('term', [$definition([['line_block', 'line one|line two']])])],
    ],
    'multiple definitions' => [
        'markdown' => "term\n: one\n: two",
        'items' => [$item('term', [$definition([['plain', 'one']]), $definition([['plain', 'two']])])],
    ],
    'multiple items' => [
        'markdown' => "term one\n: one\n\nterm two\n: two",
        'items' => [
            $item('term one', [$definition([['plain', 'one']])]),
            $item('term two', [$definition([['plain', 'two']])]),
        ],
    ],
    'indented heading body' => [
        'markdown' => "term\n:\n    # Heading",
        'items' => [$item('term', [$definition([['heading', 'Heading']])])],
    ],
    'indented ordered list body' => [
        'markdown' => "term\n:\n    1. ordered",
        'items' => [$item('term', [$definition([['ordered_list', 'ordered']])])],
    ],
];

$literalCases = [
    'colon-marker tight' => "term\n: definition",
    'indented colon marker' => "term\n  : spaced marker",
    'tilde marker tight' => "term\n~ alternate marker",
    'loose first definition' => "term\n\n: loose definition",
    'stacked terms' => "term a\nterm b\n: stacked definition",
    'multiple definitions' => "term\n: one\n: two",
];

$nodeTypes = static fn (array $nodes): array => array_map(static fn (AstNode $node): string => $node->type, $nodes);

$blockSummary = static function (AstNode $block): array {
    return match ($block->type) {
        'paragraph', 'plain', 'code_block', 'heading' => [$block->type, (string) $block->attr('text', '')],
        'blockquote' => [$block->type, (string) ($block->children[0] ?? new AstNode('missing'))->attr('text', '')],
        'bullet_list', 'ordered_list' => [
            $block->type,
            (string) (($block->children[0] ?? new AstNode('missing'))->children[0] ?? new AstNode('missing'))->attr('text', ''),
        ],
        'line_block' => [
            $block->type,
            implode('|', array_map(static fn (AstNode $line): string => (string) $line->attr('text', ''), $block->children)),
        ],
        default => [$block->type, ''],
    };
};

return [
    'maps upstream markdown definition-list final-harvest enabled profile matrix' =>
        static function (TestRunner $t) use ($enabledFormats, $lineBlockEnabledFormats, $definitionCases, $nodeTypes, $blockSummary): void {
            $mappedCases = count($enabledFormats) * count($definitionCases);
            $t->same(135, $mappedCases);

            foreach ($enabledFormats as $formatName => $options) {
                $reader = new MarkdownReader($options);
                foreach ($definitionCases as $caseName => $case) {
                    $document = $reader->read($case['markdown']);
                    $list = $document->children[0] ?? new AstNode('missing');
                    $label = "{$formatName} {$caseName}";

                    $t->same('definition_list', $list->type, $label);
                    $t->same(count($case['items']), count($list->children), $label . ' item count');

                    foreach ($case['items'] as $itemIndex => $expectedItem) {
                        $item = $list->children[$itemIndex] ?? new AstNode('missing');
                        $term = $item->children[0] ?? new AstNode('missing');
                        $definitions = array_slice($item->children, 1);

                        $t->same('definition_item', $item->type, $label . " item {$itemIndex}");
                        $t->same($expectedItem['term'], $item->attr('term'), $label . " item {$itemIndex} term");
                        if (isset($expectedItem['termTypes'])) {
                            $t->same($expectedItem['termTypes'], $nodeTypes($term->children), $label . " item {$itemIndex} term children");
                        }
                        $t->same(count($expectedItem['definitions']), count($definitions), $label . " item {$itemIndex} definition count");

                        foreach ($expectedItem['definitions'] as $definitionIndex => $expectedDefinition) {
                            $definition = $definitions[$definitionIndex] ?? new AstNode('missing');
                            $blocks = $definition->children;
                            $expectedLoose = $expectedDefinition['loose'] ?? false;
                            $expectedBlocks = $expectedDefinition['blocks'];
                            if ($caseName === 'indented line block body' && !($lineBlockEnabledFormats[$formatName] ?? false)) {
                                $expectedBlocks = [['plain', '| line one | line two']];
                            }

                            $t->same('definition', $definition->type, $label . " definition {$definitionIndex}");
                            $t->same($expectedLoose, (bool) $definition->attr('loose', false), $label . " definition {$definitionIndex} loose");
                            $t->same(count($expectedBlocks), count($blocks), $label . " definition {$definitionIndex} block count");

                            foreach ($expectedBlocks as $blockIndex => $expectedBlock) {
                                $block = $blocks[$blockIndex] ?? new AstNode('missing');
                                $t->same($expectedBlock, $blockSummary($block), $label . " definition {$definitionIndex} block {$blockIndex}");
                            }
                        }
                    }
                }
            }
        },

    'maps upstream markdown definition-list final-harvest disabled profile literals' =>
        static function (TestRunner $t) use ($disabledFormats, $literalCases, $nodeTypes): void {
            $mappedCases = count($disabledFormats) * count($literalCases);
            $t->same(24, $mappedCases);

            foreach ($disabledFormats as $formatName => $options) {
                $reader = new MarkdownReader($options);
                foreach ($literalCases as $caseName => $markdown) {
                    $document = $reader->read($markdown);
                    $types = $nodeTypes($document->children);
                    $label = "{$formatName} {$caseName}";

                    $t->true(!in_array('definition_list', $types, true), $label . ' should not parse definition_list');
                    $t->true($types !== [], $label . ' should preserve literal block content');
                }
            }
        },

    'records markdown reader definition-list final-harvest mapped-case count' =>
        static function (TestRunner $t) use ($enabledFormats, $definitionCases, $disabledFormats, $literalCases): void {
            $t->same(
                159,
                count($enabledFormats) * count($definitionCases)
                    + count($disabledFormats) * count($literalCases)
            );
        },
];

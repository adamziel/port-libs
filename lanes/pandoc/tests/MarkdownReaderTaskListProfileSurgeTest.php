<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

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

$listItemText = static function (AstNode $item) use ($inlineText): string {
    $parts = [];
    foreach ($item->children as $child) {
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list') {
            continue;
        }
        $part = $inlineText($child);
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return trim(implode(' ', $parts));
};

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$collectTaskStates = static function (AstNode $node) use (&$collectTaskStates): array {
    $states = [];
    if ($node->type === 'list_item' && is_bool($node->attr('taskChecked', null))) {
        $states[] = $node->attr('taskChecked');
    }

    foreach ($node->children as $child) {
        array_push($states, ...$collectTaskStates($child));
    }

    return $states;
};

$read = static function (?string $format, string $markdown): AstNode {
    return (new MarkdownReader($format === null ? [] : ['format' => $format]))->read($markdown);
};

$markers = [
    'dash bullet' => [
        'marker' => '- ',
        'next' => '- next',
        'list' => 'bullet_list',
        'attrs' => ['marker' => '-'],
        'profile' => 'standard',
    ],
    'plus bullet' => [
        'marker' => '+ ',
        'next' => '+ next',
        'list' => 'bullet_list',
        'attrs' => ['marker' => '+'],
        'profile' => 'standard',
    ],
    'star bullet' => [
        'marker' => '* ',
        'next' => '* next',
        'list' => 'bullet_list',
        'attrs' => ['marker' => '*'],
        'profile' => 'standard',
    ],
    'decimal period' => [
        'marker' => '1. ',
        'next' => '2. next',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
        'profile' => 'standard',
    ],
    'decimal paren' => [
        'marker' => '1) ',
        'next' => '2) next',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'one_paren'],
        'profile' => 'standard',
    ],
    'parenthesized decimal' => [
        'marker' => '(1) ',
        'next' => '(2) next',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'two_parens'],
        'profile' => 'fancy',
    ],
    'default ordered' => [
        'marker' => '#. ',
        'next' => '#. next',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'default', 'delimiter' => 'default'],
        'profile' => 'fancy',
    ],
    'numbered example' => [
        'marker' => '(@) ',
        'next' => '(@) next',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
        'profile' => 'example',
    ],
    'upper alpha' => [
        'marker' => 'A.  ',
        'next' => 'B.  next',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period'],
        'profile' => 'fancy',
    ],
    'upper roman' => [
        'marker' => 'I.  ',
        'next' => 'II.  next',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'upper_roman', 'delimiter' => 'period'],
        'profile' => 'fancy',
    ],
];

$continuationIndent = static fn (array $case): string => str_repeat(' ', strlen($case['marker']));

$variants = [
    'unchecked tight item' => static fn (array $case, string $indent): array => [
        'markdown' => $case['marker'] . "[ ] todo\n" . $case['next'],
        'text' => 'todo',
        'taskStates' => [false],
        'childTypes' => ['text'],
    ],
    'checked uppercase item' => static fn (array $case, string $indent): array => [
        'markdown' => $case['marker'] . "[X] done\n" . $case['next'],
        'text' => 'done',
        'taskStates' => [true],
        'childTypes' => ['text'],
    ],
    'lazy continuation item' => static fn (array $case, string $indent): array => [
        'markdown' => $case['marker'] . "[x] alpha\nbeta\n" . $case['next'],
        'text' => 'alpha beta',
        'taskStates' => [true],
        'childTypes' => ['text'],
    ],
    'indented continuation item' => static fn (array $case, string $indent): array => [
        'markdown' => $case['marker'] . "[ ] alpha\n" . $indent . "beta\n" . $case['next'],
        'text' => 'alpha beta',
        'taskStates' => [false],
        'childTypes' => ['text'],
    ],
    'loose paragraph continuation item' => static fn (array $case, string $indent): array => [
        'markdown' => $case['marker'] . "[x] alpha\n\n" . $indent . "beta\n" . $case['next'],
        'text' => 'alpha beta',
        'taskStates' => [true],
        'childTypes' => ['paragraph', 'paragraph'],
        'loose' => true,
    ],
    'nested bullet boundary item' => static fn (array $case, string $indent): array => [
        'markdown' => $case['marker'] . "[ ] parent\n" . $indent . "- child\n" . $case['next'],
        'text' => 'parent',
        'taskStates' => [false],
        'childTypes' => ['text', 'bullet_list'],
        'nestedType' => 'bullet_list',
        'nestedText' => 'child',
    ],
    'nested ordered boundary item' => static fn (array $case, string $indent): array => [
        'markdown' => $case['marker'] . "[x] parent\n" . $indent . "1. child\n" . $case['next'],
        'text' => 'parent',
        'taskStates' => [true],
        'childTypes' => ['text', 'ordered_list'],
        'nestedType' => 'ordered_list',
        'nestedText' => 'child',
    ],
    'nested task boundary item' => static fn (array $case, string $indent): array => [
        'markdown' => $case['marker'] . "[x] parent\n" . $indent . "- [ ] child\n" . $case['next'],
        'text' => 'parent',
        'taskStates' => [true, false],
        'childTypes' => ['text', 'bullet_list'],
        'nestedType' => 'bullet_list',
        'nestedText' => 'child',
        'nestedTaskList' => true,
    ],
    'blockquote block item' => static fn (array $case, string $indent): array => [
        'markdown' => $case['marker'] . "[ ] > quoted\n" . $case['next'],
        'text' => 'quoted',
        'taskStates' => [false],
        'childTypes' => ['blockquote'],
    ],
];

$standardEnabledFormats = [
    'default' => null,
    'markdown' => 'markdown',
    'commonmark_x' => 'commonmark_x',
    'gfm' => 'gfm',
    'commonmark plus task lists' => 'commonmark+task_lists',
    'strict plus task lists' => 'markdown_strict+task_lists',
];

$fancyEnabledFormats = [
    'default' => null,
    'markdown' => 'markdown',
    'commonmark_x' => 'commonmark_x',
    'gfm plus fancy lists' => 'gfm+fancy_lists',
    'commonmark plus task and fancy lists' => 'commonmark+task_lists+fancy_lists',
    'strict plus task and fancy lists' => 'markdown_strict+task_lists+fancy_lists',
];

$exampleEnabledFormats = [
    'default' => null,
    'markdown' => 'markdown',
    'commonmark_x' => 'commonmark_x',
    'gfm plus example lists' => 'gfm+example_lists',
    'commonmark plus task and example lists' => 'commonmark+task_lists+example_lists',
    'strict plus task and numbered examples' => 'markdown_strict+task_lists+numbered_examples',
];

$standardDisabledFormats = [
    'commonmark' => 'commonmark',
    'markdown strict' => 'markdown_strict',
    'php extra' => 'markdown_phpextra',
    'multimarkdown' => 'markdown_mmd',
    'markdown minus task lists' => 'markdown-task_lists',
    'gfm minus task lists' => 'gfm-task_lists',
];

$fancyDisabledFormats = [
    'commonmark plus fancy lists' => 'commonmark+fancy_lists',
    'markdown strict plus fancy lists' => 'markdown_strict+fancy_lists',
    'php extra plus fancy lists' => 'markdown_phpextra+fancy_lists',
    'multimarkdown plus fancy lists' => 'markdown_mmd+fancy_lists',
    'markdown minus task lists' => 'markdown-task_lists',
    'gfm plus fancy lists minus task lists' => 'gfm+fancy_lists-task_lists',
];

$exampleDisabledFormats = [
    'commonmark plus example lists' => 'commonmark+example_lists',
    'markdown strict plus numbered examples' => 'markdown_strict+numbered_examples',
    'php extra plus numbered examples' => 'markdown_phpextra+numbered_examples',
    'multimarkdown plus example lists' => 'markdown_mmd+example_lists',
    'markdown minus task lists' => 'markdown-task_lists',
    'gfm plus example lists minus task lists' => 'gfm+example_lists-task_lists',
];

$assertListAttrs = static function (TestRunner $t, AstNode $list, array $case, string $label): void {
    $t->same($case['list'], $list->type, $label . ' list type');
    $t->same(2, count($list->children), $label . ' sibling item count');
    foreach ($case['attrs'] as $attr => $expected) {
        $t->same($expected, $list->attr($attr), $label . ' list attr ' . $attr);
    }
};

$tests = [];

foreach ($markers as $markerName => $marker) {
    foreach ($variants as $variantName => $buildVariant) {
        $tests['maps upstream markdown reader task-list profile surge ' . $markerName . ' ' . $variantName] =
            static function (TestRunner $t) use (
                $marker,
                $buildVariant,
                $continuationIndent,
                $standardEnabledFormats,
                $fancyEnabledFormats,
                $exampleEnabledFormats,
                $standardDisabledFormats,
                $fancyDisabledFormats,
                $exampleDisabledFormats,
                $read,
                $assertListAttrs,
                $collectTaskStates,
                $childTypes,
                $listItemText
            ): void {
                $case = $buildVariant($marker, $continuationIndent($marker));
                $enabledFormats = match ($marker['profile']) {
                    'fancy' => $fancyEnabledFormats,
                    'example' => $exampleEnabledFormats,
                    default => $standardEnabledFormats,
                };
                $disabledFormats = match ($marker['profile']) {
                    'fancy' => $fancyDisabledFormats,
                    'example' => $exampleDisabledFormats,
                    default => $standardDisabledFormats,
                };

                foreach ($enabledFormats as $formatName => $format) {
                    $document = $read($format, $case['markdown']);
                    $list = $document->children[0] ?? new AstNode('missing');
                    $item = $list->children[0] ?? new AstNode('missing');
                    $label = $formatName . ': ' . $case['markdown'];

                    $assertListAttrs($t, $list, $marker, $label);
                    $t->same((bool) ($case['loose'] ?? false), (bool) $list->attr('loose'), $label . ' loose list state');
                    $t->same($case['taskStates'], $collectTaskStates($list), $label . ' task checkbox states');
                    $t->same($case['text'], $listItemText($item), $label . ' item text');
                    $t->same($case['childTypes'], $childTypes($item), $label . ' item child types');
                    if (isset($case['nestedType'])) {
                        $nested = null;
                        foreach ($item->children as $child) {
                            if ($child->type === $case['nestedType']) {
                                $nested = $child;
                                break;
                            }
                        }
                        $t->true($nested instanceof AstNode, $label . ' expected nested list');
                        if ($nested instanceof AstNode) {
                            $nestedItem = $nested->children[0] ?? new AstNode('missing');
                            $t->same($case['nestedText'], $listItemText($nestedItem), $label . ' nested item text');
                            if (($case['nestedTaskList'] ?? false) === true) {
                                $t->same(true, $nested->attr('taskList'), $label . ' nested task-list metadata');
                            }
                        }
                    }
                }

                foreach ($disabledFormats as $formatName => $format) {
                    $document = $read($format, $case['markdown']);
                    $list = $document->children[0] ?? new AstNode('missing');
                    $item = $list->children[0] ?? new AstNode('missing');
                    $label = $formatName . ': ' . $case['markdown'];

                    $assertListAttrs($t, $list, $marker, $label);
                    $t->same([], $collectTaskStates($list), $label . ' keeps task marker literal');
                    $t->same(null, $list->attr('taskList', null), $label . ' no task-list metadata');
                    $t->true(
                        str_starts_with((string) $item->attr('text', ''), '['),
                        $label . ' first text keeps checkbox marker'
                    );
                }
            };
    }
}

$tests['maps upstream markdown task-list fixture through default profile'] =
    static function (TestRunner $t) use ($childTypes, $collectTaskStates, $listItemText): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-task-list.md');
        $document = (new MarkdownReader())->read($fixture);
        $list = $document->children[0] ?? new AstNode('missing');
        $secondItem = $list->children[1] ?? new AstNode('missing');
        $nested = $secondItem->children[1] ?? new AstNode('missing');

        $t->same('bullet_list', $list->type);
        $t->same(true, $list->attr('taskList'));
        $t->same('-', $list->attr('marker'));
        $t->same(false, $list->attr('loose'));
        $t->same(3, count($list->children));
        $t->same([false, true, false, true], $collectTaskStates($list));
        $t->same('Review imported media', $listItemText($list->children[0] ?? new AstNode('missing')));
        $t->same('Confirm source links', $listItemText($secondItem));
        $t->same(['text', 'bullet_list'], $childTypes($secondItem));
        $t->same('bullet_list', $nested->type);
        $t->same(true, $nested->attr('taskList'));
        $t->same('Attach follow-up', $listItemText($nested->children[0] ?? new AstNode('missing')));
        $t->same('Mark ready to publish', $listItemText($list->children[2] ?? new AstNode('missing')));
    };

$tests['records upstream markdown reader task-list profile surge mapped-case count'] =
    static function (TestRunner $t) use ($markers, $variants): void {
        $t->same(90, count($markers) * count($variants));
    };

return $tests;

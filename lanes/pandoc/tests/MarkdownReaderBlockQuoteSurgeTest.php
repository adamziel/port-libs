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

    return $text;
};

$listItemText = static function (AstNode $item) use ($inlineText): string {
    $parts = [];
    foreach ($item->children as $child) {
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list' || $child->type === 'definition_list') {
            continue;
        }

        $parts[] = trim($inlineText($child));
    }

    return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
};

$nodeTypes = static fn (array $nodes): array => array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
);

$markers = [
    'dash bullet' => [
        'marker' => '- ',
        'next' => '> - next',
        'listType' => 'bullet_list',
    ],
    'plus bullet' => [
        'marker' => '+ ',
        'next' => '> + next',
        'listType' => 'bullet_list',
    ],
    'star bullet' => [
        'marker' => '* ',
        'next' => '> * next',
        'listType' => 'bullet_list',
    ],
    'unchecked task bullet' => [
        'marker' => '- [ ] ',
        'next' => '> - [ ] next',
        'listType' => 'bullet_list',
        'taskList' => true,
        'tasks' => [false, false],
    ],
    'checked task bullet' => [
        'marker' => '- [x] ',
        'next' => '> - [x] next',
        'listType' => 'bullet_list',
        'taskList' => true,
        'tasks' => [true, true],
    ],
    'decimal period ordered' => [
        'marker' => '1. ',
        'next' => '> 2. next',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    'decimal paren ordered' => [
        'marker' => '1) ',
        'next' => '> 2) next',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
    ],
    'default ordered' => [
        'marker' => '#. ',
        'next' => '> #. next',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
    ],
    'numbered example ordered' => [
        'marker' => '(@) ',
        'next' => '> (@) next',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
    ],
    'parenthesized decimal ordered' => [
        'marker' => '(1) ',
        'next' => '> (2) next',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'two_parens',
    ],
    'upper alpha ordered' => [
        'marker' => 'A.  ',
        'next' => '> B.  next',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'upper_alpha',
        'delimiter' => 'period',
    ],
    'upper roman ordered' => [
        'marker' => 'IV.  ',
        'next' => '> V.  next',
        'listType' => 'ordered_list',
        'start' => 4,
        'style' => 'upper_roman',
        'delimiter' => 'period',
    ],
];

$continuations = [
    'unmarked continuation' => [
        'lines' => ['lazy continuation'],
        'text' => 'lead lazy continuation',
    ],
    'indented continuation' => [
        'lines' => ['  indented continuation'],
        'text' => 'lead indented continuation',
    ],
    'two unmarked continuations' => [
        'lines' => ['lazy continuation', 'second continuation'],
        'text' => 'lead lazy continuation second continuation',
    ],
    'emphasis continuation' => [
        'lines' => ['  **strong** continuation'],
        'text' => 'lead strong continuation',
        'firstItemChildTypes' => ['text', 'strong', 'text'],
    ],
    'code continuation' => [
        'lines' => ['  `code` continuation'],
        'text' => 'lead code continuation',
        'firstItemChildTypes' => ['text', 'code', 'text'],
    ],
];

$tests = [];

foreach ($markers as $markerName => $marker) {
    foreach ($continuations as $continuationName => $continuation) {
        $tests["maps commonmark blockquote lazy list continuation {$markerName} {$continuationName}"] = static function (TestRunner $t) use (
            $marker,
            $continuation,
            $listItemText,
            $nodeTypes
        ): void {
            $markdown = '> ' . $marker['marker'] . 'lead' . "\n"
                . implode("\n", $continuation['lines']) . "\n"
                . $marker['next'];
            $document = (new MarkdownReader())->read($markdown);

            $t->same(['blockquote'], $nodeTypes($document->children), $markdown);
            $quote = $document->children[0] ?? new AstNode('missing');
            $t->same([$marker['listType']], $nodeTypes($quote->children), $markdown);

            $list = $quote->children[0] ?? new AstNode('missing');
            $t->same($marker['listType'], $list->type, $markdown);
            $t->same(false, (bool) $list->attr('loose'), $markdown);
            $t->same(2, count($list->children), $markdown);
            $t->same([$continuation['text'], 'next'], array_map($listItemText, $list->children), $markdown);

            if (isset($marker['taskList'])) {
                $t->same($marker['taskList'], (bool) $list->attr('taskList'), $markdown);
            }
            if (isset($marker['tasks'])) {
                $t->same($marker['tasks'], array_map(
                    static fn (AstNode $item): ?bool => $item->attr('taskChecked', null),
                    $list->children
                ), $markdown);
            }
            if (isset($marker['start'])) {
                $t->same($marker['start'], $list->attr('start'), $markdown);
            }
            if (isset($marker['style'])) {
                $t->same($marker['style'], $list->attr('style'), $markdown);
            }
            if (isset($marker['delimiter'])) {
                $t->same($marker['delimiter'], $list->attr('delimiter'), $markdown);
            }
            if (isset($continuation['firstItemChildTypes'])) {
                $t->same($continuation['firstItemChildTypes'], $nodeTypes($list->children[0]->children), $markdown);
            }
        };
    }
}

$tests['records commonmark blockquote lazy list continuation surge mapped-case count'] = static function (TestRunner $t) use ($markers, $continuations): void {
    $t->same(60, count($markers) * count($continuations));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$textOf = static function (AstNode $node) use (&$textOf): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $textOf($child);
    }

    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
};

$listItemText = static function (AstNode $item) use ($textOf): string {
    $parts = [];
    foreach ($item->children as $child) {
        if ($child->type === 'bullet_list' || $child->type === 'ordered_list') {
            continue;
        }
        $parts[] = $textOf($child);
    }

    return trim(implode(' ', array_filter($parts)));
};

$markers = [
    'dash bullet' => [
        'marker' => '- ',
        'next' => '- next',
        'indent' => '  ',
        'list' => 'bullet_list',
    ],
    'plus bullet' => [
        'marker' => '+ ',
        'next' => '+ next',
        'indent' => '  ',
        'list' => 'bullet_list',
    ],
    'star bullet' => [
        'marker' => '* ',
        'next' => '* next',
        'indent' => '  ',
        'list' => 'bullet_list',
    ],
    'decimal period' => [
        'marker' => '1. ',
        'next' => '2. next',
        'indent' => '   ',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'],
    ],
    'decimal paren' => [
        'marker' => '1) ',
        'next' => '2) next',
        'indent' => '   ',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'one_paren'],
    ],
    'default ordered' => [
        'marker' => '#. ',
        'next' => '#. next',
        'indent' => '   ',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'default', 'delimiter' => 'default'],
    ],
    'parenthesized decimal' => [
        'marker' => '(1) ',
        'next' => '(2) next',
        'indent' => '    ',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'decimal', 'delimiter' => 'two_parens'],
    ],
    'numbered example' => [
        'marker' => '(@) ',
        'next' => '(@) next',
        'indent' => '    ',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'example', 'delimiter' => 'two_parens'],
    ],
    'upper alpha' => [
        'marker' => 'A.  ',
        'next' => 'B.  next',
        'indent' => '    ',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'upper_alpha', 'delimiter' => 'period'],
    ],
    'upper roman' => [
        'marker' => 'I.  ',
        'next' => 'II.  next',
        'indent' => '    ',
        'list' => 'ordered_list',
        'attrs' => ['start' => 1, 'style' => 'upper_roman', 'delimiter' => 'period'],
    ],
];

$variants = [
    'level one opening' => static fn (array $case): array => [
        'markdown' => $case['marker'] . "Release title\n" . $case['indent'] . "===\n" . $case['next'],
        'childTypes' => ['heading'],
        'headingText' => 'Release title',
        'headingLevel' => 1,
        'itemText' => 'Release title',
        'itemCount' => 2,
    ],
    'long underline opening' => static fn (array $case): array => [
        'markdown' => $case['marker'] . "Review title\n" . $case['indent'] . "=====\n" . $case['next'],
        'childTypes' => ['heading'],
        'headingText' => 'Review title',
        'headingLevel' => 1,
        'itemText' => 'Review title',
        'itemCount' => 2,
    ],
    'wrapped heading text' => static fn (array $case): array => [
        'markdown' => $case['marker'] . "Release\n" . $case['indent'] . "candidate\n" . $case['indent'] . "===\n" . $case['next'],
        'childTypes' => ['heading'],
        'headingText' => 'Release candidate',
        'headingLevel' => 1,
        'itemText' => 'Release candidate',
        'itemCount' => 2,
    ],
    'loose paragraph before heading' => static fn (array $case): array => [
        'markdown' => $case['marker'] . "intro\n\n" . $case['indent'] . "Nested title\n" . $case['indent'] . "===\n" . $case['next'],
        'childTypes' => ['paragraph', 'heading'],
        'headingText' => 'Nested title',
        'headingLevel' => 1,
        'itemText' => 'intro Nested title',
        'itemCount' => 2,
        'loose' => true,
    ],
    'heading before nested bullet' => static fn (array $case): array => [
        'markdown' => $case['marker'] . "Nested title\n" . $case['indent'] . "===\n" . $case['indent'] . "- child\n" . $case['next'],
        'childTypes' => ['heading', 'bullet_list'],
        'headingText' => 'Nested title',
        'headingLevel' => 1,
        'itemText' => 'Nested title',
        'itemCount' => 2,
        'nestedText' => 'child',
    ],
];

$tests = [];
foreach ($markers as $markerLabel => $marker) {
    foreach ($variants as $variantLabel => $buildVariant) {
        $tests["maps markdown reader list setext heading surge {$markerLabel} {$variantLabel}"] =
            static function (TestRunner $t) use ($buildVariant, $marker, $listItemText): void {
                $case = $buildVariant($marker);
                $document = (new MarkdownReader())->read($case['markdown']);
                $list = $document->children[0] ?? new AstNode('missing');
                $item = $list->children[0] ?? new AstNode('missing');

                $t->same([$marker['list']], array_map(static fn (AstNode $node): string => $node->type, $document->children));
                $t->same($marker['list'], $list->type);
                foreach (($marker['attrs'] ?? []) as $attr => $expected) {
                    $t->same($expected, $list->attr($attr), $case['markdown'] . ' list attr ' . $attr);
                }
                $t->same($case['itemCount'], count($list->children));
                $t->same((bool) ($case['loose'] ?? false), (bool) $list->attr('loose'));
                $t->same($case['childTypes'], array_map(static fn (AstNode $node): string => $node->type, $item->children));
                $t->same($case['itemText'], $listItemText($item));

                $heading = null;
                foreach ($item->children as $child) {
                    if ($child->type === 'heading') {
                        $heading = $child;
                        break;
                    }
                }
                $t->true($heading instanceof AstNode, 'Expected setext heading inside list item');
                if ($heading instanceof AstNode) {
                    $t->same($case['headingText'], $heading->attr('text'));
                    $t->same($case['headingLevel'], $heading->attr('level'));
                    $t->same(str_replace(' ', '-', strtolower($case['headingText'])), $heading->attr('id'));
                }

                if (isset($case['nestedText'])) {
                    $nested = $item->children[1] ?? new AstNode('missing');
                    $nestedItem = $nested->children[0] ?? new AstNode('missing');
                    $t->same('bullet_list', $nested->type);
                    $t->same($case['nestedText'], $listItemText($nestedItem));
                }

                $t->same('next', $listItemText($list->children[1] ?? new AstNode('missing')));
            };
    }
}

$tests['records markdown reader list setext heading surge mapped-case count'] =
    static function (TestRunner $t) use ($markers, $variants): void {
        $t->same(50, count($markers) * count($variants));
    };

return $tests;

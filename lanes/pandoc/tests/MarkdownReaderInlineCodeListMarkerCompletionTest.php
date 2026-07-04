<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$listType = static fn (string $marker): string => $marker === '- ' ? 'bullet_list' : 'ordered_list';

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$collectTypes = null;
$collectTypes = static function (AstNode $node) use (&$collectTypes): array {
    $types = [$node->type];
    foreach ($node->children as $child) {
        array_push($types, ...$collectTypes($child));
    }

    return $types;
};

$codeMarkerCases = [
    'in text' => [
        'source' => 'If `(1) x`, then `2`',
        'codes' => ['(1) x', '2'],
        'text' => 'If , then ',
    ],
    'hash marker at start' => [
        'source' => '`#. x`',
        'codes' => ['#. x'],
        'text' => '',
    ],
    'bullet marker at start' => [
        'source' => '`- x`',
        'codes' => ['- x'],
        'text' => '',
    ],
    'hash marker after literal backticks' => [
        'source' => '`x``#. x`',
        'codes' => ['x``#. x'],
        'text' => '',
    ],
    'bullet marker after literal backticks' => [
        'source' => '`x``- x`',
        'codes' => ['x``- x'],
        'text' => '',
    ],
];

$fixtureCodeMarkerCases = [
    ['codes' => ['(1) x', '2'], 'text' => 'If `(1) x`, then `2`'],
    ['codes' => ['#. x'], 'text' => '`#. x`'],
    ['codes' => ['- x'], 'text' => '`- x`'],
    ['codes' => ['x``#. x'], 'text' => '`x``#. x`'],
    ['codes' => ['x``- x'], 'text' => '`x``- x`'],
];

$listMarkers = ['- ', '1. '];
$listMarkerName = static fn (string $marker): string => $marker === '- ' ? 'bullet' : 'ordered';
$generatedListMarkerTriples = [];
foreach ($listMarkers as $outerMarker) {
    foreach ($listMarkers as $middleMarker) {
        foreach ($listMarkers as $innerMarker) {
            $generatedListMarkerTriples[
                $listMarkerName($outerMarker) . ' then ' . $listMarkerName($middleMarker) . ' ' . $listMarkerName($innerMarker)
            ] = [$outerMarker, $middleMarker, $innerMarker];
        }
    }
}

$tests = [];

$tests['maps selected upstream inline code list marker fixture'] =
    static function (TestRunner $t) use ($fixtureCodeMarkerCases): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-code-list-markers.md');
        $document = (new MarkdownReader())->read($fixture);
        $lists = $document->children;

        $t->same(['bullet_list', 'ordered_list'], array_map(static fn (AstNode $node): string => $node->type, $lists));
        foreach ($lists as $list) {
            $t->same(5, count($list->children));
            foreach ($fixtureCodeMarkerCases as $index => $case) {
                $item = $list->children[$index] ?? new AstNode('missing');
                $codes = array_values(array_filter(
                    $item->children,
                    static fn (AstNode $node): bool => $node->type === 'code'
                ));

                $t->same($case['text'], $item->attr('text'));
                $t->same(
                    $case['codes'],
                    array_map(static fn (AstNode $node): string => $node->attr('text'), $codes)
                );
            }
        }
    };

$tests['maps upstream generated inline code list marker boundary fixture'] =
    static function (TestRunner $t) use ($collectTypes): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-code-list-marker-generated-boundaries.md');
        $document = (new MarkdownReader())->read($fixture);
        $types = $collectTypes($document);
        $headings = array_values(array_filter(
            $document->children,
            static fn (AstNode $node): bool => $node->type === 'heading'
        ));

        $t->same(12, count($headings));
        $t->same(false, in_array('code', $types, true), 'Generated list-marker cases keep multiline backticks as literal text');
        $t->same('newline bullet bullet bullet', $headings[0]->attr('text'));
        $t->same('blank ordered ordered ordered', $headings[array_key_last($headings)]->attr('text'));
    };

foreach (['- ', '1. '] as $marker) {
    foreach ($codeMarkerCases as $name => $case) {
        $tests["maps upstream inline code list marker literal {$marker}{$name}"] =
            static function (TestRunner $t) use ($case, $collectTypes, $findFirstNode, $listType, $marker): void {
                $document = (new MarkdownReader())->read($marker . $case['source']);
                $list = $document->children[0] ?? new AstNode('missing');
                $item = $list->children[0] ?? new AstNode('missing');
                $codes = array_values(array_filter(
                    $item->children,
                    static fn (AstNode $node): bool => $node->type === 'code'
                ));
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same($listType($marker), $list->type);
                $t->same(1, count($list->children));
                $t->same($case['source'], $item->attr('text'));
                $t->same(false, in_array('bullet_list', array_slice($collectTypes($item), 1), true));
                $t->same(false, in_array('ordered_list', array_slice($collectTypes($item), 1), true));
                $t->same($case['codes'], array_map(static fn (AstNode $node): string => $node->attr('text'), $codes));
                foreach ($case['codes'] as $codeText) {
                    $t->contains('<code>' . htmlspecialchars($codeText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>', $blocks);
                }

                if ($case['text'] !== '') {
                    $t->same('code', $findFirstNode($item, 'code')->type);
                    $t->contains('then', $blocks);
                }
            };
    }
}

foreach ($generatedListMarkerTriples as $name => $markers) {
    $tests["maps upstream newline inline code list marker grouping {$name}"] =
        static function (TestRunner $t) use ($listType, $markers): void {
            $texts = ['`text', 'y', 'x`'];
            $document = (new MarkdownReader())->read(implode("\n", [
                $markers[0] . $texts[0],
                $markers[1] . $texts[1],
                $markers[2] . $texts[2],
            ]));

            $groups = [];
            foreach ($markers as $index => $marker) {
                $lastIndex = array_key_last($groups);
                if ($lastIndex !== null && $groups[$lastIndex]['marker'] === $marker) {
                    $groups[$lastIndex]['texts'][] = $texts[$index];
                    continue;
                }

                $groups[] = ['marker' => $marker, 'texts' => [$texts[$index]]];
            }

            $t->same(
                array_map(static fn (array $group): string => $listType($group['marker']), $groups),
                array_map(static fn (AstNode $node): string => $node->type, $document->children)
            );
            foreach ($groups as $index => $group) {
                $list = $document->children[$index] ?? new AstNode('missing');
                $t->same(
                    $group['texts'],
                    array_map(static fn (AstNode $item): string => $item->attr('text'), $list->children)
                );
            }
        };
}

foreach ($generatedListMarkerTriples as $name => $markers) {
    $tests["maps upstream blank-line inline code list marker nesting {$name}"] =
        static function (TestRunner $t) use ($listType, $markers): void {
            [$outerMarker, $middleMarker, $innerMarker] = $markers;
            $document = (new MarkdownReader())->read(implode("\n", [
                $outerMarker . '`text',
                '',
                '    ' . $middleMarker . 'y',
                '',
                '    ' . $innerMarker . 'x`',
            ]));
            $outer = $document->children[0] ?? new AstNode('missing');
            $outerItem = $outer->children[0] ?? new AstNode('missing');
            $nestedLists = array_values(array_filter(
                $outerItem->children,
                static fn (AstNode $node): bool => in_array($node->type, ['bullet_list', 'ordered_list'], true)
            ));

            $t->same($listType($outerMarker), $outer->type);
            $t->same('paragraph', ($outerItem->children[0] ?? new AstNode('missing'))->type);
            $t->same('`text', ($outerItem->children[0] ?? new AstNode('missing'))->attr('text'));

            if ($middleMarker === $innerMarker) {
                $grouped = $nestedLists[0] ?? new AstNode('missing');
                $t->same(1, count($nestedLists));
                $t->same($listType($middleMarker), $grouped->type);
                $t->same(2, count($grouped->children));
                $t->same('y', ($grouped->children[0] ?? new AstNode('missing'))->attr('text'));
                $t->same('x`', ($grouped->children[1] ?? new AstNode('missing'))->attr('text'));

                return;
            }

            $middle = $nestedLists[0] ?? new AstNode('missing');
            $inner = $nestedLists[1] ?? new AstNode('missing');

            $t->same(2, count($nestedLists));
            $t->same($listType($middleMarker), $middle->type);
            $t->same('y', ($middle->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same($listType($innerMarker), $inner->type);
            $t->same('x`', ($inner->children[0] ?? new AstNode('missing'))->attr('text'));
        };
}

$tests['records upstream inline code list marker mapped-case count'] =
    static function (TestRunner $t) use ($codeMarkerCases, $generatedListMarkerTriples): void {
        $t->same(26, (2 * count($codeMarkerCases)) + (2 * count($generatedListMarkerTriples)));
    };

return $tests;

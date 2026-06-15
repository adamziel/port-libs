<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$markerCases = [
    'dash bullet' => [
        'marker' => '- ',
        'next' => '- next',
        'indent' => '  ',
        'listType' => 'bullet_list',
    ],
    'plus bullet' => [
        'marker' => '+ ',
        'next' => '+ next',
        'indent' => '  ',
        'listType' => 'bullet_list',
    ],
    'star bullet' => [
        'marker' => '* ',
        'next' => '* next',
        'indent' => '  ',
        'listType' => 'bullet_list',
    ],
    'decimal period' => [
        'marker' => '1. ',
        'next' => '2. next',
        'indent' => '   ',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'period',
    ],
    'decimal paren' => [
        'marker' => '1) ',
        'next' => '2) next',
        'indent' => '   ',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
    ],
    'two-paren decimal' => [
        'marker' => '(1) ',
        'next' => '(2) next',
        'indent' => '    ',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'decimal',
        'delimiter' => 'two_parens',
    ],
    'upper alpha period' => [
        'marker' => 'A.  ',
        'next' => 'B.  next',
        'indent' => '    ',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'upper_alpha',
        'delimiter' => 'period',
    ],
    'lower alpha paren' => [
        'marker' => 'a)  ',
        'next' => 'b)  next',
        'indent' => '    ',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'lower_alpha',
        'delimiter' => 'one_paren',
    ],
    'upper roman period' => [
        'marker' => 'IV.  ',
        'next' => 'V.  next',
        'indent' => '     ',
        'listType' => 'ordered_list',
        'start' => 4,
        'style' => 'upper_roman',
        'delimiter' => 'period',
    ],
    'default ordered' => [
        'marker' => '#. ',
        'next' => '#. next',
        'indent' => '   ',
        'listType' => 'ordered_list',
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
    ],
];

$slug = static function (string $label): string {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label) ?? $label);

    return trim($slug, '-');
};

$read = static fn (string $markdown): AstNode => (new MarkdownReader())->read($markdown);
$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$assertList = static function (TestRunner $t, AstNode $document, array $case, int $itemCount = 2): AstNode {
    $list = $document->children[0] ?? new AstNode('missing');
    $t->same($case['listType'], $list->type);
    $t->same($itemCount, count($list->children));

    foreach (['start', 'style', 'delimiter'] as $attribute) {
        if (array_key_exists($attribute, $case)) {
            $t->same($case[$attribute], $list->attr($attribute), $attribute);
        }
    }

    if ($itemCount > 1) {
        $next = $list->children[1] ?? new AstNode('missing');
        $t->same('next', $next->attr('text'));
    }

    return $list;
};

$tests = [];

foreach ($markerCases as $label => $case) {
    $caseSlug = $slug($label);

    $tests["maps markdown reader list-item equals setext heading opening {$label}"] =
        static function (TestRunner $t) use ($read, $childTypes, $assertList, $case, $label, $caseSlug): void {
            $document = $read($case['marker'] . 'Setext ' . $label . "\n" . $case['indent'] . "===\n" . $case['next']);
            $list = $assertList($t, $document, $case);
            $item = $list->children[0] ?? new AstNode('missing');
            $heading = $item->children[0] ?? new AstNode('missing');

            $t->same(['heading'], $childTypes($item));
            $t->same(1, $heading->attr('level'));
            $t->same('Setext ' . $label, $heading->attr('text'));
            $t->same('setext-' . $caseSlug, $heading->attr('id'));
        };

    $tests["maps markdown reader list-item equals setext multiline heading {$label}"] =
        static function (TestRunner $t) use ($read, $childTypes, $assertList, $case, $label, $caseSlug): void {
            $document = $read($case['marker'] . 'Wrapped ' . $label . "\n" . $case['indent'] . 'import review' . "\n" . $case['indent'] . "===\n" . $case['next']);
            $list = $assertList($t, $document, $case);
            $item = $list->children[0] ?? new AstNode('missing');
            $heading = $item->children[0] ?? new AstNode('missing');

            $t->same(['heading'], $childTypes($item));
            $t->same('Wrapped ' . $label . ' import review', $heading->attr('text'));
            $t->same('wrapped-' . $caseSlug . '-import-review', $heading->attr('id'));
        };

    $tests["maps markdown reader list-item equals setext attributed heading {$label}"] =
        static function (TestRunner $t) use ($read, $childTypes, $assertList, $case, $label, $caseSlug): void {
            $id = 'setext-list-' . $caseSlug;
            $document = $read(
                $case['marker'] . 'Attributed ' . $label . ' {#' . $id . ' .surge data-case="' . $caseSlug . "\"}\n"
                . $case['indent'] . "===\n" . $case['next']
            );
            $list = $assertList($t, $document, $case);
            $item = $list->children[0] ?? new AstNode('missing');
            $heading = $item->children[0] ?? new AstNode('missing');

            $t->same(['heading'], $childTypes($item));
            $t->same('Attributed ' . $label, $heading->attr('text'));
            $t->same($id, $heading->attr('id'));
            $t->same(['surge'], $heading->attr('classes'));
            $t->same(['data-case' => $caseSlug], $heading->attr('attributes'));
        };

    $tests["maps markdown reader list-item equals setext loose second block {$label}"] =
        static function (TestRunner $t) use ($read, $childTypes, $assertList, $case, $label): void {
            $document = $read($case['marker'] . 'Lead paragraph' . "\n\n" . $case['indent'] . 'Loose heading ' . $label . "\n" . $case['indent'] . "===\n" . $case['next']);
            $list = $assertList($t, $document, $case);
            $item = $list->children[0] ?? new AstNode('missing');
            $paragraph = $item->children[0] ?? new AstNode('missing');
            $heading = $item->children[1] ?? new AstNode('missing');

            $t->same(true, (bool) $list->attr('loose'));
            $t->same(true, (bool) $item->attr('loose'));
            $t->same(['paragraph', 'heading'], $childTypes($item));
            $t->same('Lead paragraph', $paragraph->attr('text'));
            $t->same('Loose heading ' . $label, $heading->attr('text'));
        };

    $tests["maps markdown reader list-item equals setext followed by item paragraph {$label}"] =
        static function (TestRunner $t) use ($read, $childTypes, $assertList, $case, $label): void {
            $document = $read($case['marker'] . 'Heading ' . $label . "\n" . $case['indent'] . "===\n" . $case['indent'] . 'following paragraph' . "\n" . $case['next']);
            $list = $assertList($t, $document, $case);
            $item = $list->children[0] ?? new AstNode('missing');
            $heading = $item->children[0] ?? new AstNode('missing');
            $paragraph = $item->children[1] ?? new AstNode('missing');

            $t->same(['heading', 'paragraph'], $childTypes($item));
            $t->same('Heading ' . $label, $heading->attr('text'));
            $t->same('following paragraph', $paragraph->attr('text'));
        };

    $tests["maps markdown reader list-item equals setext before outdented paragraph {$label}"] =
        static function (TestRunner $t) use ($read, $childTypes, $assertList, $case, $label): void {
            $document = $read($case['marker'] . 'Outer heading ' . $label . "\n" . $case['indent'] . "===\nAfter paragraph");
            $list = $assertList($t, $document, $case, 1);
            $item = $list->children[0] ?? new AstNode('missing');
            $heading = $item->children[0] ?? new AstNode('missing');
            $after = $document->children[1] ?? new AstNode('missing');

            $t->same([$case['listType'], 'paragraph'], $childTypes($document));
            $t->same(['heading'], $childTypes($item));
            $t->same('Outer heading ' . $label, $heading->attr('text'));
            $t->same('After paragraph', $after->attr('text'));
        };
}

$tests['records markdown reader list-item equals setext mapped-case count'] =
    static function (TestRunner $t) use ($markerCases): void {
        $t->same(60, count($markerCases) * 6);
    };

return $tests;

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

    return trim(preg_replace('/\s+/', ' ', $text) ?? '');
};

$read = static fn (?string $format, string $markdown): AstNode =>
    (new MarkdownReader($format === null ? [] : ['format' => $format]))->read($markdown);

$assertOrdered = static function (TestRunner $t, AstNode $document, array $case, string $label) use ($inlineText): void {
    $list = $document->children[0] ?? new AstNode('missing');
    $item = $list->children[0] ?? new AstNode('missing');

    $t->same(['ordered_list'], array_map(
        static fn (AstNode $node): string => $node->type,
        $document->children
    ), $label);
    $t->same($case['start'], $list->attr('start'), $label . ' start');
    $t->same($case['style'], $list->attr('style'), $label . ' style');
    $t->same($case['delimiter'], $list->attr('delimiter'), $label . ' delimiter');
    $t->same($case['text'], $inlineText($item), $label . ' item text');
    if (array_key_exists('exampleLabel', $case)) {
        $t->same($case['exampleLabel'], $item->attr('exampleLabel', null), $label . ' example label');
    }
};

$assertParagraph = static function (TestRunner $t, AstNode $document, array $case, string $label) use ($inlineText): void {
    $paragraph = $document->children[0] ?? new AstNode('missing');

    $t->same(['paragraph'], array_map(
        static fn (AstNode $node): string => $node->type,
        $document->children
    ), $label);
    $t->same($case['literal'], $inlineText($paragraph), $label . ' literal text');
};

$assertHeading = static function (TestRunner $t, AstNode $document, array $case, string $label) use ($inlineText): void {
    $heading = $document->children[0] ?? new AstNode('missing');

    $t->same(['heading'], array_map(
        static fn (AstNode $node): string => $node->type,
        $document->children
    ), $label);
    $t->same(1, $heading->attr('level'), $label . ' level');
    $t->same($case['strictHeadingText'], $inlineText($heading), $label . ' heading text');
    $t->same('', $heading->attr('id', ''), $label . ' id');
};

$fancyMarkers = [
    'default period marker' => [
        'markdown' => '#. default ordered',
        'literal' => '#. default ordered',
        'strictHeadingText' => '. default ordered',
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
        'text' => 'default ordered',
    ],
    'default paren marker' => [
        'markdown' => '#) default paren',
        'literal' => '#) default paren',
        'strictHeadingText' => ') default paren',
        'start' => 1,
        'style' => 'default',
        'delimiter' => 'default',
        'text' => 'default paren',
    ],
    'parenthesized decimal marker' => [
        'markdown' => '(3) decimal ordered',
        'literal' => '(3) decimal ordered',
        'start' => 3,
        'style' => 'decimal',
        'delimiter' => 'two_parens',
        'text' => 'decimal ordered',
    ],
    'parenthesized upper alpha marker' => [
        'markdown' => '(B)  alpha ordered',
        'literal' => '(B) alpha ordered',
        'start' => 2,
        'style' => 'upper_alpha',
        'delimiter' => 'two_parens',
        'text' => 'alpha ordered',
    ],
    'upper alpha period marker' => [
        'markdown' => 'B.  upper alpha ordered',
        'literal' => 'B. upper alpha ordered',
        'start' => 2,
        'style' => 'upper_alpha',
        'delimiter' => 'period',
        'text' => 'upper alpha ordered',
    ],
    'lower alpha paren marker' => [
        'markdown' => 'b)  lower alpha ordered',
        'literal' => 'b) lower alpha ordered',
        'start' => 2,
        'style' => 'lower_alpha',
        'delimiter' => 'one_paren',
        'text' => 'lower alpha ordered',
    ],
    'upper roman period marker' => [
        'markdown' => 'IV. upper roman ordered',
        'literal' => 'IV. upper roman ordered',
        'start' => 4,
        'style' => 'upper_roman',
        'delimiter' => 'period',
        'text' => 'upper roman ordered',
    ],
    'lower roman period marker' => [
        'markdown' => 'iv. lower roman ordered',
        'literal' => 'iv. lower roman ordered',
        'start' => 4,
        'style' => 'lower_roman',
        'delimiter' => 'period',
        'text' => 'lower roman ordered',
    ],
];

$exampleMarkers = [
    'anonymous example marker' => [
        'markdown' => '(@) anonymous example',
        'literal' => '(@) anonymous example',
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
        'text' => 'anonymous example',
        'exampleLabel' => null,
    ],
    'labeled example marker' => [
        'markdown' => '(@review) labeled example',
        'literal' => '(@review) labeled example',
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
        'text' => 'labeled example',
        'exampleLabel' => 'review',
    ],
    'hyphenated example marker' => [
        'markdown' => '(@case-42) hyphen example',
        'literal' => '(@case-42) hyphen example',
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
        'text' => 'hyphen example',
        'exampleLabel' => 'case-42',
    ],
    'underscored example marker' => [
        'markdown' => '(@case_42) underscore example',
        'literal' => '(@case_42) underscore example',
        'start' => 1,
        'style' => 'example',
        'delimiter' => 'two_parens',
        'text' => 'underscore example',
        'exampleLabel' => 'case_42',
    ],
];

$standardMarkers = [
    'decimal period marker' => [
        'markdown' => '7. decimal period',
        'start' => 7,
        'style' => 'decimal',
        'delimiter' => 'period',
        'text' => 'decimal period',
    ],
    'decimal paren marker' => [
        'markdown' => '7) decimal paren',
        'start' => 7,
        'style' => 'decimal',
        'delimiter' => 'one_paren',
        'text' => 'decimal paren',
    ],
];

$fancyEnabledFormats = [
    'default' => null,
    'markdown' => 'markdown',
    'pandoc' => 'pandoc',
    'commonmark_x' => 'commonmark_x',
    'commonmark plus fancy_lists' => 'commonmark+fancy_lists',
    'gfm plus fancy_lists' => 'gfm+fancy_lists',
];

$fancyDisabledFormats = [
    'commonmark' => 'commonmark',
    'gfm' => 'gfm',
    'markdown_strict' => 'markdown_strict',
    'markdown minus fancy_lists' => 'markdown-fancy_lists',
];

$exampleEnabledFormats = [
    'default' => null,
    'markdown' => 'markdown',
    'pandoc' => 'pandoc',
    'commonmark_x' => 'commonmark_x',
    'commonmark plus numbered_examples' => 'commonmark+numbered_examples',
    'gfm plus example_lists alias' => 'gfm+example_lists',
];

$exampleDisabledFormats = [
    'commonmark' => 'commonmark',
    'gfm' => 'gfm',
    'markdown_strict' => 'markdown_strict',
    'markdown minus numbered_examples' => 'markdown-numbered_examples',
    'markdown minus example_lists alias' => 'markdown-example_lists',
];

$standardFormats = [
    'commonmark' => 'commonmark',
    'gfm' => 'gfm',
    'markdown_strict' => 'markdown_strict',
    'markdown minus fancy_lists' => 'markdown-fancy_lists',
    'markdown minus numbered_examples' => 'markdown-numbered_examples',
];

$tests = [
    'records upstream markdown reader list marker profile mapped-case count' =>
        static function (TestRunner $t) use (
            $fancyMarkers,
            $exampleMarkers,
            $standardMarkers,
            $fancyEnabledFormats,
            $fancyDisabledFormats,
            $exampleEnabledFormats,
            $exampleDisabledFormats,
            $standardFormats
        ): void {
            $t->same(
                134,
                count($fancyMarkers) * count($fancyEnabledFormats)
                    + count($fancyMarkers) * count($fancyDisabledFormats)
                    + count($exampleMarkers) * count($exampleEnabledFormats)
                    + count($exampleMarkers) * count($exampleDisabledFormats)
                    + count($standardMarkers) * count($standardFormats)
            );
        },
];

foreach ($fancyEnabledFormats as $formatName => $format) {
    foreach ($fancyMarkers as $markerName => $case) {
        $tests["maps upstream markdown reader fancy list profile enabled {$formatName} {$markerName}"] =
            static function (TestRunner $t) use ($read, $assertOrdered, $format, $case, $formatName, $markerName): void {
                $assertOrdered($t, $read($format, $case['markdown']), $case, "{$formatName} {$markerName}");
            };
    }
}

foreach ($fancyDisabledFormats as $formatName => $format) {
    foreach ($fancyMarkers as $markerName => $case) {
        $tests["keeps upstream markdown reader fancy list profile disabled literal {$formatName} {$markerName}"] =
            static function (TestRunner $t) use ($read, $assertHeading, $assertParagraph, $format, $case, $formatName, $markerName): void {
                if ($format === 'markdown_strict' && isset($case['strictHeadingText'])) {
                    $assertHeading($t, $read($format, $case['markdown']), $case, "{$formatName} {$markerName}");

                    return;
                }

                $assertParagraph($t, $read($format, $case['markdown']), $case, "{$formatName} {$markerName}");
            };
    }
}

foreach ($exampleEnabledFormats as $formatName => $format) {
    foreach ($exampleMarkers as $markerName => $case) {
        $tests["maps upstream markdown reader example list profile enabled {$formatName} {$markerName}"] =
            static function (TestRunner $t) use ($read, $assertOrdered, $format, $case, $formatName, $markerName): void {
                $assertOrdered($t, $read($format, $case['markdown']), $case, "{$formatName} {$markerName}");
            };
    }
}

foreach ($exampleDisabledFormats as $formatName => $format) {
    foreach ($exampleMarkers as $markerName => $case) {
        $tests["keeps upstream markdown reader example list profile disabled literal {$formatName} {$markerName}"] =
            static function (TestRunner $t) use ($read, $assertParagraph, $format, $case, $formatName, $markerName): void {
                $assertParagraph($t, $read($format, $case['markdown']), $case, "{$formatName} {$markerName}");
            };
    }
}

foreach ($standardFormats as $formatName => $format) {
    foreach ($standardMarkers as $markerName => $case) {
        $tests["keeps upstream markdown reader standard ordered marker enabled {$formatName} {$markerName}"] =
            static function (TestRunner $t) use ($read, $assertOrdered, $format, $case, $formatName, $markerName): void {
                $assertOrdered($t, $read($format, $case['markdown']), $case, "{$formatName} {$markerName}");
            };
    }
}

return $tests;

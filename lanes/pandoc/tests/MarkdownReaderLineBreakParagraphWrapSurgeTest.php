<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$profiles = [
    'markdown default' => ['options' => ['format' => 'markdown'], 'mode' => 'soft'],
    'pandoc default' => ['options' => ['format' => 'pandoc'], 'mode' => 'soft'],
    'commonmark default' => ['options' => ['format' => 'commonmark'], 'mode' => 'soft'],
    'commonmark_x default' => ['options' => ['format' => 'commonmark_x'], 'mode' => 'soft'],
    'gfm default' => ['options' => ['format' => 'gfm'], 'mode' => 'soft'],
    'markdown_strict default' => ['options' => ['format' => 'markdown_strict'], 'mode' => 'soft'],
    'markdown hard_line_breaks' => ['options' => ['format' => 'markdown+hard_line_breaks'], 'mode' => 'hard'],
    'commonmark hard_line_breaks' => ['options' => ['format' => 'commonmark+hard_line_breaks'], 'mode' => 'hard'],
    'gfm hard_line_breaks' => ['options' => ['format' => 'gfm+hard_line_breaks'], 'mode' => 'hard'],
    'markdown ignore_line_breaks' => ['options' => ['format' => 'markdown+ignore_line_breaks'], 'mode' => 'ignore'],
];

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$lineExpectation = static function (array $segments, string $mode): array {
    if ($mode === 'ignore') {
        return [
            'text' => implode('', $segments),
            'inlineTypes' => ['text'],
        ];
    }

    $break = $mode === 'hard' ? 'linebreak' : 'softbreak';
    $types = [];
    foreach ($segments as $index => $_segment) {
        if ($index > 0) {
            $types[] = $break;
        }
        $types[] = 'text';
    }

    return [
        'text' => implode($mode === 'hard' ? "\n" : ' ', $segments),
        'inlineTypes' => $types,
    ];
};

$hardBreakExpectation = static function (array $segments): array {
    $types = [];
    foreach ($segments as $index => $_segment) {
        if ($index > 0) {
            $types[] = 'linebreak';
        }
        $types[] = 'text';
    }

    return [
        'text' => implode("\n", $segments),
        'inlineTypes' => $types,
    ];
};

$cases = [
    'soft wrap trims indentation' => [
        'markdown' => "  alpha\n  beta",
        'expect' => static fn (string $mode): array => $lineExpectation(['alpha', 'beta'], $mode),
    ],
    'single trailing space remains profile soft line' => [
        'markdown' => "alpha \nbeta",
        'expect' => static fn (string $mode): array => $lineExpectation(['alpha', 'beta'], $mode),
    ],
    'three-space continuation trims paragraph line edges' => [
        'markdown' => "alpha\n   beta",
        'expect' => static fn (string $mode): array => $lineExpectation(['alpha', 'beta'], $mode),
    ],
    'three physical lines keep profile break policy' => [
        'markdown' => "alpha\nbeta\ngamma",
        'expect' => static fn (string $mode): array => $lineExpectation(['alpha', 'beta', 'gamma'], $mode),
    ],
    'two-space marker hardbreaks between paragraph lines' => [
        'markdown' => "alpha  \nbeta",
        'expect' => static fn (string $_mode): array => $hardBreakExpectation(['alpha', 'beta']),
    ],
    'three-space marker hardbreaks between paragraph lines' => [
        'markdown' => "alpha   \nbeta",
        'expect' => static fn (string $_mode): array => $hardBreakExpectation(['alpha', 'beta']),
    ],
    'escaped newline hardbreaks between paragraph lines' => [
        'markdown' => "alpha\\\nbeta",
        'expect' => static fn (string $_mode): array => $hardBreakExpectation(['alpha', 'beta']),
    ],
    'trailing-space marker before blank boundary trims' => [
        'markdown' => "alpha  \n\nbeta",
        'blocks' => ['paragraph', 'paragraph'],
        'texts' => ['alpha', 'beta'],
    ],
    'trailing-space marker before atx boundary trims' => [
        'markdown' => "alpha  \n# beta",
        'blocks' => static fn (string $profileName): array =>
            $profileName === 'pandoc default' || str_starts_with($profileName, 'markdown ')
                ? ['paragraph']
                : ['paragraph', 'heading'],
        'texts' => static fn (string $profileName): array =>
            $profileName === 'pandoc default' || str_starts_with($profileName, 'markdown ')
                ? ["alpha\n# beta"]
                : ['alpha', 'beta'],
        'headingLevel' => static fn (string $profileName): ?int =>
            $profileName === 'pandoc default' || str_starts_with($profileName, 'markdown ')
                ? null
                : 1,
    ],
];

return [
    'maps upstream markdown reader linebreak paragraph wrap final harvest cases' =>
        static function (TestRunner $t) use ($profiles, $cases, $inlineTypes): void {
            foreach ($profiles as $profileName => $profile) {
                foreach ($cases as $caseName => $case) {
                    $label = "{$profileName} {$caseName}";
                    $document = (new MarkdownReader($profile['options']))->read($case['markdown']);
                    $expectedBlocks = $case['blocks'] ?? ['paragraph'];
                    if (is_callable($expectedBlocks)) {
                        $expectedBlocks = $expectedBlocks($profileName, $profile);
                    }

                    $t->same($expectedBlocks, array_map(static fn (AstNode $node): string => $node->type, $document->children), $label . ' block types');

                    if (isset($case['expect'])) {
                        $paragraph = $document->children[0] ?? new AstNode('missing');
                        $expect = $case['expect']($profile['mode']);
                        $t->same('paragraph', $paragraph->type, $label . ' paragraph type');
                        $t->same($expect['text'], $paragraph->attr('text'), $label . ' paragraph text');
                        $t->same($expect['inlineTypes'], $inlineTypes($paragraph), $label . ' inline types');
                        continue;
                    }

                    $texts = $case['texts'];
                    if (is_callable($texts)) {
                        $texts = $texts($profileName, $profile);
                    }
                    foreach ($texts as $index => $text) {
                        $node = $document->children[$index] ?? new AstNode('missing');
                        $t->same($text, $node->attr('text'), $label . " block {$index} text");
                    }

                    $headingLevel = $case['headingLevel'] ?? null;
                    if (is_callable($headingLevel)) {
                        $headingLevel = $headingLevel($profileName, $profile);
                    }
                    if ($headingLevel !== null) {
                        $heading = $document->children[1] ?? new AstNode('missing');
                        $t->same($headingLevel, $heading->attr('level'), $label . ' heading level');
                    }
                }
            }
        },
    'records upstream markdown reader linebreak paragraph wrap mapped-case count' =>
        static function (TestRunner $t) use ($profiles, $cases): void {
            $t->same(90, count($profiles) * count($cases));
        },
];

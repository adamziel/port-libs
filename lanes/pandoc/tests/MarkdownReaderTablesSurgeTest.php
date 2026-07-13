<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$tableFixtures = [
    'pipe' => [
        'markdown' => implode("\n", [
            '| Item | Count |',
            '|:-----|------:|',
            '| Posts | 42 |',
        ]),
        'alignments' => ['left', 'right'],
        'header' => 'Item',
        'body' => '42',
    ],
    'simple' => [
        'markdown' => implode("\n", [
            'Item    Count',
            '------  -----',
            'Posts   42',
        ]),
        'alignments' => ['left', 'default'],
        'header' => 'Item',
        'body' => '42',
    ],
    'grid' => [
        'markdown' => implode("\n", [
            '+-------+-------+',
            '| Item  | Count |',
            '+=======+=======+',
            '| Posts | 42    |',
            '+-------+-------+',
        ]),
        'alignments' => ['default', 'default'],
        'header' => 'Item',
        'body' => '42',
    ],
];

$captionedMarkdown = static function (string $table, string $position, string $marker, string $caption): string {
    $captionLine = $marker . ($caption === '' ? '' : ' ' . $caption);

    return $position === 'before-table'
        ? $captionLine . "\n\n" . $table
        : $table . "\n\n" . $captionLine;
};

$firstTable = static function (AstNode $document): AstNode {
    foreach ($document->children as $node) {
        if ($node->type === 'table') {
            return $node;
        }
    }

    return new AstNode('missing');
};

$inlineTypes = static fn (array $nodes): array => array_values(array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
));

$assertTableShape = static function (TestRunner $t, AstNode $table, array $fixture): void {
    $t->same('table', $table->type);
    $t->same($fixture['alignments'], $table->attr('alignments'));
    $t->same($fixture['header'], $table->children[0]->children[0]->children[0]->attr('text'));
    $t->same($fixture['body'], $table->children[1]->children[0]->children[1]->attr('text'));
};

$tests = [];
$caseNumber = 1;

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        foreach (['plain' => 'Short label', 'formatted' => 'Short **label**'] as $style => $short) {
            $number = $caseNumber++;
            $tests["maps upstream markdown reader table short caption {$tableName} {$position} {$style}"] =
                static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $inlineTypes, $assertTableShape, $fixture, $position, $marker, $short, $style, $number): void {
                    $caption = "[{$short} {$number}] Long **caption** {$number}";
                    $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, $caption));
                    $table = $firstTable($document);
                    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

                    $assertTableShape($t, $table, $fixture);
                    $t->same("Long **caption** {$number}", $table->attr('caption'));
                    $t->same("Long caption {$number}", $packet['captions']['long']['text'] ?? null);
                    $t->same("Short label {$number}", $table->attr('shortCaption'));
                    $t->same($style === 'formatted' ? ['text', 'strong', 'text'] : ['text'], $inlineTypes($table->attr('shortCaptionInlines')));
                    $t->same(true, $packet['summary']['hasShortCaption'] ?? null);
                    $t->same($position, $table->attr('captionSource')['position'] ?? null);
                };
        }
    }
}

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table' => 'Caption:', 'after-table' => ':'] as $position => $marker) {
        foreach (['data' => 'data-source', 'lang' => 'lang'] as $style => $attributeName) {
            $number = $caseNumber++;
            $id = "tbl-{$tableName}-{$position}-{$style}";
            $tests["maps upstream markdown reader table caption attributes {$tableName} {$position} {$style}"] =
                static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $assertTableShape, $fixture, $position, $marker, $tableName, $attributeName, $number, $id): void {
                    $caption = $attributeName === 'data-source'
                        ? "Attributed caption {$number} {#{$id} .review .{$tableName} data-source=\"batch-{$number}\" title=\"Review &amp; table\"}"
                        : "Attributed caption {$number} {#{$id} .review .{$tableName} lang=\"en-US\" title=\"{$tableName} table\"}";
                    $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, $caption));
                    $table = $firstTable($document);

                    $assertTableShape($t, $table, $fixture);
                    $t->same("Attributed caption {$number}", $table->attr('caption'));
                    $t->same($id, $table->attr('id'));
                    $t->same(['review', $tableName], $table->attr('classes'));
                    $t->same($attributeName === 'data-source' ? "batch-{$number}" : 'en-US', $table->attr('attributes')[$attributeName] ?? null);
                };
        }
    }
}

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table', 'after-table'] as $position) {
        foreach (['Table:', 'Caption:', ':'] as $marker) {
            $number = $caseNumber++;
            $tests["maps upstream markdown reader table caption source {$tableName} {$position} {$marker}"] =
                static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $assertTableShape, $fixture, $position, $marker, $number): void {
                    $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, "Source marker caption {$number}"));
                    $table = $firstTable($document);
                    $source = $table->attr('captionSource');
                    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

                    $assertTableShape($t, $table, $fixture);
                    $t->same("Source marker caption {$number}", $table->attr('caption'));
                    $t->same('markdown-table-caption', $source['element'] ?? null);
                    $t->same($position, $source['position'] ?? null);
                    $t->same($marker, $source['marker'] ?? null);
                    $t->same($position === 'before-table' ? 'top' : 'bottom', $source['captionSide'] ?? null);
                    $t->same($position === 'before-table' ? 'before-table' : 'after-table', $packet['summary']['captionPlacement'] ?? null);
                };
        }
    }
}

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        foreach (['link-continuation', 'code-continuation'] as $style) {
            $number = $caseNumber++;
            $tests["maps upstream markdown reader multiline table caption {$tableName} {$position} {$style}"] =
                static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $inlineTypes, $assertTableShape, $fixture, $position, $marker, $style, $number): void {
                    $continuation = $style === 'link-continuation'
                        ? "  continuation with [review link](/review-{$number})"
                        : "  continuation with `code {$number}`";
                    $caption = "Multiline **caption** {$number}\n{$continuation}";
                    $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, $caption));
                    $table = $firstTable($document);
                    $types = $inlineTypes($table->attr('captionInlines'));
                    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

                    $assertTableShape($t, $table, $fixture);
                    $t->contains("Multiline **caption** {$number}\ncontinuation", $table->attr('caption'));
                    $t->contains('strong', implode(',', $types));
                    $t->contains($style === 'link-continuation' ? 'link' : 'code', implode(',', $types));
                    $t->same(true, $packet['summary']['hasCaption'] ?? null);
                    $t->same($position === 'before-table' ? 'top' : 'bottom', $packet['summary']['captionSide'] ?? null);
                };
        }
    }
}

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        $number = $caseNumber++;
        $tests["maps upstream markdown reader table caption wordpress handoff {$tableName} {$position}"] =
            static function (TestRunner $t) use ($captionedMarkdown, $firstTable, $fixture, $position, $marker, $tableName, $number): void {
                $caption = "[Short {$number}] Handoff **caption** {$number} {#handoff-{$tableName}-{$position} .handoff data-source=\"surge\"}";
                $document = (new MarkdownReader())->read($captionedMarkdown($fixture['markdown'], $position, $marker, $caption));
                $table = $firstTable($document);
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same("Short {$number}", $table->attr('shortCaption'));
                $t->contains('data-pandoc-short-caption="Short ' . $number . '"', $blocks);
                $t->contains('Handoff <strong>caption</strong> ' . $number, $blocks);
                $t->contains('data-source="surge"', $blocks);
            };
    }
}

$supplementalPlacements = [
    ['label' => 'pipe before table marker', 'fixture' => $tableFixtures['pipe'], 'position' => 'before-table', 'marker' => 'Table:'],
    ['label' => 'pipe after colon marker', 'fixture' => $tableFixtures['pipe'], 'position' => 'after-table', 'marker' => ':'],
    ['label' => 'simple before caption marker', 'fixture' => $tableFixtures['simple'], 'position' => 'before-table', 'marker' => 'Caption:'],
    ['label' => 'grid after colon marker', 'fixture' => $tableFixtures['grid'], 'position' => 'after-table', 'marker' => ':'],
    ['label' => 'grid before table marker', 'fixture' => $tableFixtures['grid'], 'position' => 'before-table', 'marker' => 'Table:'],
];

$supplementalCaptionCases = [
    [
        'label' => 'role aria title attributes',
        'line' => 'Access review {#tbl-{id} .a11y role="presentation" aria-label="Review table {n}" title="Caption {n}"}',
        'caption' => 'Access review',
        'classes' => ['a11y'],
        'attributes' => ['role' => 'presentation', 'aria-label' => 'Review table {n}', 'title' => 'Caption {n}'],
    ],
    [
        'label' => 'locale direction attributes',
        'line' => 'Locale review {#tbl-{id} .i18n lang=fr dir=rtl data-owner=editorial}',
        'caption' => 'Locale review',
        'classes' => ['i18n'],
        'attributes' => ['lang' => 'fr', 'dir' => 'rtl', 'data-owner' => 'editorial'],
    ],
    [
        'label' => 'single quoted title attribute',
        'line' => 'Quoted title caption {#tbl-{id} .quote title=\'Batch "{n}" table\'}',
        'caption' => 'Quoted title caption',
        'classes' => ['quote'],
        'attributes' => ['title' => 'Batch "{n}" table'],
    ],
    [
        'label' => 'html entity attribute decode',
        'line' => 'Entity caption {#tbl-{id} .entity data-label="Review &amp; measure {n}"}',
        'caption' => 'Entity caption',
        'classes' => ['entity'],
        'attributes' => ['data-label' => 'Review & measure {n}'],
    ],
    [
        'label' => 'multiple class data attributes',
        'line' => 'Multi class caption {#tbl-{id} .review .wide .sortable data-source=batch-{n} data-state=ready}',
        'caption' => 'Multi class caption',
        'classes' => ['review', 'wide', 'sortable'],
        'attributes' => ['data-source' => 'batch-{n}', 'data-state' => 'ready'],
    ],
    [
        'label' => 'formatted emph short caption',
        'line' => '[Review *short* {n}] Long caption {n} {#tbl-{id} .short data-kind=emph}',
        'caption' => 'Long caption {n}',
        'short' => 'Review short {n}',
        'shortTypes' => ['text', 'emph', 'text'],
        'classes' => ['short'],
        'attributes' => ['data-kind' => 'emph'],
    ],
    [
        'label' => 'formatted strong short caption',
        'line' => '[**Review** short {n}] Long caption {n} {#tbl-{id} .short data-kind=strong}',
        'caption' => 'Long caption {n}',
        'short' => 'Review short {n}',
        'shortTypes' => ['strong', 'text'],
        'classes' => ['short'],
        'attributes' => ['data-kind' => 'strong'],
    ],
    [
        'label' => 'long caption link code inlines',
        'line' => 'Review [docs](/docs/{n}) and `code {n}` {#tbl-{id} .inline data-kind=link-code}',
        'caption' => 'Review [docs](/docs/{n}) and `code {n}`',
        'captionTypes' => ['text', 'link', 'text', 'code'],
        'classes' => ['inline'],
        'attributes' => ['data-kind' => 'link-code'],
    ],
    [
        'label' => 'link starting caption not short',
        'line' => '[Docs](/docs/{n}) caption {#tbl-{id} .link-start data-kind=not-short}',
        'caption' => '[Docs](/docs/{n}) caption',
        'captionTypes' => ['link', 'text'],
        'classes' => ['link-start'],
        'attributes' => ['data-kind' => 'not-short'],
        'short' => null,
    ],
    [
        'label' => 'multiline continuation with attributes',
        'line' => "First caption {n}\n  second line `code {n}` {#tbl-{id} .continued data-source=multi-{n}}",
        'caption' => "First caption {n}\nsecond line `code {n}`",
        'captionTypes' => ['text', 'softbreak', 'text', 'code'],
        'classes' => ['continued'],
        'attributes' => ['data-source' => 'multi-{n}'],
    ],
];

foreach ($supplementalPlacements as $placementIndex => $placement) {
    foreach ($supplementalCaptionCases as $captionIndex => $case) {
        $number = str_pad((string) $caseNumber++, 2, '0', STR_PAD_LEFT);
        $replacements = [
            '{id}' => "{$placementIndex}-{$captionIndex}",
            '{n}' => $number,
        ];
        $line = strtr($case['line'], $replacements);
        $expectedCaption = strtr($case['caption'], $replacements);
        $expectedShort = array_key_exists('short', $case) && $case['short'] !== null ? strtr($case['short'], $replacements) : null;
        $expectedAttributes = [];
        foreach ($case['attributes'] ?? [] as $name => $value) {
            $expectedAttributes[$name] = strtr($value, $replacements);
        }
        $id = 'tbl-' . $replacements['{id}'];
        $tests["maps upstream markdown reader table caption supplemental surge {$number} {$placement['label']} {$case['label']}"] =
            static function (TestRunner $t) use (
                $captionedMarkdown,
                $firstTable,
                $inlineTypes,
                $assertTableShape,
                $placement,
                $line,
                $expectedCaption,
                $expectedShort,
                $expectedAttributes,
                $id,
                $case
            ): void {
                $document = (new MarkdownReader())->read($captionedMarkdown(
                    $placement['fixture']['markdown'],
                    $placement['position'],
                    $placement['marker'],
                    $line
                ));
                $table = $firstTable($document);
                $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);

                $assertTableShape($t, $table, $placement['fixture']);
                $t->same($expectedCaption, $table->attr('caption'));
                $t->same($id, $table->attr('id'));
                $t->same($case['classes'], $table->attr('classes'));
                $t->same($expectedAttributes, $table->attr('attributes'));
                $t->same('markdown-table-caption', $table->attr('captionSource')['element'] ?? null);
                $t->same($placement['position'], $table->attr('captionSource')['position'] ?? null);
                $t->same($placement['marker'], $table->attr('captionSource')['marker'] ?? null);
                $t->same($placement['position'] === 'before-table' ? 'top' : 'bottom', $table->attr('captionSource')['captionSide'] ?? null);
                $t->same($placement['position'] === 'before-table' ? 'before-table' : 'after-table', $packet['summary']['captionPlacement'] ?? null);

                foreach ($case['classes'] as $class) {
                }

                if ($expectedShort !== null) {
                    $t->same($expectedShort, $table->attr('shortCaption'));
                    $t->same($case['shortTypes'], $inlineTypes($table->attr('shortCaptionInlines')));
                    $t->same($expectedShort, $packet['captions']['short']['text'] ?? null);
                    $blocks = (new WordPressBlockWriter())->write($document);
                    $t->contains('data-pandoc-short-caption="' . htmlspecialchars($expectedShort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"', $blocks);
                } elseif (array_key_exists('short', $case)) {
                    $t->same(null, $table->attr('shortCaption', null));
                }

                if (isset($case['captionTypes'])) {
                    $t->same($case['captionTypes'], $inlineTypes($table->attr('captionInlines')));
                }
            };
    }
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\TableGeometry;
use PortLibs\Pandoc\WordPressBlockWriter;

$tableFixtures = [
    'pipe' => [
        'markdown' => implode("\n", [
            '| Metric | Value |',
            '|:-------|------:|',
            '| Posts  | 42    |',
        ]),
        'body' => '42',
    ],
    'simple' => [
        'markdown' => implode("\n", [
            'Metric   Value',
            '-------  -----',
            'Posts    42',
        ]),
        'body' => '42',
    ],
    'grid' => [
        'markdown' => implode("\n", [
            '+--------+-------+',
            '| Metric | Value |',
            '+========+=======+',
            '| Posts  | 42    |',
            '+--------+-------+',
        ]),
        'body' => '42',
    ],
];

$imageFixtures = [
    'inline' => [
        'markdown' => '![Inline source](media/inline-source.png "Inline title")',
        'tail' => '',
        'url' => 'media/inline-source.png',
    ],
    'reference' => [
        'markdown' => '![Reference source][caption-source-ref]',
        'tail' => "\n\n[caption-source-ref]: media/reference-source.png \"Reference title\"",
        'url' => 'media/reference-source.png',
    ],
    'shortcut' => [
        'markdown' => '![Shortcut source][]',
        'tail' => "\n\n[Shortcut source]: media/shortcut-source.png \"Shortcut title\"",
        'url' => 'media/shortcut-source.png',
    ],
];

$captionAttributeCases = [
    'id class data source' => static function (string $caseId): array {
        return [
            'source' => '{#caption-source-' . $caseId . ' .caption-source .case-' . $caseId . ' data-source="queue-' . $caseId . '"}',
            'id' => 'caption-source-' . $caseId,
            'classes' => ['caption-source', 'case-' . $caseId],
            'attributes' => ['data-source' => 'queue-' . $caseId],
        ];
    },
    'language direction title' => static function (string $caseId): array {
        return [
            'source' => '{.localized-caption lang="en-US" dir="ltr" title="Review caption ' . $caseId . '"}',
            'id' => null,
            'classes' => ['localized-caption'],
            'attributes' => ['lang' => 'en-US', 'dir' => 'ltr', 'title' => 'Review caption ' . $caseId],
        ];
    },
    'aria role metadata' => static function (string $caseId): array {
        return [
            'source' => '{#caption-role-' . $caseId . ' role="note" aria-label="Caption review ' . $caseId . '" data-review="yes"}',
            'id' => 'caption-role-' . $caseId,
            'classes' => [],
            'attributes' => ['role' => 'note', 'aria-label' => 'Caption review ' . $caseId, 'data-review' => 'yes'],
        ];
    },
    'xml language owner' => static function (string $caseId): array {
        return [
            'source' => '{.xml-caption xml:lang="en-GB" data-owner="editor-' . $caseId . '"}',
            'id' => null,
            'classes' => ['xml-caption'],
            'attributes' => ['xml:lang' => 'en-GB', 'data-owner' => 'editor-' . $caseId],
        ];
    },
    'phase index metadata' => static function (string $caseId): array {
        return [
            'source' => '{#caption-phase-' . $caseId . ' .phase-caption data-phase="draft" data-index="' . $caseId . '"}',
            'id' => 'caption-phase-' . $caseId,
            'classes' => ['phase-caption'],
            'attributes' => ['data-phase' => 'draft', 'data-index' => $caseId],
        ];
    },
];

$captionedTableMarkdown = static function (string $table, string $position, string $captionLine): string {
    return $position === 'before-table'
        ? $captionLine . "\n\n" . $table
        : $table . "\n\n" . $captionLine;
};

$captionedFigureMarkdown = static function (array $fixture, string $position, string $captionLine): string {
    $body = $position === 'before-figure'
        ? $captionLine . "\n\n" . $fixture['markdown']
        : $fixture['markdown'] . "\n\n" . $captionLine;

    return $body . $fixture['tail'];
};

$firstNodeOfType = static function (AstNode $document, string $type): AstNode {
    foreach ($document->children as $node) {
        if ($node->type === $type) {
            return $node;
        }
    }

    return new AstNode('missing');
};

$assertCaptionSourceAttributes = static function (TestRunner $t, array $source, array $case): void {
    $sourceAttributes = is_array($source['sourceAttributes'] ?? null) ? $source['sourceAttributes'] : [];

    if ($case['id'] !== null) {
        $t->same($case['id'], $sourceAttributes['id'] ?? null);
        $t->same($case['id'], $sourceAttributes['htmlAttributes']['id'] ?? null);
    }
    $t->same($case['classes'], $sourceAttributes['classes'] ?? []);
    if ($case['classes'] !== []) {
        $t->same(implode(' ', $case['classes']), $sourceAttributes['htmlAttributes']['class'] ?? null);
    }
    foreach ($case['attributes'] as $name => $value) {
        $t->same($value, $sourceAttributes['attributes'][$name] ?? null);
        $t->same($value, $sourceAttributes['htmlAttributes'][$name] ?? null);
    }
};

$assertNodeAttributes = static function (TestRunner $t, AstNode $node, array $case): void {
    if ($case['id'] !== null) {
        $t->same($case['id'], $node->attr('id'));
    }
    $t->same($case['classes'], $node->attr('classes', []));
    foreach ($case['attributes'] as $name => $value) {
        $t->same($value, $node->attr('attributes', [])[$name] ?? null);
        $t->same($value, $node->attr('htmlAttributes', [])[$name] ?? null);
    }
};

$assertRenderedAttributes = static function (TestRunner $t, string $html, array $case): void {
    if ($case['id'] !== null) {
        $t->contains('id="' . $case['id'] . '"', $html);
    }
    foreach ($case['classes'] as $class) {
        $t->contains($class, $html);
    }
    foreach ($case['attributes'] as $name => $value) {
        if ($name === 'xml:lang') {
            continue;
        }
        $t->contains($name . '="' . $value . '"', $html);
    }
};

$tests = [];
$tableCases = [];
$tableCaseNumber = 1;
foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        foreach ($captionAttributeCases as $attributeName => $attributeBuilder) {
            $caseId = str_pad((string) $tableCaseNumber, 3, '0', STR_PAD_LEFT);
            $attribute = $attributeBuilder($caseId);
            $tableCases[] = [
                'name' => sprintf(
                    'maps upstream markdown reader table caption source attributes %s %s %s',
                    $tableName,
                    $position,
                    str_replace(' ', '-', $attributeName)
                ),
                'caseId' => $caseId,
                'tableName' => $tableName,
                'position' => $position,
                'marker' => $marker,
                'fixture' => $fixture,
                'caption' => 'Source **caption** ' . $caseId,
                ...$attribute,
            ];
            $tableCaseNumber++;
        }
    }
}

foreach ($tableCases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use (
        $case,
        $captionedTableMarkdown,
        $firstNodeOfType,
        $assertCaptionSourceAttributes,
        $assertNodeAttributes,
        $assertRenderedAttributes
    ): void {
        $captionLine = $case['marker'] . ' ' . $case['caption'] . ' ' . $case['source'];
        $document = (new MarkdownReader())->read($captionedTableMarkdown($case['fixture']['markdown'], $case['position'], $captionLine));
        $table = $firstNodeOfType($document, 'table');
        $captionSource = $table->attr('captionSource', []);
        $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$table]));

        $t->same('table', $table->type);
        $t->same($case['caption'], $table->attr('caption'));
        $t->same('markdown-table-caption', $captionSource['element'] ?? null);
        $t->same($case['position'], $captionSource['position'] ?? null);
        $t->same($case['marker'], $captionSource['marker'] ?? null);
        $assertCaptionSourceAttributes($t, $captionSource, $case);
        $assertNodeAttributes($t, $table, $case);

        $sourceSummary = $packet['captions']['long']['sourceAttributes'] ?? [];
        if ($case['id'] !== null) {
            $t->same($case['id'], $sourceSummary['id'] ?? null);
        }
        $t->same($case['classes'], $sourceSummary['classes'] ?? []);
        foreach ($case['attributes'] as $name => $value) {
            $t->same($value, $sourceSummary['attributes'][$name] ?? null);
        }
        $t->contains('<figcaption', $blocks);
        $assertRenderedAttributes($t, $blocks, $case);
    };
}

$figureCases = [];
$figureCaseNumber = 1;
foreach ($imageFixtures as $imageName => $fixture) {
    foreach (['before-figure' => 'Figure:', 'after-figure' => ':'] as $position => $marker) {
        foreach ($captionAttributeCases as $attributeName => $attributeBuilder) {
            $caseId = str_pad((string) $figureCaseNumber, 3, '0', STR_PAD_LEFT);
            $attribute = $attributeBuilder('fig-' . $caseId);
            $figureCases[] = [
                'name' => sprintf(
                    'maps upstream markdown reader figure caption source attributes %s %s %s',
                    $imageName,
                    $position,
                    str_replace(' ', '-', $attributeName)
                ),
                'caseId' => $caseId,
                'imageName' => $imageName,
                'position' => $position,
                'marker' => $marker,
                'fixture' => $fixture,
                'caption' => 'Figure **caption** ' . $caseId,
                'expectedCaption' => 'Figure caption ' . $caseId,
                ...$attribute,
            ];
            $figureCaseNumber++;
        }
    }
}

foreach ($figureCases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use (
        $case,
        $captionedFigureMarkdown,
        $firstNodeOfType,
        $assertCaptionSourceAttributes,
        $assertNodeAttributes,
        $assertRenderedAttributes
    ): void {
        $captionLine = $case['marker'] . ' ' . $case['caption'] . ' ' . $case['source'];
        $document = (new MarkdownReader())->read($captionedFigureMarkdown($case['fixture'], $case['position'], $captionLine));
        $figure = $firstNodeOfType($document, 'figure');
        $image = $figure->children[0] ?? new AstNode('missing');
        $captionSource = $figure->attr('captionSource', []);
        $blocks = (new WordPressBlockWriter())->write(new AstNode('document', [], [$figure]));

        $t->same('figure', $figure->type);
        $t->same('image', $image->type);
        $t->same($case['fixture']['url'], $image->attr('url'));
        $t->same($case['expectedCaption'], $figure->attr('caption'));
        $t->same('markdown-figure-caption', $captionSource['element'] ?? null);
        $t->same($case['position'], $captionSource['position'] ?? null);
        $t->same($case['marker'], $captionSource['marker'] ?? null);
        $assertCaptionSourceAttributes($t, $captionSource, $case);
        $assertNodeAttributes($t, $figure, $case);
        $t->contains('<figcaption>Figure <strong>caption</strong> ' . $case['caseId'] . '</figcaption>', $blocks);
        $assertRenderedAttributes($t, $blocks, $case);
    };
}

$tests['records markdown reader caption source completion mapped-case count'] =
    static function (TestRunner $t) use ($tableCases, $figureCases): void {
        $t->same(60, count($tableCases) + count($figureCases));
    };

return $tests;

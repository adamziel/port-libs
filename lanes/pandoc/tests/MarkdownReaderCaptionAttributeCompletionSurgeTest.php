<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$inlineTypes = static fn (array $nodes): array => array_values(array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
));

$attributeVariants = [
    'closing brace value' => static function (string $caseId): array {
        return [
            'source' => 'data-review="brace } value ' . $caseId . '"',
            'markdown' => 'data-review="brace } value ' . $caseId . '"',
            'attributes' => ['data-review' => 'brace } value ' . $caseId],
            'handoff' => 'data-review="brace } value ' . $caseId . '"',
        ];
    },
    'escaped quote value' => static function (string $caseId): array {
        return [
            'source' => 'title="Caption \"quote\" ' . $caseId . '" data-review="quote } value ' . $caseId . '"',
            'markdown' => 'title="Caption \"quote\" ' . $caseId . '" data-review="quote } value ' . $caseId . '"',
            'attributes' => [
                'title' => 'Caption "quote" ' . $caseId,
                'data-review' => 'quote } value ' . $caseId,
            ],
            'handoff' => 'title="Caption &quot;quote&quot; ' . $caseId . '"',
        ];
    },
    'entity decoded value' => static function (string $caseId): array {
        return [
            'source' => 'data-review="A &amp; B } ' . $caseId . '"',
            'markdown' => 'data-review="A & B } ' . $caseId . '"',
            'attributes' => ['data-review' => 'A & B } ' . $caseId],
            'handoff' => 'data-review="A &amp; B } ' . $caseId . '"',
        ];
    },
    'namespaced language value' => static function (string $caseId): array {
        return [
            'source' => 'xml:lang="fr-CA" data-review="locale } ' . $caseId . '"',
            'markdown' => 'xml:lang="fr-CA" data-review="locale } ' . $caseId . '"',
            'attributes' => [
                'xml:lang' => 'fr-CA',
                'data-review' => 'locale } ' . $caseId,
            ],
            'handoff' => 'xml:lang="fr-CA"',
        ];
    },
    'aria label value' => static function (string $caseId): array {
        return [
            'source' => 'aria-label="Review } caption ' . $caseId . '" dir="ltr"',
            'markdown' => 'aria-label="Review } caption ' . $caseId . '" dir="ltr"',
            'attributes' => [
                'aria-label' => 'Review } caption ' . $caseId,
                'dir' => 'ltr',
            ],
            'handoff' => 'aria-label="Review } caption ' . $caseId . '"',
        ];
    },
];

$makeTable = static function (string $syntax, string $caseId): string {
    return match ($syntax) {
        'pipe' => implode("\n", [
            '| Term | Count |',
            '|:-----|------:|',
            '| A' . $caseId . ' | ' . (int) $caseId . ' |',
        ]),
        'grid' => implode("\n", [
            '+----------+-------+',
            '| Term     | Count |',
            '+==========+=======+',
            '| A' . str_pad($caseId, 7) . ' | ' . str_pad((string) (int) $caseId, 5) . ' |',
            '+----------+-------+',
        ]),
        'simple' => implode("\n", [
            sprintf('%-12s  %5s', 'Term', 'Count'),
            '------------  -----',
            sprintf('%-12s  %5s', 'A' . $caseId, (string) (int) $caseId),
        ]),
    };
};

$makeCaption = static function (string $marker, string $shortCaption, string $caption, string $id, array $classes, string $attributeSource): string {
    return $marker . ' [' . $shortCaption . '] ' . $caption
        . ' {#' . $id . ' .' . implode(' .', $classes)
        . ' ' . $attributeSource . '}';
};

$makeCaptionedTableMarkdown = static function (array $case) use ($makeCaption, $makeTable): string {
    $caption = $makeCaption(
        $case['marker'],
        $case['shortCaption'],
        'Review **table** ' . $case['caseId'],
        $case['id'],
        $case['classes'],
        $case['attributeSource']
    );
    $table = $makeTable($case['syntax'], $case['caseId']);

    return $case['position'] === 'before-table'
        ? $caption . "\n\n" . $table
        : $table . "\n\n" . $caption;
};

$tableCases = [];
$tableCaseNumber = 1;
foreach (['pipe', 'grid', 'simple'] as $syntax) {
    foreach (['before-table' => 'Table:', 'after-table' => ':'] as $position => $marker) {
        foreach ($attributeVariants as $variantName => $variantBuilder) {
            $caseId = str_pad((string) $tableCaseNumber, 3, '0', STR_PAD_LEFT);
            $variant = $variantBuilder($caseId);
            $tableCases[] = [
                'caseId' => $caseId,
                'name' => sprintf(
                    'maps upstream markdown table caption attribute completion %s %s %s %s',
                    $caseId,
                    $syntax,
                    $position,
                    $variantName
                ),
                'syntax' => $syntax,
                'position' => $position,
                'marker' => $marker,
                'id' => 'caption-attr-table-' . $caseId,
                'classes' => ['caption-attr', 'table-' . $syntax, 'case-' . $caseId],
                'shortCaption' => 'Short ' . $caseId,
                'attributeSource' => $variant['source'],
                'markdownAttributeSource' => $variant['markdown'],
                'attributes' => $variant['attributes'],
                'handoff' => $variant['handoff'],
            ];
            $tableCaseNumber++;
        }
    }
}

$tests = [];

foreach ($tableCases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $makeCaptionedTableMarkdown, $inlineTypes): void {
        $document = (new MarkdownReader())->read($makeCaptionedTableMarkdown($case));
        $table = $document->children[0] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children), $case['caseId'] . ' parses as a single table block');
        $t->same('table', $table->type, $case['caseId'] . ' table node');
        $t->same('Review **table** ' . $case['caseId'], $table->attr('caption'), $case['caseId'] . ' caption text excludes trailing attrs');
        $t->same($case['shortCaption'], $table->attr('shortCaption'), $case['caseId'] . ' short caption');
        $t->same($case['id'], $table->attr('id'), $case['caseId'] . ' id');
        $t->same($case['classes'], $table->attr('classes'), $case['caseId'] . ' classes');
        $t->same($case['attributes'], $table->attr('attributes'), $case['caseId'] . ' attributes');
        $t->same($case['position'], $table->attr('captionSource')['position'] ?? null, $case['caseId'] . ' caption source position');
        $t->same(['text', 'strong', 'text'], $inlineTypes($table->attr('captionInlines', [])), $case['caseId'] . ' caption inlines');
        $t->same('Term', $table->children[0]->children[0]->children[0]->attr('text'), $case['caseId'] . ' header cell');
        $t->same('A' . $case['caseId'], $table->children[1]->children[0]->children[0]->attr('text'), $case['caseId'] . ' body cell');
        $t->contains('id="' . $case['id'] . '"', $blocks);
        $t->contains('class="caption-attr table-' . $case['syntax'] . ' case-' . $case['caseId'] . '"', $blocks);
        $t->contains($case['handoff'], $blocks);
    };
}

$makeFigureImage = static function (array $case): string {
    return match ($case['syntax']) {
        'inline' => '![' . $case['sourceLabel'] . '](' . $case['url'] . ' "' . $case['title'] . '")',
        'reference' => '![' . $case['sourceLabel'] . '][fig-attr-' . $case['caseId'] . ']',
        'shortcut' => '![' . $case['sourceLabel'] . '][]',
    };
};

$makeFigureReferenceTail = static function (array $case): string {
    return match ($case['syntax']) {
        'reference' => "\n\n" . '[fig-attr-' . $case['caseId'] . ']: ' . $case['url'] . ' "' . $case['title'] . '"',
        'shortcut' => "\n\n" . '[' . $case['sourceLabel'] . ']: ' . $case['url'] . ' "' . $case['title'] . '"',
        default => '',
    };
};

$makeCaptionedFigureMarkdown = static function (array $case) use ($makeCaption, $makeFigureImage, $makeFigureReferenceTail): string {
    $caption = $makeCaption(
        $case['marker'],
        $case['shortCaption'],
        'Review **figure** ' . $case['caseId'],
        $case['id'],
        $case['classes'],
        $case['attributeSource']
    );
    $image = $makeFigureImage($case);
    $referenceTail = $makeFigureReferenceTail($case);

    return $case['position'] === 'before-figure'
        ? $caption . "\n\n" . $image . $referenceTail
        : $image . "\n\n" . $caption . $referenceTail;
};

$figureCases = [];
$figureCaseNumber = 1;
foreach (['inline', 'reference', 'shortcut'] as $syntax) {
    foreach (['before-figure' => 'Figure:', 'after-figure' => ':'] as $position => $marker) {
        foreach ($attributeVariants as $variantName => $variantBuilder) {
            $caseId = str_pad((string) $figureCaseNumber, 3, '0', STR_PAD_LEFT);
            $variant = $variantBuilder($caseId);
            $figureCases[] = [
                'caseId' => $caseId,
                'name' => sprintf(
                    'maps upstream markdown figure caption attribute completion %s %s %s %s',
                    $caseId,
                    $syntax,
                    $position,
                    $variantName
                ),
                'syntax' => $syntax,
                'position' => $position,
                'marker' => $marker,
                'id' => 'caption-attr-figure-' . $caseId,
                'classes' => ['caption-attr', 'figure-' . $syntax, 'case-' . $caseId],
                'shortCaption' => 'Short ' . $caseId,
                'sourceLabel' => 'Figure source ' . $caseId,
                'url' => 'media/caption-attr-' . $caseId . '.png',
                'title' => 'Figure title ' . $caseId,
                'attributeSource' => $variant['source'],
                'markdownAttributeSource' => $variant['markdown'],
                'attributes' => $variant['attributes'],
                'handoff' => $variant['handoff'],
            ];
            $figureCaseNumber++;
        }
    }
}

foreach ($figureCases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $makeCaptionedFigureMarkdown, $inlineTypes): void {
        $document = (new MarkdownReader())->read($makeCaptionedFigureMarkdown($case));
        $figure = $document->children[0] ?? new AstNode('missing');
        $image = $figure->children[0] ?? new AstNode('missing');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children), $case['caseId'] . ' parses as a single figure block');
        $t->same('figure', $figure->type, $case['caseId'] . ' figure node');
        $t->same('Review figure ' . $case['caseId'], $figure->attr('caption'), $case['caseId'] . ' plain caption excludes trailing attrs');
        $t->same($case['shortCaption'], $figure->attr('shortCaption'), $case['caseId'] . ' short caption');
        $t->same($case['id'], $figure->attr('id'), $case['caseId'] . ' id');
        $t->same($case['classes'], $figure->attr('classes'), $case['caseId'] . ' classes');
        $t->same($case['attributes'], $figure->attr('attributes'), $case['caseId'] . ' attributes');
        $t->same($case['position'], $figure->attr('captionSource')['position'] ?? null, $case['caseId'] . ' caption source position');
        $t->same(['text', 'strong', 'text'], $inlineTypes($figure->attr('captionInlines', [])), $case['caseId'] . ' caption inlines');
        $t->same('image', $image->type, $case['caseId'] . ' child image');
        $t->same($case['url'], $image->attr('url'), $case['caseId'] . ' image url');
        $t->same($case['title'], $image->attr('title'), $case['caseId'] . ' image title');
        $t->same($case['sourceLabel'], $image->attr('alt'), $case['caseId'] . ' image alt');
        $t->contains('id="' . $case['id'] . '"', $blocks);
        $t->contains('class="wp-block-image caption-attr figure-' . $case['syntax'] . ' case-' . $case['caseId'] . '"', $blocks);
        $t->contains($case['handoff'], $blocks);
    };
}

$tests['records upstream markdown reader caption attribute completion mapped-case count'] =
    static function (TestRunner $t) use ($tableCases, $figureCases): void {
        $t->same(60, count($tableCases) + count($figureCases));
    };

return $tests;

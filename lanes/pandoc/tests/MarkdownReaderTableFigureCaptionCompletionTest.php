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

$tableMarkers = [
    'numbered colon' => 'Table 1:',
    'alpha period' => 'Table A.',
    'abbrev numbered colon' => 'Tbl. 2:',
    'tab roman period' => 'Tab. IV.',
    'caption numbered colon' => 'Caption 3:',
];

$figureMarkers = [
    'numbered colon' => 'Figure 1:',
    'fig numbered colon' => 'Fig. 2:',
    'figs roman period' => 'Figs. IV.',
    'image alpha colon' => 'Image A:',
    'img numbered period' => 'Img. 3.',
];

$captionedTableMarkdown = static function (string $table, string $position, string $marker, string $caption): string {
    $captionLine = $marker . ($caption === '' ? '' : ' ' . $caption);

    return $position === 'before-table'
        ? $captionLine . "\n\n" . $table
        : $table . "\n\n" . $captionLine;
};

$firstNodeOfType = static function (AstNode $document, string $type): AstNode {
    foreach ($document->children as $node) {
        if ($node->type === $type) {
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

$figureImageMarkdown = static function (array $case): string {
    return match ($case['syntax']) {
        'inline' => '![' . $case['label'] . '](' . $case['url'] . ' "' . $case['title'] . '")',
        'reference' => '![' . $case['label'] . '][fig-completion-' . $case['caseId'] . ']',
        'shortcut' => '![' . $case['label'] . '][]',
    };
};

$figureReferenceMarkdown = static function (array $case): string {
    return match ($case['syntax']) {
        'reference' => "\n\n" . '[fig-completion-' . $case['caseId'] . ']: ' . $case['url'] . ' "' . $case['title'] . '"',
        'shortcut' => "\n\n" . '[' . $case['label'] . ']: ' . $case['url'] . ' "' . $case['title'] . '"',
        default => '',
    };
};

$captionedFigureMarkdown = static function (array $case) use ($figureImageMarkdown, $figureReferenceMarkdown): string {
    $caption = $case['marker'] . ' [' . $case['shortCaption'] . '] ' . $case['caption']
        . ' {#' . $case['id'] . ' .caption-completion .' . $case['caseClass']
        . ' data-source="' . $case['dataSource'] . '"}';
    $image = $figureImageMarkdown($case);
    $reference = $figureReferenceMarkdown($case);

    return $case['position'] === 'before-figure'
        ? $caption . "\n\n" . $image . $reference
        : $image . "\n\n" . $caption . $reference;
};

$tests = [];
$mappedCases = 0;
$caseNumber = 1;

foreach ($tableFixtures as $tableName => $fixture) {
    foreach (['before-table', 'after-table'] as $position) {
        foreach ($tableMarkers as $markerName => $marker) {
            $caseId = str_pad((string) $caseNumber++, 3, '0', STR_PAD_LEFT);
            $id = 'tbl-caption-completion-' . $caseId;
            $short = 'Queue ' . $caseId;
            $caption = 'Reader *table* caption ' . $caseId;
            $dataSource = 'table-completion-' . $caseId;
            $mappedCases++;

            $tests["maps upstream markdown reader keyed table caption {$tableName} {$position} {$markerName}"] =
                static function (TestRunner $t) use (
                    $captionedTableMarkdown,
                    $firstNodeOfType,
                    $inlineTypes,
                    $assertTableShape,
                    $fixture,
                    $tableName,
                    $position,
                    $marker,
                    $id,
                    $short,
                    $caption,
                    $dataSource
                ): void {
                    $sourceCaption = "[{$short}] {$caption} {#{$id} .caption-completion .{$tableName} data-source=\"{$dataSource}\"}";
                    $document = (new MarkdownReader())->read($captionedTableMarkdown($fixture['markdown'], $position, $marker, $sourceCaption));
                    $table = $firstNodeOfType($document, 'table');
                    $packet = TableGeometry::reviewPacket($table, ['accessibility' => false]);
                    $blocks = (new WordPressBlockWriter())->write($document);
                    $source = $table->attr('captionSource', []);

                    $assertTableShape($t, $table, $fixture);
                    $t->same($caption, $table->attr('caption'));
                    $t->same($short, $table->attr('shortCaption'));
                    $t->same(['text', 'emph', 'text'], $inlineTypes($table->attr('captionInlines', [])));
                    $t->same(['text'], $inlineTypes($table->attr('shortCaptionInlines', [])));
                    $t->same($id, $table->attr('id'));
                    $t->same(['caption-completion', $tableName], $table->attr('classes'));
                    $t->same(['data-source' => $dataSource], $table->attr('attributes'));
                    $t->same('markdown-table-caption', $source['element'] ?? null);
                    $t->same($position, $source['position'] ?? null);
                    $t->same($marker, $source['marker'] ?? null);
                    $t->same($position === 'before-table' ? 'top' : 'bottom', $packet['summary']['captionSide'] ?? null);
                    $t->same($position === 'before-table' ? 'before-table' : 'after-table', $packet['summary']['captionPlacement'] ?? null);
                    $t->contains('data-source="' . $dataSource . '"', $blocks);
                    $t->contains('Reader <em>table</em> caption', $blocks);
                };
        }
    }
}

foreach (['inline', 'reference', 'shortcut'] as $syntax) {
    foreach (['before-figure', 'after-figure'] as $position) {
        foreach ($figureMarkers as $markerName => $marker) {
            $caseId = str_pad((string) $caseNumber++, 3, '0', STR_PAD_LEFT);
            $case = [
                'caseId' => $caseId,
                'syntax' => $syntax,
                'position' => $position,
                'marker' => $marker,
                'label' => ucfirst($syntax) . ' source ' . $caseId,
                'caption' => 'Reader *figure* caption ' . $caseId,
                'plainCaption' => 'Reader figure caption ' . $caseId,
                'shortCaption' => 'Figure queue ' . $caseId,
                'id' => 'fig-caption-completion-' . $caseId,
                'caseClass' => 'case-' . $caseId,
                'dataSource' => 'figure-completion-' . $caseId,
                'url' => 'media/figure-completion-' . $caseId . '.png',
                'title' => 'Figure completion title ' . $caseId,
            ];
            $mappedCases++;

            $tests["maps upstream markdown reader keyed figure caption {$syntax} {$position} {$markerName}"] =
                static function (TestRunner $t) use ($captionedFigureMarkdown, $inlineTypes, $case): void {
                    $document = (new MarkdownReader())->read($captionedFigureMarkdown($case));
                    $figure = $document->children[0] ?? new AstNode('missing');
                    $image = $figure->children[0] ?? new AstNode('missing');
                    $blocks = (new WordPressBlockWriter())->write($document);

                    $t->same(1, count($document->children));
                    $t->same('figure', $figure->type);
                    $t->same('image', $image->type);
                    $t->same($case['plainCaption'], $figure->attr('caption'));
                    $t->same($case['shortCaption'], $figure->attr('shortCaption'));
                    $t->same(true, $figure->attr('renderCaptionInlines'));
                    $t->same(true, $figure->attr('renderShortCaptionAttribute'));
                    $t->same(['text', 'emph', 'text'], $inlineTypes($figure->attr('captionInlines', [])));
                    $t->same(['text'], $inlineTypes($figure->attr('shortCaptionInlines', [])));
                    $t->same($case['id'], $figure->attr('id'));
                    $t->same(['caption-completion', $case['caseClass']], $figure->attr('classes'));
                    $t->same(['data-source' => $case['dataSource']], $figure->attr('attributes'));
                    $t->same($case['url'], $image->attr('url'));
                    $t->same($case['title'], $image->attr('title'));
                    $t->same($case['label'], $image->attr('caption'));
                    $t->contains('data-source="' . $case['dataSource'] . '"', $blocks);
                    $t->contains('<figcaption>Reader <em>figure</em> caption ' . $case['caseId'] . '</figcaption>', $blocks);
                };
        }
    }
}

$tests['records upstream markdown reader table figure caption completion mapped-case count'] =
    static function (TestRunner $t) use ($mappedCases): void {
        $t->same(60, $mappedCases);
    };

return $tests;

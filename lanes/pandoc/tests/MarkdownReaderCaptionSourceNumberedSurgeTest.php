<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$firstNodeOfType = static function (AstNode $document, string $type): AstNode {
    foreach ($document->children as $node) {
        if ($node->type === $type) {
            return $node;
        }
    }

    return new AstNode('missing');
};

$tableMarkdown = static function (string $syntax, int $number): string {
    $rowLabel = 'Item ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);

    return match ($syntax) {
        'grid' => implode("\n", [
            '+----------+--------+',
            '| Item     | Count  |',
            '+==========+========+',
            '| ' . str_pad($rowLabel, 8) . ' | ' . str_pad((string) $number, 6) . ' |',
            '+----------+--------+',
        ]),
        'simple' => implode("\n", [
            'Item        Count',
            '----------  -----',
            str_pad($rowLabel, 10) . '  ' . str_pad((string) $number, 5, ' ', STR_PAD_LEFT),
        ]),
        default => implode("\n", [
            '| Item | Count |',
            '|:-----|------:|',
            '| ' . $rowLabel . ' | ' . $number . ' |',
        ]),
    };
};

$captionedTableMarkdown = static function (array $case) use ($tableMarkdown): string {
    $caption = $case['marker'] . ' [' . $case['short'] . '] ' . $case['caption']
        . ' {#' . $case['id'] . ' .numbered-caption .' . $case['class']
        . ' data-source="' . $case['dataSource'] . '"}';
    $table = $tableMarkdown($case['syntax'], $case['number']);

    return $case['position'] === 'before-table'
        ? $caption . "\n\n" . $table
        : $table . "\n\n" . $caption;
};

$tableMarkers = ['Table 1:', 'Table 2a:', 'Tbl. 3:', 'Tab. 4:', 'Caption 5:'];
$tableCases = [];
$tableCaseNumber = 1;
foreach (['pipe', 'simple', 'grid'] as $syntax) {
    foreach (['before-table', 'after-table'] as $position) {
        foreach ($tableMarkers as $marker) {
            $caseId = str_pad((string) $tableCaseNumber, 3, '0', STR_PAD_LEFT);
            $tableCases[] = [
                'number' => $tableCaseNumber,
                'id' => 'numbered-table-caption-' . $caseId,
                'class' => 'case-' . $caseId,
                'syntax' => $syntax,
                'position' => $position,
                'marker' => $marker,
                'short' => 'Queue ' . $caseId,
                'caption' => 'Numbered *table* caption ' . $caseId,
                'dataSource' => 'table-' . $caseId,
                'rowLabel' => 'Item ' . str_pad((string) $tableCaseNumber, 2, '0', STR_PAD_LEFT),
            ];
            $tableCaseNumber++;
        }
    }
}

$figureImageMarkdown = static function (array $case): string {
    return match ($case['syntax']) {
        'reference' => '![' . $case['label'] . '][fig-source-' . $case['caseId'] . ']',
        'shortcut' => '![' . $case['label'] . '][]',
        default => '![' . $case['label'] . '](' . $case['url'] . ' "' . $case['title'] . '"){alt="' . $case['alt'] . '"}',
    };
};

$figureReferenceMarkdown = static function (array $case): string {
    return match ($case['syntax']) {
        'reference' => "\n\n" . '[fig-source-' . $case['caseId'] . ']: ' . $case['url'] . ' "' . $case['title'] . '"',
        'shortcut' => "\n\n" . '[' . $case['label'] . ']: ' . $case['url'] . ' "' . $case['title'] . '"',
        default => '',
    };
};

$captionedFigureMarkdown = static function (array $case) use ($figureImageMarkdown, $figureReferenceMarkdown): string {
    $caption = $case['marker'] . ' [' . $case['short'] . '] ' . $case['caption']
        . ' {#' . $case['id'] . ' .numbered-figure .' . $case['class']
        . ' data-source="' . $case['dataSource'] . '"}';
    $image = $figureImageMarkdown($case);
    $reference = $figureReferenceMarkdown($case);

    return $case['position'] === 'before-figure'
        ? $caption . "\n\n" . $image . $reference
        : $image . "\n\n" . $caption . $reference;
};

$figureMarkers = ['Figure 1:', 'Fig. 2:', 'Fig 3:', 'Image 4:', 'Caption 5:'];
$figureCases = [];
$figureCaseNumber = 1;
foreach (['inline', 'reference', 'shortcut'] as $syntax) {
    foreach (['before-figure', 'after-figure'] as $position) {
        foreach ($figureMarkers as $marker) {
            $caseId = str_pad((string) $figureCaseNumber, 3, '0', STR_PAD_LEFT);
            $figureCases[] = [
                'caseId' => $caseId,
                'id' => 'numbered-figure-caption-' . $caseId,
                'class' => 'case-' . $caseId,
                'syntax' => $syntax,
                'position' => $position,
                'marker' => $marker,
                'short' => 'Figure queue ' . $caseId,
                'caption' => 'Numbered *figure* caption ' . $caseId,
                'plainCaption' => 'Numbered figure caption ' . $caseId,
                'dataSource' => 'figure-' . $caseId,
                'label' => ucfirst($syntax) . ' source ' . $caseId,
                'alt' => 'Alt source ' . $caseId,
                'url' => 'media/numbered-figure-' . $caseId . '.png',
                'title' => 'Numbered figure ' . $caseId,
            ];
            $figureCaseNumber++;
        }
    }
}

$tests = [];

foreach ($tableCases as $case) {
    $tests['maps upstream markdown numbered table caption marker ' . $case['syntax'] . ' ' . $case['position'] . ' ' . $case['marker']] =
        static function (TestRunner $t) use ($case, $captionedTableMarkdown, $firstNodeOfType): void {
            $document = (new MarkdownReader())->read($captionedTableMarkdown($case));
            $table = $firstNodeOfType($document, 'table');
            $captionSource = $table->attr('captionSource', []);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('table', $table->type);
            $t->same($case['caption'], $table->attr('caption'));
            $t->same($case['short'], $table->attr('shortCaption'));
            $t->same($case['id'], $table->attr('id'));
            $t->same(['numbered-caption', $case['class']], $table->attr('classes'));
            $t->same(['data-source' => $case['dataSource']], $table->attr('attributes'));
            $t->same('markdown-table-caption', $captionSource['element'] ?? null);
            $t->same($case['position'], $captionSource['position'] ?? null);
            $t->same($case['marker'], $captionSource['marker'] ?? null);
            $t->same($case['position'] === 'before-table' ? 'top' : 'bottom', $captionSource['captionSide'] ?? null);
            $t->same($case['rowLabel'], $table->children[1]->children[0]->children[0]->attr('text'));
            $t->contains('data-pandoc-short-caption="' . $case['short'] . '"', $blocks);
        };
}

foreach ($figureCases as $case) {
    $tests['maps upstream markdown numbered figure caption marker ' . $case['syntax'] . ' ' . $case['position'] . ' ' . $case['marker']] =
        static function (TestRunner $t) use ($case, $captionedFigureMarkdown, $firstNodeOfType): void {
            $document = (new MarkdownReader())->read($captionedFigureMarkdown($case));
            $figure = $firstNodeOfType($document, 'figure');
            $image = $figure->children[0] ?? new AstNode('missing');
            $captionSource = $figure->attr('captionSource', []);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('figure', $figure->type);
            $t->same('image', $image->type);
            $t->same($case['plainCaption'], $figure->attr('caption'));
            $t->same($case['short'], $figure->attr('shortCaption'));
            $t->same($case['id'], $figure->attr('id'));
            $t->same(['numbered-figure', $case['class']], $figure->attr('classes'));
            $t->same(['data-source' => $case['dataSource']], $figure->attr('attributes'));
            $t->same('markdown-figure-caption', $captionSource['element'] ?? null);
            $t->same($case['position'], $captionSource['position'] ?? null);
            $t->same($case['marker'], $captionSource['marker'] ?? null);
            $t->same($case['position'] === 'before-figure' ? 'top' : 'bottom', $captionSource['captionSide'] ?? null);
            $t->same($case['url'], $image->attr('url'));
            $t->same($case['title'], $image->attr('title'));
            $t->contains('data-pandoc-short-caption="' . $case['short'] . '"', $blocks);
            $t->contains('<figcaption>Numbered <em>figure</em> caption ' . $case['caseId'] . '</figcaption>', $blocks);
        };
}

$tests['records markdown reader caption source numbered surge mapped-case count'] =
    static function (TestRunner $t) use ($tableCases, $figureCases): void {
        $t->same(60, count($tableCases) + count($figureCases));
    };

return $tests;

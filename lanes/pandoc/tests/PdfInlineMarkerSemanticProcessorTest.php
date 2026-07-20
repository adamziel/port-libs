<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfInlineMarkerSemanticProcessor;

$record = static function (
    string $text,
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    float $fontSize,
    int $order,
    int $page = 1,
    int $stream = 1
): array {
    return [
        'text' => $text,
        'layout' => [
            'page' => $page,
            'sourceStream' => $stream,
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'fontSize' => $fontSize,
            'sourceOrderStart' => $order,
            'sourceOrderEnd' => $order,
        ],
    ];
};

$sourceSha256 = str_repeat('a', 64);

$exactRecord = static function (
    string $text,
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    float $fontSize,
    int $order,
    int $sourceIndex
) use ($record): array {
    $result = $record($text, $x1, $y1, $x2, $y2, $fontSize, $order);
    $projection = preg_replace('/[\s\p{Cc}\p{Cf}]+/u', '', $text) ?? '';
    $range = [
        'sourceIndex' => $sourceIndex,
        'sourceStart' => 0,
        'sourceEnd' => strlen($projection),
    ];
    $result['layout'] = array_replace($result['layout'], [
        'id' => 'inline-source-' . $sourceIndex,
        'text' => $text,
        'sourceOrderStart' => $sourceIndex,
        'sourceOrderEnd' => $sourceIndex,
        'sourcePdfSourceIndex' => $sourceIndex,
        'sourcePdfSourceIndexEnd' => $sourceIndex,
        'sourcePdfSourceIndexes' => [$sourceIndex],
        'sourcePdfGlobalSourceIndex' => $sourceIndex,
        'sourcePdfExactSourceRanges' => [$range],
        'sourceGeometry' => [
            'page' => 1,
            'stream' => 1,
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'orientation' => 'horizontal',
        ],
        'sourceGeometryMethod' => 'exact-page-stream-character-offset',
        'sourcePdfExactGeometryFallback' => true,
        'sourceUnmatchedFallback' => true,
    ]);

    return $result;
};

return [
    'inline marker processor reattaches a detached numeric superscript by geometry' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = $processor->process([
            $record('A sentence ends here.', 80.0, 394.0, 142.0, 407.0, 10.0, 20),
            $record('1', 138.0, 399.0, 145.0, 408.0, 7.0, 21),
            $record('1 Introduction', 70.0, 372.0, 156.0, 387.0, 12.0, 22),
        ]);

        $t->same(['A sentence ends here.1', '1 Introduction'], array_column($records, 'text'));
        $t->same(1, $records[0]['layout']['sourcePdfInlineMarkerCount']);
        $t->same(true, $records[0]['layout']['sourcePdfProtectedSemanticContent']);
        $t->same(21, $records[0]['layout']['sourceOrderEnd']);
        $t->same(1, $processor->markerCount());
    },

    'inline marker processor supports typographic footnote symbols without lexical context' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = $processor->process([
            $record('Contributors', 80.0, 700.0, 145.0, 713.0, 10.0, 1),
            $record('†', 144.0, 705.0, 150.0, 714.0, 7.0, 2),
        ]);

        $t->same('Contributors†', $records[0]['text']);
        $t->same(1, $processor->markerCount());
    },

    'inline marker processor carries a validated exact union for consecutive source occurrences' => static function (TestRunner $t) use ($exactRecord, $sourceSha256): void {
        $processor = new PdfInlineMarkerSemanticProcessor($sourceSha256);
        $records = $processor->process([
            $exactRecord(',w', 80.0, 394.0, 99.0, 407.0, 10.0, 20, 30),
            $exactRecord('(i)', 97.0, 399.0, 108.0, 408.0, 7.0, 21, 31),
        ]);

        $t->same(',w(i)', $records[0]['text']);
        $t->same(',w(i)', $records[0]['layout']['text']);
        $t->same([30, 31], $records[0]['layout']['sourcePdfGlobalSourceIndexes']);
        $t->same(null, $records[0]['layout']['sourcePdfSourceIndexes'] ?? null);
        $t->same(['inline-source-30', 'inline-source-31'], $records[0]['layout']['sourcePdfSourceIds']);
        $t->same([
            ['sourceIndex' => 30, 'sourceStart' => 0, 'sourceEnd' => 2],
            ['sourceIndex' => 31, 'sourceStart' => 0, 'sourceEnd' => 3],
        ], $records[0]['layout']['sourcePdfExactSourceRanges']);
        $inlineProof = $records[0]['layout']['sourcePdfInlineMarkerExactSourceUnionProof'] ?? null;
        $t->same(null, $records[0]['layout']['sourcePdfWholeExactOccurrenceProof'] ?? null);
        $t->true(is_array($inlineProof));
        $t->same('exact-source-inline-marker-union', $inlineProof['method']);
        $t->same($sourceSha256, $inlineProof['sourceSha256']);
        $t->same(2, count($inlineProof['components']));
        $t->same(hash('sha256', ',w(i)'), $inlineProof['projectionDigest']);
        $t->same(null, $records[0]['layout']['sourcePdfExactPositionedText'] ?? null);
    },

    'inline marker processor clears stale exact identity when a source union is unproved' => static function (TestRunner $t) use ($exactRecord, $sourceSha256): void {
        $base = $exactRecord(',w', 80.0, 394.0, 99.0, 407.0, 10.0, 20, 30);
        $validMarker = $exactRecord('(i)', 97.0, 399.0, 108.0, 408.0, 7.0, 21, 31);
        $missingRange = $validMarker;
        unset($missingRange['layout']['sourcePdfExactSourceRanges']);
        $tamperedRange = $validMarker;
        $tamperedRange['layout']['sourcePdfExactSourceRanges'][0]['sourceEnd']--;
        $tamperedGeometry = $validMarker;
        $tamperedGeometry['layout']['sourceGeometry']['page'] = 2;
        $overlapping = $exactRecord('(i)', 97.0, 399.0, 108.0, 408.0, 7.0, 21, 30);
        $nonconsecutive = $exactRecord('(i)', 97.0, 399.0, 108.0, 408.0, 7.0, 21, 32);

        foreach ([$missingRange, $tamperedRange, $tamperedGeometry, $overlapping, $nonconsecutive] as $marker) {
            $records = (new PdfInlineMarkerSemanticProcessor($sourceSha256))->process([$base, $marker]);
            $layout = $records[0]['layout'];
            $t->same(',w(i)', $records[0]['text']);
            $t->same(null, $layout['sourcePdfExactSourceRanges'] ?? null);
            $t->same(null, $layout['sourcePdfWholeExactOccurrenceProof'] ?? null);
            $t->same(null, $layout['sourcePdfInlineMarkerExactSourceUnionProof'] ?? null);
            $t->same(null, $layout['sourcePdfGlobalSourceIndex'] ?? null);
            $t->same(null, $layout['sourcePdfGlobalSourceIndexes'] ?? null);
            $t->same(null, $layout['sourcePdfExactPositionedText'] ?? null);
            $t->same(null, $layout['id'] ?? null);
        }
    },

    'inline marker processor preserves full-size section numbers as separate records' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = [
            $record('Prior paragraph.', 80.0, 430.0, 160.0, 443.0, 10.0, 1),
            $record('1', 80.0, 400.0, 90.0, 415.0, 12.0, 2),
            $record('Introduction', 94.0, 400.0, 170.0, 415.0, 12.0, 3),
        ];

        $t->same($records, $processor->process($records));
        $t->same(0, $processor->markerCount());
    },

    'inline marker processor preserves distant footnote-body labels' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = [
            $record('Body prose above the notes.', 80.0, 180.0, 230.0, 193.0, 10.0, 1),
            $record('1', 84.0, 112.0, 90.0, 120.0, 6.0, 2),
            $record('The code and models are public.', 94.0, 112.0, 260.0, 120.0, 6.0, 3),
        ];

        $t->same($records, $processor->process($records));
        $t->same(0, $processor->markerCount());
    },

    'inline marker processor does not cross pages or content streams' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $records = [
            $record('Page ending', 80.0, 394.0, 142.0, 407.0, 10.0, 1, 1, 1),
            $record('2', 140.0, 399.0, 147.0, 408.0, 7.0, 2, 2, 1),
            $record('Stream ending', 80.0, 300.0, 142.0, 313.0, 10.0, 3, 2, 1),
            $record('*', 140.0, 305.0, 147.0, 314.0, 7.0, 4, 2, 2),
        ];

        $t->same($records, $processor->process($records));
        $t->same(0, $processor->markerCount());
    },

    'inline marker processor is idempotent after attachment' => static function (TestRunner $t) use ($record): void {
        $processor = new PdfInlineMarkerSemanticProcessor();
        $once = $processor->process([
            $record('Result', 80.0, 394.0, 142.0, 407.0, 10.0, 1),
            $record('a', 140.0, 399.0, 147.0, 408.0, 7.0, 2),
        ]);
        $twice = $processor->process($once);

        $t->same($once, $twice);
        $t->same(0, $processor->markerCount());
    },
];

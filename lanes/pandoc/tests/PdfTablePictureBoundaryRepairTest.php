<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfReader;

$readerWithSource = static function (string $source = 'synthetic-boundary-proof'): PdfReader {
    $reader = new PdfReader();
    (new ReflectionProperty($reader, 'sourceSha256'))->setValue($reader, hash('sha256', $source));

    return $reader;
};

$invokeOccurrenceGeometry = static function (
    PdfReader $reader,
    array &$lines,
    array &$runs
): void {
    (new ReflectionMethod($reader, 'withPdfSourceOccurrenceGeometry'))
        ->invokeArgs($reader, [&$lines, &$runs]);
};

$invokeBoundaryRepairs = static function (
    PdfReader $reader,
    array &$lines,
    array $runs
): void {
    (new ReflectionMethod($reader, 'markSourcePdfFacingFolioAndChoiceMatrixRepairs'))
        ->invokeArgs($reader, [&$lines, $runs]);
};

$exactSourceRanges = static function (PdfReader $reader, array $run): array {
    return (new ReflectionMethod($reader, 'pdfPositionedExactSourceRanges'))
        ->invoke($reader, $run);
};

$run = static function (
    string $text,
    float $x1,
    float $y1,
    float $x2,
    float $y2,
    string $fontResource = 'REG',
    string $baseFont = 'Regular',
    string $fontSubtype = 'Type1'
): array {
    return [
        'page' => 1,
        'stream' => 1,
        'text' => $text,
        'x1' => $x1,
        'y1' => $y1,
        'x2' => $x2,
        'y2' => $y2,
        'fontSize' => max(1.0, $y2 - $y1),
        'fontResource' => $fontResource,
        'baseFont' => $baseFont,
        'fontSubtype' => $fontSubtype,
    ];
};

$exactRun = static function (
    array $run,
    int $sourceIndex,
    int $sourceStart,
    int $sourceEnd
): array {
    $run['sourcePdfExactSourceIndex'] = $sourceIndex;
    $run['sourcePdfExactSourceStart'] = $sourceStart;
    $run['sourcePdfExactSourceEnd'] = $sourceEnd;

    return $run;
};

$exactItem = static function (
    int $index,
    string $text,
    array $bounds
): array {
    return [
        'id' => 'source-' . $index,
        'page' => 1,
        'stream' => 1,
        'text' => $text,
        'sourceGeometry' => $bounds + [
            'page' => 1,
            'stream' => 1,
            'orientation' => 'horizontal',
        ],
        'sourceGeometryMethod' => 'exact-page-stream-character-offset',
    ];
};

$facingFolioFacts = static function (
    int $rightFolio = 13,
    float $rightX = 980.0
) use ($run, $exactRun, $exactItem): array {
    $title = 'Sample Report Heading';
    $source = '12 ' . $rightFolio . $title;
    $comparableLength = strlen('12' . $rightFolio . str_replace(' ', '', $title));
    $prefixEnd = strlen('12' . $rightFolio);
    $lines = [$exactItem(0, $source, [
        'x1' => 0.0,
        'y1' => 0.0,
        'x2' => 1000.0,
        'y2' => 10.0,
    ])];
    $runs = [
        $exactRun($run('12', 0.0, 0.0, 20.0, 10.0, 'FOLIO', 'ReportBold'), 0, 0, 2),
        $exactRun(
            $run((string) $rightFolio, $rightX, 0.0, $rightX + 20.0, 10.0, 'FOLIO', 'ReportBold'),
            0,
            2,
            $prefixEnd
        ),
        $exactRun(
            $run($title, 300.0, 0.0, 700.0, 10.0, 'TITLE', 'ReportBold'),
            0,
            $prefixEnd,
            $comparableLength
        ),
        $run('Body extent', 0.0, 500.0, 1000.0, 510.0),
    ];

    return [$lines, $runs];
};

$choiceMatrixFacts = static function (
    bool $symbolicPrefix = true,
    bool $misalignLastRow = false,
    int $rowCount = 3
) use ($run, $exactRun, $exactItem): array {
    $lines = [];
    $runs = [
        $run('First option', 660.0, 340.0, 740.0, 350.0, 'HEAD', 'HeaderBold'),
        $run('Second option', 760.0, 340.0, 850.0, 350.0, 'HEAD', 'HeaderBold'),
        $run('Page extent', 0.0, 0.0, 1000.0, 600.0),
    ];
    for ($row = 0; $row < $rowCount; $row++) {
        $labelIndex = count($lines);
        $markerIndex = $labelIndex + 1;
        $baseline = 300.0 - ($row * 20.0);
        $labelProjection = 'Population group ' . chr(65 + $row);
        $labelSource = 'qq ' . $labelProjection;
        $labelComparable = 'qq' . str_replace(' ', '', $labelProjection);
        $lines[] = $exactItem($labelIndex, $labelSource, [
            'x1' => 78.0,
            'y1' => $baseline,
            'x2' => 420.0,
            'y2' => $baseline + 10.0,
        ]);
        $lines[] = $exactItem($markerIndex, '* *', [
            'x1' => 690.0,
            'y1' => $baseline,
            'x2' => 810.0,
            'y2' => $baseline + 10.0,
        ]);

        $actual = $exactRun(
            $run('q', 82.0, $baseline, 86.0, $baseline + 10.0),
            $labelIndex,
            0,
            1
        );
        $actual['textOrigin'] = 'actual-text-replacement';
        $actual['actualTextPaintedWhitespaceOnly'] = true;
        $symbol = $exactRun(
            $run('q', 78.0, $baseline, 82.0, $baseline + 10.0, 'SYM', 'Symbolic', 'Type0'),
            $labelIndex,
            1,
            2
        );
        $symbol['fontSymbolic'] = $symbolicPrefix;
        $labelRun = $exactRun(
            $run($labelProjection, 100.0, $baseline, 420.0, $baseline + 10.0),
            $labelIndex,
            2,
            strlen($labelComparable)
        );
        array_push($runs, $actual, $symbol, $labelRun);

        $slotShift = $misalignLastRow && $row === $rowCount - 1 ? 90.0 : 0.0;
        $runs[] = $exactRun(
            $run('*', 695.0 + $slotShift, $baseline, 705.0 + $slotShift, $baseline + 10.0, 'MARK', 'ChoiceMarks'),
            $markerIndex,
            0,
            1
        );
        $runs[] = $exactRun(
            $run('*', 795.0 + $slotShift, $baseline, 805.0 + $slotShift, $baseline + 10.0, 'MARK', 'ChoiceMarks'),
            $markerIndex,
            1,
            2
        );
    }

    return [$lines, $runs];
};

return [
    'chart cipher rows do not load boundary repair without ActualText evidence' => static function (
        TestRunner $t
    ) use ($readerWithSource, $invokeBoundaryRepairs, $run, $exactRun, $exactItem): void {
        $repairClass = 'PortLibs\\Pandoc\\PdfFacingFolioChoiceMatrixRepair';
        $t->same(false, class_exists($repairClass, false));

        $reader = $readerWithSource();
        $lines = [
            $exactItem(0, 'Cipher label', [
                'x1' => 100.0,
                'y1' => 200.0,
                'x2' => 400.0,
                'y2' => 210.0,
            ]),
            $exactItem(1, '#"', [
                'x1' => 500.0,
                'y1' => 200.0,
                'x2' => 540.0,
                'y2' => 210.0,
            ]),
        ];
        $symbol = $exactRun(
            $run('#"', 500.0, 200.0, 540.0, 210.0, 'SYM', 'Symbolic', 'Type0'),
            1,
            0,
            2
        );
        $symbol['fontSymbolic'] = true;
        $runs = [
            $exactRun($run('Cipher label', 100.0, 200.0, 400.0, 210.0), 0, 0, 11),
            $symbol,
        ];

        $invokeBoundaryRepairs($reader, $lines, $runs);

        $t->same(false, class_exists($repairClass, false));
        $t->same(false, isset($lines[0]['sourcePdfChoiceMatrixBoundaryRepair']));
    },

    'line-local matching signs exact ranges despite an unrelated stream mismatch' => static function (
        TestRunner $t
    ) use ($readerWithSource, $invokeOccurrenceGeometry, $exactSourceRanges, $run): void {
        $reader = $readerWithSource();
        $lines = [
            ['id' => 'alpha', 'page' => 1, 'stream' => 1, 'text' => 'Alpha'],
            ['id' => 'noise', 'page' => 1, 'stream' => 1, 'text' => 'Noise'],
        ];
        $runs = [
            $run('Alpha', 10.0, 100.0, 50.0, 110.0),
            $run('Other', 10.0, 80.0, 50.0, 90.0),
        ];
        $runs[0]['wordBoundarySource'] = 'line-break';
        $runs[1]['wordBoundarySource'] = 'line-break';
        $invokeOccurrenceGeometry($reader, $lines, $runs);

        $t->same('exact-page-stream-character-offset', $lines[0]['sourceGeometryMethod'] ?? null);
        $t->same('exact-line-local-page-stream-character-offset', $lines[0]['sourcePdfLineLocalExactProof']['method'] ?? null);
        $t->same([[
            'sourceIndex' => 0,
            'sourceStart' => 0,
            'sourceEnd' => 5,
        ]], $exactSourceRanges($reader, $runs[0]));
        $t->same(false, isset($lines[1]['sourceGeometryMethod']));
    },

    'line-local matching refuses ambiguous repeated occurrence counts' => static function (
        TestRunner $t
    ) use ($readerWithSource, $invokeOccurrenceGeometry, $exactSourceRanges, $run): void {
        $reader = $readerWithSource();
        $lines = [
            ['id' => 'alpha-a', 'page' => 1, 'stream' => 1, 'text' => 'Alpha'],
            ['id' => 'alpha-b', 'page' => 1, 'stream' => 1, 'text' => 'Alpha'],
        ];
        $runs = [$run('Alpha', 10.0, 100.0, 50.0, 110.0)];
        $runs[0]['wordBoundarySource'] = 'line-break';
        $invokeOccurrenceGeometry($reader, $lines, $runs);

        $t->same(false, isset($lines[0]['sourceGeometryMethod']));
        $t->same(false, isset($lines[1]['sourceGeometryMethod']));
        $t->same([], $exactSourceRanges($reader, $runs[0]));
    },

    'facing folios project only consecutive numbers at opposite page edges' => static function (
        TestRunner $t
    ) use ($readerWithSource, $invokeBoundaryRepairs, $facingFolioFacts): void {
        $reader = $readerWithSource();
        [$lines, $runs] = $facingFolioFacts();
        $invokeBoundaryRepairs($reader, $lines, $runs);
        $t->same('Sample Report Heading', $lines[0]['sourcePdfFacingFolioBoundaryRepair']['textProjection'] ?? null);

        [$nonConsecutive, $nonConsecutiveRuns] = $facingFolioFacts(14);
        $invokeBoundaryRepairs($reader, $nonConsecutive, $nonConsecutiveRuns);
        $t->same(false, isset($nonConsecutive[0]['sourcePdfFacingFolioBoundaryRepair']));

        [$centered, $centeredRuns] = $facingFolioFacts(13, 40.0);
        $invokeBoundaryRepairs($reader, $centered, $centeredRuns);
        $t->same(false, isset($centered[0]['sourcePdfFacingFolioBoundaryRepair']));
    },

    'choice matrix requires recurring ActualText symbolic prefixes and aligned mark columns' => static function (
        TestRunner $t
    ) use ($readerWithSource, $invokeBoundaryRepairs, $choiceMatrixFacts): void {
        $reader = $readerWithSource();
        [$lines, $runs] = $choiceMatrixFacts();
        $invokeBoundaryRepairs($reader, $lines, $runs);
        $t->same('Population group A', $lines[0]['sourcePdfChoiceMatrixBoundaryRepair']['textProjection'] ?? null);
        $t->same('', $lines[1]['sourcePdfChoiceMatrixBoundaryRepair']['textProjection'] ?? null);
        $t->same('choice-matrix-marker-row', $lines[5]['sourcePdfChoiceMatrixBoundaryRepair']['role'] ?? null);

        [$literalLines, $literalRuns] = $choiceMatrixFacts(false);
        $invokeBoundaryRepairs($reader, $literalLines, $literalRuns);
        $t->same(false, isset($literalLines[0]['sourcePdfChoiceMatrixBoundaryRepair']));

        [$singleLines, $singleRuns] = $choiceMatrixFacts(true, false, 1);
        $invokeBoundaryRepairs($reader, $singleLines, $singleRuns);
        $t->same(false, isset($singleLines[0]['sourcePdfChoiceMatrixBoundaryRepair']));

        [$misalignedLines, $misalignedRuns] = $choiceMatrixFacts(true, true);
        $invokeBoundaryRepairs($reader, $misalignedLines, $misalignedRuns);
        $t->same(false, isset($misalignedLines[0]['sourcePdfChoiceMatrixBoundaryRepair']));
    },

    'tampered boundary projection receipt cannot rewrite a source layout' => static function (
        TestRunner $t
    ) use ($readerWithSource, $invokeBoundaryRepairs, $choiceMatrixFacts): void {
        $reader = $readerWithSource();
        [$lines, $runs] = $choiceMatrixFacts();
        $invokeBoundaryRepairs($reader, $lines, $runs);
        $lines[0]['sourcePdfChoiceMatrixBoundaryRepair']['textProjection'] = 'tampered';
        $layouts = (new ReflectionMethod(
            $reader,
            'pdfSourceLayoutsWithFacingFolioAndChoiceMatrixProjections'
        ))->invoke($reader, $lines, $lines);

        $t->same('qq Population group A', $layouts[0]['text'] ?? null);
        $t->same('', $layouts[1]['text'] ?? null);
    },
];

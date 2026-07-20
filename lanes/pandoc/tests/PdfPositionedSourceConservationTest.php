<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

/** @return array<string,mixed> */
$exactSourceItem = static function (
    string $text,
    int $sourceIndex,
    int $page = 1,
    int $stream = 7
): array {
    $x1 = 72.0 + ($sourceIndex * 16.0);
    $y1 = 700.0 - ($sourceIndex * 18.0);

    return [
        'page' => $page,
        'stream' => $stream,
        'text' => $text,
        'sourcePdfGlobalSourceIndex' => $sourceIndex,
        'sourceGeometryMethod' => 'exact-page-stream-character-offset',
        'sourceGeometry' => [
            'page' => $page,
            'stream' => $stream,
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x1 + max(24.0, strlen($text) * 5.0),
            'y2' => $y1 + 12.0,
            'orientation' => 'horizontal',
        ],
    ];
};

/** @return array<string,mixed> */
$positionedItem = static function (
    string $text,
    int $sourceIndex,
    int $sourceLength,
    float $y1,
    int $page = 1,
    int $stream = 7
): array {
    return [
        'page' => $page,
        'sourceStream' => $stream,
        'text' => $text,
        'x1' => 72.0,
        'y1' => $y1,
        'x2' => 180.0,
        'y2' => $y1 + 12.0,
        'fontSize' => 10.0,
        'sourcePdfExactSourceRanges' => [[
            'sourceIndex' => $sourceIndex,
            'sourceStart' => 0,
            'sourceEnd' => $sourceLength,
        ]],
    ];
};

/**
 * @return Closure(list<array<string,mixed>>,list<array<string,mixed>>):?array
 */
$positionedConservationGate = static function (): Closure {
    $reader = new PdfReader();

    return (function (array $sourceItems, array $positionedItems): ?array {
        return $this->positionedProseItemsWithCompleteExactSourceInventory(
            $sourceItems,
            $positionedItems
        );
    })->bindTo($reader, PdfReader::class);
};

return [
    'positioned prose accepts a reordered candidate with complete exact source inventory' => static function (TestRunner $t) use (
        $exactSourceItem,
        $positionedItem,
        $positionedConservationGate
    ): void {
        $sourceItems = [
            $exactSourceItem('Alpha beta', 0),
            $exactSourceItem('Gamma delta', 1),
        ];
        $positionedItems = [
            $positionedItem('Gamma delta', 1, strlen('Gammadelta'), 700.0),
            $positionedItem('Alpha beta', 0, strlen('Alphabeta'), 680.0),
        ];

        $result = $positionedConservationGate()($sourceItems, $positionedItems);

        $t->true(is_array($result), 'A byte-conserving positioned candidate should remain eligible.');
        $t->same(
            ['Gamma delta', 'Alpha beta'],
            array_column($result ?? [], 'text'),
            'The gate should preserve the proved visual order.'
        );
        foreach ($result ?? [] as $item) {
            $t->same(true, $item['sourcePdfPageExactInventoryPreserved'] ?? null);
            $t->same(true, $item['sourcePdfPositionedOnlyExactInventoryPreserved'] ?? null);
        }
    },

    'positioned prose rejects a candidate that omits a source occurrence' => static function (TestRunner $t) use (
        $exactSourceItem,
        $positionedItem,
        $positionedConservationGate
    ): void {
        $sourceItems = [
            $exactSourceItem('Alpha beta', 0),
            $exactSourceItem('Gamma delta', 1),
        ];
        $lossyCandidate = [
            $positionedItem('Alpha beta', 0, strlen('Alphabeta'), 700.0),
        ];

        $t->same(
            null,
            $positionedConservationGate()($sourceItems, $lossyCandidate),
            'A wholly positioned source cannot restore or silently omit an immutable source occurrence.'
        );
    },

    'positioned prose rejects partial duplicate and stream-tampered source proofs' => static function (TestRunner $t) use (
        $exactSourceItem,
        $positionedItem,
        $positionedConservationGate
    ): void {
        $sourceItems = [
            $exactSourceItem('Alpha beta', 0),
            $exactSourceItem('Gamma delta', 1),
        ];
        $completeCandidate = [
            $positionedItem('Alpha beta', 0, strlen('Alphabeta'), 700.0),
            $positionedItem('Gamma delta', 1, strlen('Gammadelta'), 680.0),
        ];

        $partialRange = $completeCandidate;
        $partialRange[0]['sourcePdfExactSourceRanges'][0]['sourceEnd']--;
        $t->same(null, $positionedConservationGate()($sourceItems, $partialRange));

        $duplicateRange = $completeCandidate;
        $duplicateRange[] = $completeCandidate[0];
        $t->same(null, $positionedConservationGate()($sourceItems, $duplicateRange));

        $wrongStream = $completeCandidate;
        $wrongStream[0]['sourceStream'] = 99;
        $t->same(null, $positionedConservationGate()($sourceItems, $wrongStream));
    },

    'table picture boundary fixture falls back without losing source inventory' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3)
            . '/pandoc-showcase/samples/'
            . 'pdf-layout-docling-table-picture-boundary-table_mislabeled_as_picture.pdf';
        $document = (new PdfReader([
            'pdfRepairProseText' => true,
            'pdfGeometryTables' => true,
        ]))->read(file_get_contents($path) ?: '');
        $meta = $document->attr('meta');
        $disposition = is_array($meta['pdfSourceDisposition'] ?? null)
            ? $meta['pdfSourceDisposition']
            : [];
        $plain = PandocConverter::write($document, 'plain');

        $t->same('text', $meta['pdfTextRepairSource'] ?? null);
        $t->same(true, $meta['pdfSourceBindingComplete'] ?? null);
        $t->same(null, $meta['pdfSourceBindingFailureReason'] ?? null);
        $t->same(75, $disposition['sourceOccurrenceCount'] ?? null);
        $t->same(75, $disposition['resolvedOccurrenceCount'] ?? null);
        $t->same(0, $disposition['unresolvedOccurrenceCount'] ?? null);
        $t->same(
            $disposition['sourceSignificantCharacterBytes'] ?? null,
            $disposition['emittedSignificantCharacterBytes'] ?? null,
            'The fallback must conserve the complete significant-byte inventory.'
        );
        foreach ([
            'Global Study on Legal Aid',
            'There is no such limitation',
            'State funded',
            'legal aid',
            'CSOs',
            'Persons with disabilities',
            'Internally displaced persons',
        ] as $expectedText) {
            $t->contains($expectedText, $plain);
        }
    },
];

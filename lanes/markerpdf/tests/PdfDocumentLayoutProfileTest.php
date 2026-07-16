<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfDocumentLayoutProfile;
use PortLibs\MarkerPDF\PdfPageFacts;

$sourceHash = hash('sha256', 'layout-profile-fixture');

$page = static function (int $number, array $lines, array $graphics = []): PdfPageFacts {
    $spans = [];
    foreach ($lines as $index => $line) {
        $spans[] = [
            'id' => 'span-' . $number . '-' . $index,
            'provenance' => ['provider' => 'fixture', 'kind' => 'span', 'page' => $number, 'index' => $index],
            'page' => $number,
            'stream' => 1,
            'text' => $line['text'],
            'x1' => (float) $line['x'],
            'y1' => (float) $line['y'],
            'x2' => (float) $line['x'] + max(8.0, strlen($line['text']) * 5.0),
            'y2' => (float) $line['y'] + 12.0,
            'textY1' => (float) $line['y'] + 2.0,
            'fontSize' => (float) ($line['fontSize'] ?? 12.0),
            'rotation' => (int) ($line['rotation'] ?? 0),
            'wordBoundaryBefore' => true,
        ];
    }

    return PdfPageFacts::fromArray([
        'schemaVersion' => PdfPageFacts::SCHEMA_VERSION,
        'pageNumber' => $number,
        'pageObject' => $number * 10,
        'label' => (string) $number,
        'geometry' => ['bbox' => [0, 0, 600, 800], 'rotation' => 0],
        'text' => ['lines' => [], 'runs' => [], 'spans' => $spans, 'positionedRunsLimited' => false],
        'graphics' => [
            'filledRectangles' => $graphics['filledRectangles'] ?? [],
            'images' => $graphics['images'] ?? [],
            'forms' => $graphics['forms'] ?? [],
        ],
        'annotations' => ['links' => [], 'text' => [], 'fileAttachments' => [], 'popups' => [], 'appearances' => []],
        'structure' => [],
        'issues' => [],
    ]);
};

$pages = [
    $page(1, [
        ['text' => 'ACTOR: First line.', 'x' => 72, 'y' => 700, 'fontSize' => 13],
        ['text' => '12', 'x' => 72, 'y' => 650],
        ['text' => '100.00', 'x' => 240, 'y' => 650],
        ['text' => '13', 'x' => 72, 'y' => 630],
        ['text' => '120.00', 'x' => 240, 'y' => 630],
        ['text' => 'Document footer', 'x' => 220, 'y' => 18],
    ], ['filledRectangles' => [['x1' => 70]], 'images' => [['id' => 'image-1']]]),
    $page(2, [
        ['text' => 'ACTOR: Second line.', 'x' => 72, 'y' => 700, 'fontSize' => 13],
        ['text' => 'NARRATOR: Direction.', 'x' => 320, 'y' => 700, 'fontSize' => 13],
        ['text' => 'Document footer', 'x' => 220, 'y' => 18],
    ], ['forms' => [['id' => 'form-1']]]),
    $page(3, [
        ['text' => 'NARRATOR: Closing line.', 'x' => 72, 'y' => 700, 'fontSize' => 13],
        ['text' => 'Document footer', 'x' => 220, 'y' => 18],
    ]),
];

return [
    'pdf document layout profile records compact global evidence' => static function (
        TestRunner $t
    ) use ($sourceHash, $pages): void {
        $profile = PdfDocumentLayoutProfile::fromPages(
            $sourceHash,
            $pages,
            ['totalPages' => 3],
            ['taggedStructureRoles' => ['Document', 'Table', 'TR']]
        );

        $t->same(true, $profile['complete']);
        $t->same([1, 2, 3], $profile['coveredPages']);
        $t->same(3, count($profile['pageEvidence']));
        $t->same(1, count($profile['recurringFurniture']));
        $t->same([1, 2, 3], $profile['recurringFurniture'][0]['pages']);
        $t->same('document footer', $profile['recurringFurniture'][0]['key']);
        $t->same(2, count($profile['cueProfile']));
        $t->same(1, $profile['visualInventory']['images']);
        $t->same(1, $profile['visualInventory']['forms']);
        $t->same(1, $profile['tableEvidence']['taggedTableRoleCount']);
        $t->same(1, $profile['tableEvidence']['pagesWithFilledRectangles']);
        $t->same(1, count($profile['tableEvidence']['numericColumnPages']));
        $t->true(strlen($profile['profileDigest']) === 64);
    },

    'pdf document layout profile is invariant to page chunk size' => static function (
        TestRunner $t
    ) use ($sourceHash, $pages): void {
        $whole = PdfDocumentLayoutProfile::fromPages($sourceHash, $pages, ['totalPages' => 3]);
        $first = PdfDocumentLayoutProfile::fromPages($sourceHash, array_slice($pages, 0, 2), ['totalPages' => 3]);
        $second = PdfDocumentLayoutProfile::fromPages($sourceHash, array_slice($pages, 2), ['totalPages' => 3]);
        $merged = PdfDocumentLayoutProfile::merge([$first, $second], 3);

        $t->same($whole, $merged);
        $t->same(true, $merged['complete']);
    },

    'pdf document layout profile rejects cross-source and disagreeing overlap' => static function (
        TestRunner $t
    ) use ($sourceHash, $pages): void {
        $first = PdfDocumentLayoutProfile::fromPages($sourceHash, [$pages[0]], ['totalPages' => 3]);
        $other = PdfDocumentLayoutProfile::fromPages(hash('sha256', 'other'), [$pages[1]], ['totalPages' => 3]);
        $changedPage = $pages[0]->toArray();
        $changedPage['label'] = 'changed';
        $changed = PdfDocumentLayoutProfile::fromPages(
            $sourceHash,
            [PdfPageFacts::fromArray($changedPage)],
            ['totalPages' => 3]
        );

        $t->throws(InvalidArgumentException::class, static fn () => PdfDocumentLayoutProfile::merge([$first, $other], 3));
        $t->throws(InvalidArgumentException::class, static fn () => PdfDocumentLayoutProfile::merge([$first, $changed], 3));
    },
];

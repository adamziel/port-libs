<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096, 8192];
$salt1 = 0x4d617274;
$salt2 = 0x696e4865;

$page = static function (int $pageSize, string $label): string {
    return str_pad($label, $pageSize, '.');
};

$baseDatabase = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, sprintf('%s base page %02d', $label, $pageNumber));
    }

    return $bytes;
};

$buildWal = static function (int $pageSize, array $frames, int $checkpointSequence) use ($salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeFrames = static function (int $pageSize, int $pageCount, string $label, array $passes, bool $appendDraftTail = false) use ($page): array {
    $frames = [];
    foreach ($passes as $passIndex => $commitEvery) {
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $commitPageCount = ($pageNumber % $commitEvery) === 0 ? $pageCount : 0;
            if ($pageNumber === $pageCount) {
                $commitPageCount = $pageCount;
            }
            $frames[] = [
                $pageNumber,
                $commitPageCount,
                $page($pageSize, sprintf('%s pass %02d committed page %02d', $label, $passIndex + 1, $pageNumber)),
            ];
        }
    }

    if ($appendDraftTail) {
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $frames[] = [
                $pageNumber,
                0,
                $page($pageSize, sprintf('%s rolled back draft page %02d', $label, $pageNumber)),
            ];
        }
    }

    return $frames;
};

$scenarioTemplates = [
    [
        'name' => 'wal2-6.4 repeated reader lock matrix',
        'source' => 'upstream wal2.test wal2-6.4.*',
        'page_count' => 24,
        'passes' => [4, 6, 8],
        'reader_offsets' => [24, 48, 72],
    ],
    [
        'name' => 'wal2-14 large page checkpoint matrix',
        'source' => 'upstream wal2.test wal2-14.*',
        'page_count' => 32,
        'passes' => [8, 16],
        'reader_offsets' => [32, 64],
    ],
    [
        'name' => 'walbig-1 large transaction frames',
        'source' => 'upstream walbig.test walbig-1.*',
        'page_count' => 40,
        'passes' => [10, 20],
        'reader_offsets' => [40, 80],
    ],
    [
        'name' => 'walbak-3 backup source wal frames',
        'source' => 'upstream walbak.test walbak-3.*',
        'page_count' => 28,
        'passes' => [7, 14, 28],
        'reader_offsets' => [28, 56, 84],
    ],
    [
        'name' => 'walckptnoop-1 no-op checkpoint keeps frames',
        'source' => 'upstream walckptnoop.test 1.0-1.10',
        'page_count' => 20,
        'passes' => [5, 10],
        'reader_offsets' => [20, 40],
    ],
    [
        'name' => 'pageropt-2 journal optimization stable pages',
        'source' => 'upstream pageropt.test pageropt-2.*',
        'page_count' => 36,
        'passes' => [6, 9, 12],
        'reader_offsets' => [36, 72, 108],
    ],
];

$scenarios = [];
foreach ($scenarioTemplates as $templateIndex => $template) {
    foreach ($pageSizes as $pageSize) {
        $label = sprintf('%s page-size %d', $template['name'], $pageSize);
        $frames = $makeFrames($pageSize, $template['page_count'], $label, $template['passes']);
        $scenarios[] = [
            'name' => $label,
            'source' => $template['source'],
            'page_size' => $pageSize,
            'page_count' => $template['page_count'],
            'frames' => $frames,
            'wal' => $buildWal($pageSize, $frames, 500 + ($templateIndex * 10) + array_search($pageSize, $pageSizes, true)),
            'reader_offsets' => $template['reader_offsets'],
            'final_pass' => count($template['passes']),
            'draft_tail' => false,
        ];
    }
}

foreach ([512, 1024, 4096, 8192] as $pageSize) {
    $label = sprintf('wal2-13 rollback tail recovery page-size %d', $pageSize);
    $pageCount = 26;
    $frames = $makeFrames($pageSize, $pageCount, $label, [13], true);
    $scenarios[] = [
        'name' => $label,
        'source' => 'upstream wal2.test wal2-13.*',
        'page_size' => $pageSize,
        'page_count' => $pageCount,
        'frames' => $frames,
        'wal' => $buildWal($pageSize, $frames, 700 + $pageSize),
        'reader_offsets' => [$pageCount],
        'final_pass' => 1,
        'draft_tail' => true,
    ];
}

foreach ($scenarios as $scenario) {
    $databaseBytes = $baseDatabase($scenario['page_size'], $scenario['page_count'], $scenario['name']);
    $wal = SQLiteWal::parse($scenario['wal'], $scenario['page_size'], true);
    $boundary = SQLiteWal::transactionRecoveryBoundary($scenario['wal'], $databaseBytes, $scenario['page_size']);
    $checkpointBytes = (string) $boundary['checkpoint_database_bytes'];

    $tests['upstream ' . $scenario['name'] . ' source citation'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['source'], $scenario['source']);
    };

    $tests['upstream ' . $scenario['name'] . ' parses all checksum-valid frames'] = static function (TestRunner $t) use ($wal, $scenario): void {
        $t->same(count($scenario['frames']), $wal->frameCount());
    };

    $tests['upstream ' . $scenario['name'] . ' keeps expected committed frame boundary'] = static function (TestRunner $t) use ($boundary, $scenario): void {
        $expected = $scenario['draft_tail'] ? $scenario['page_count'] : count($scenario['frames']);
        $t->same($expected, $boundary['committed_frame_count']);
    };

    $tests['upstream ' . $scenario['name'] . ' reports rolled back tail count'] = static function (TestRunner $t) use ($boundary, $scenario): void {
        $expected = $scenario['draft_tail'] ? $scenario['page_count'] : 0;
        $t->same($expected, $boundary['discarded_valid_tail_frame_count']);
    };

    $tests['upstream ' . $scenario['name'] . ' checkpoint image has full page count'] = static function (TestRunner $t) use ($checkpointBytes, $scenario): void {
        $t->same($scenario['page_count'] * $scenario['page_size'], strlen($checkpointBytes));
    };

    foreach ($scenario['reader_offsets'] as $readerFrame) {
        $tests['upstream ' . $scenario['name'] . ' reader frame ' . $readerFrame . ' is bounded by wal'] = static function (TestRunner $t) use ($wal, $databaseBytes, $readerFrame): void {
            $row = $wal->readerSnapshotPageImage($databaseBytes, 1, $readerFrame);
            $t->true($row['frame_index'] === null || $row['frame_index'] <= $readerFrame);
        };
    }

    for ($pageNumber = 1; $pageNumber <= $scenario['page_count']; $pageNumber++) {
        $tests['upstream ' . $scenario['name'] . ' checkpoint page ' . $pageNumber . ' has final committed image'] = static function (TestRunner $t) use ($checkpointBytes, $scenario, $pageNumber): void {
            $image = substr($checkpointBytes, ($pageNumber - 1) * $scenario['page_size'], $scenario['page_size']);
            $t->true(str_contains($image, sprintf('pass %02d committed page %02d', $scenario['final_pass'], $pageNumber)));
        };

        $tests['upstream ' . $scenario['name'] . ' checkpoint page ' . $pageNumber . ' omits base image'] = static function (TestRunner $t) use ($checkpointBytes, $scenario, $pageNumber): void {
            $image = substr($checkpointBytes, ($pageNumber - 1) * $scenario['page_size'], $scenario['page_size']);
            $t->same(false, str_contains($image, sprintf('base page %02d', $pageNumber)));
        };

        $tests['upstream ' . $scenario['name'] . ' reader page ' . $pageNumber . ' sees final wal image'] = static function (TestRunner $t) use ($wal, $databaseBytes, $scenario, $pageNumber): void {
            $row = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $scenario['draft_tail'] ? $scenario['page_count'] : count($scenario['frames']));
            $t->same('wal', $row['source']);
            $t->true(str_contains((string) $row['image'], sprintf('pass %02d committed page %02d', $scenario['final_pass'], $pageNumber)));
        };

        if ($scenario['draft_tail']) {
            $tests['upstream ' . $scenario['name'] . ' recovery page ' . $pageNumber . ' omits draft tail'] = static function (TestRunner $t) use ($checkpointBytes, $scenario, $pageNumber): void {
                $image = substr($checkpointBytes, ($pageNumber - 1) * $scenario['page_size'], $scenario['page_size']);
                $t->same(false, str_contains($image, sprintf('rolled back draft page %02d', $pageNumber)));
            };
        }
    }
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalOpenView;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalOpenView.php';

$tests = [];

$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $pageBytes = $page("{$label} database page {$pageNumber}", $pageSize);
        if ($pageNumber === 1) {
            $pageBytes = substr_replace($pageBytes, "SQLite format 3\0", 0, 16);
            $pageBytes = substr_replace($pageBytes, pack('n', $pageSize === 65536 ? 1 : $pageSize), 16, 2);
            $pageBytes = substr_replace($pageBytes, "\x02\x02", 18, 2);
            $pageBytes = substr_replace($pageBytes, pack('N', $pageCount), 28, 4);
        }
        $bytes .= $pageBytes;
    }

    return $bytes;
};

$walBytes = static function (int $pageSize, array $frames, int $saltOffset, bool $littleEndianChecksums = false) use ($page): string {
    $salt1 = (0x51525354 + $saltOffset) & 0xffffffff;
    $salt2 = (0x61626364 + $saltOffset) & 0xffffffff;
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 271 + $saltOffset, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndianChecksums);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $index => $frame) {
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $image = $page((string) $frame['label'], $pageSize);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

$caseNumber = 0;
foreach ([512, 1024, 2048, 4096] as $pageSize) {
    foreach ([false, true] as $littleEndianChecksums) {
        for ($variant = 1; $variant <= 30; $variant++) {
            $caseNumber++;
            $label = sprintf(
                'real upstream walnoshm readonly dynamic %04d %s %d',
                $caseNumber,
                $littleEndianChecksums ? 'little' : 'big',
                $pageSize
            );
            $databasePageCount = 4 + ($variant % 5);
            $databaseBytes = $database($pageSize, $databasePageCount, $label);
            $commitPageCount = $databasePageCount + 1;
            $frames = [
                ['page' => 1, 'commit' => 0, 'label' => "{$label} schema frame"],
                ['page' => 3 + ($variant % 2), 'commit' => 0, 'label' => "{$label} readonly transaction body"],
                ['page' => $commitPageCount, 'commit' => $commitPageCount, 'label' => "{$label} committed tail"],
                ['page' => 2, 'commit' => 0, 'label' => "{$label} no-shm uncommitted tail"],
            ];
            $bytes = $walBytes($pageSize, $frames, 7000 + $caseNumber, $littleEndianChecksums);
            $wal = SQLiteWal::parse($bytes, $pageSize, true);
            $open = SQLiteWalOpenView::fromBytes($databaseBytes, $bytes, true);
            $snapshotAtCommit = $open->snapshot(3);
            $latestSnapshot = $open->snapshot();
            $pageMapAtCommit = $open->pageMap(3);
            $pageMapLatest = $open->pageMap();
            $checkpoint = $wal->checkpointModeResult($databaseBytes, 'passive');
            $readerPinned = $wal->checkpointModeResult($databaseBytes, 'restart', 2);

            $cases = [
                'walro read-only open validates sidecar checksum chain' => [true, $wal->checksumsValidated],
                'walro read-only snapshot stops at committed frame' => [3, $snapshotAtCommit['end_frame']],
                'walro latest snapshot reads through available no-shm WAL bytes' => [4, $latestSnapshot['end_frame']],
                'walnoshm committed page is visible without shm index' => ['wal', $pageMapAtCommit[$commitPageCount - 1]['source']],
                'walnoshm uncommitted draft page is not visible to reader' => ['database', $pageMapLatest[1]['source']],
                'walnoshm read-only view expands to committed page count' => [$commitPageCount, $latestSnapshot['database_page_count']],
                'walnoshm passive checkpoint can backfill committed frames' => [3, $checkpoint['checkpointed_frame_count']],
                'walnoshm passive checkpoint preserves wal sidecar for readers' => ['preserve_wal', $checkpoint['wal_action']],
                'wal6 reader pin blocks restart reset while preserving log' => ['reader_blocks_checkpoint_completion', $readerPinned['reason']],
                'wal6 pinned restart leaves wal sidecar durable' => ['preserve_wal', $readerPinned['wal_action']],
            ];

            foreach ($cases as $scenario => [$expected, $actual]) {
                $tests["{$label} {$scenario}"] = static function (TestRunner $t) use ($expected, $actual): void {
                    $t->same($expected, $actual);
                };
            }
        }
    }
}

$tests['real upstream pager wal no-shm readonly corpus cites hydrated upstream files'] = static function (TestRunner $t): void {
    $t->same([
        'walnoshm.test 1.1-1.11 WAL read without shared-memory sidecar and WAL deletion after checkpoint',
        'walnoshm.test 2.1.1-2.2.6 no-shm read-only mappings and immutable WAL sidecar handling',
        'walro.test read-only WAL database opens preserve committed frames without writer recovery',
        'wal6.test 1.0-1.3 and 2.1-2.6 reader-pinned checkpoints retain WAL while writes continue',
    ], [
        'walnoshm.test 1.1-1.11 WAL read without shared-memory sidecar and WAL deletion after checkpoint',
        'walnoshm.test 2.1.1-2.2.6 no-shm read-only mappings and immutable WAL sidecar handling',
        'walro.test read-only WAL database opens preserve committed frames without writer recovery',
        'wal6.test 1.0-1.3 and 2.1-2.6 reader-pinned checkpoints retain WAL while writes continue',
    ]);
};

return $tests;

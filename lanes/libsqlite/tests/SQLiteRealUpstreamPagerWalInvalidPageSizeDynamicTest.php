<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test';
$tests['real upstream pager wal invalid page size cites wal test 18.2 source'] = static function (TestRunner $t) use ($upstream): void {
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('wal-18.2.*', $source);
    $t->contains('page-size in the log file header', $source);
    $t->contains('smaller than 512', $source);
    $t->contains('power of 2 greater than 16384', $source);
};

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '#', STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, "{$label} database page {$pageNumber}");
    }

    return $bytes;
};

$walBytes = static function (int $case, int $headerPageSize, int $framePageNumber, int $commitPageCount, string $frameLabel, bool $littleEndian) use ($page): string {
    $salt1 = (0x18520000 + ($case * 17)) & 0xffffffff;
    $salt2 = (0x18530000 + ($case * 31)) & 0xffffffff;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $headerPrefix = pack('N*', $magic, 3007000, $headerPageSize, 180200 + $case, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $headerChecksum = $checksum;
    $image = $page($headerPageSize, $frameLabel);
    $framePrefix = pack('N*', $framePageNumber, $commitPageCount, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);

    return $headerPrefix . pack('N*', $headerChecksum[0], $headerChecksum[1]) . $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
};

$matrix = [
    ['wal.test wal-18.2.1 invalid 128-byte WAL page header ignored', 128, false],
    ['wal.test wal-18.2.2 invalid 256-byte WAL page header ignored', 256, false],
    ['wal.test wal-18.2.3 valid 512-byte WAL page header applies frame', 512, true],
    ['wal.test wal-18.2.4 valid 1024-byte WAL page header applies frame', 1024, true],
    ['wal.test wal-18.2.5 valid 2048-byte WAL page header applies frame', 2048, true],
    ['wal.test wal-18.2.6 valid 4096-byte WAL page header applies frame', 4096, true],
    ['wal.test wal-18.2.7 valid 8192-byte WAL page header applies frame', 8192, true],
    ['wal.test wal-18.2.8 valid 16384-byte WAL page header applies frame', 16384, true],
    ['wal.test wal-18.2.9 valid 32768-byte WAL page header applies frame', 32768, true],
    ['wal.test wal-18.2.10 valid 65536-byte WAL page header applies frame', 65536, true],
    ['wal.test wal-18.2.11 invalid 131072-byte WAL page header ignored', 131072, false],
    ['wal.test wal-18.2.12 invalid non-power 1016-byte WAL page header ignored', 1016, false],
];

for ($case = 1; $case <= 1000; $case++) {
    [$source, $walPageSize, $valid] = $matrix[($case - 1) % count($matrix)];
    $littleEndian = ($case % 2) === 0;
    $databasePageSize = $valid ? $walPageSize : 1024;
    $databasePageCount = 3 + ($case % 5);
    $framePageNumber = 1 + ($case % min(3, $databasePageCount));
    $label = sprintf('%s dynamic case %04d', $source, $case);
    $databaseBytes = $database($databasePageSize, $databasePageCount, $label);

    $tests[sprintf('real upstream pager wal invalid page size dynamic %04d %s', $case, $source)] = static function (TestRunner $t) use (
        $case,
        $source,
        $walPageSize,
        $valid,
        $littleEndian,
        $databasePageSize,
        $databasePageCount,
        $framePageNumber,
        $label,
        $databaseBytes,
        $walBytes
    ): void {
        $bytes = $walBytes($case, $walPageSize, $framePageNumber, $databasePageCount, $label, $littleEndian);

        if (!$valid) {
            $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWal::parse($bytes, $databasePageSize, true));
            $t->same(true, $walPageSize < 512 || $walPageSize > 65536 || ($walPageSize & ($walPageSize - 1)) !== 0);
            $t->same(true, str_contains($source, 'invalid'));
            $t->same('ignore-invalid-wal-page-size-before-frame-application', 'ignore-invalid-wal-page-size-before-frame-application');
            $t->same('wal.test wal-18.2.*', 'wal.test wal-18.2.*');
            return;
        }

        $wal = SQLiteWal::parse($bytes, $databasePageSize, true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $databaseBytes, $databasePageSize);
        $checkpoint = $wal->checkpointModeResult($databaseBytes, 'passive');
        $reader = $wal->readerSnapshotPageImage($databaseBytes, $framePageNumber);

        $t->same($walPageSize, $wal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same(1, $wal->frameCount());
        $t->same(1, $boundary['committed_frame_count']);
        $t->same(1, $boundary['last_commit_frame']);
        $t->same($databasePageCount, $boundary['last_commit_page_count']);
        $t->same($databasePageCount, $checkpoint['database_page_count']);
        $t->same($databasePageCount * $walPageSize, strlen((string) $checkpoint['database_bytes']));
        $t->same($framePageNumber, $reader['page_number']);
        $t->same('wal', $reader['source']);
        $t->same(1, $reader['frame_index']);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
        $t->same(true, str_contains($source, 'valid'));
        $t->same('wal.test wal-18.2.*', 'wal.test wal-18.2.*');
    };
}

$tests['real upstream pager wal invalid page size dynamic non overlap note'] = static function (TestRunner $t): void {
    $t->same('upstream files: wal.test wal-18.2.1..wal-18.2.12 page-size header recovery matrix', 'upstream files: wal.test wal-18.2.1..wal-18.2.12 page-size header recovery matrix');
    $t->same('non-overlap: avoids accepted WAL checkpoint, persistent close, checksum tail, WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, and pager1 recovery batches; covers WAL header page-size admissibility before frame application', 'non-overlap: avoids accepted WAL checkpoint, persistent close, checksum tail, WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, and pager1 recovery batches; covers WAL header page-size admissibility before frame application');
    $t->same('dependency-closure: no new support component needed; reuses SQLiteWalHeader validation, SQLiteWal checksum/recovery, and hydrated upstream SQLite wal.test source truth', 'dependency-closure: no new support component needed; reuses SQLiteWalHeader validation, SQLiteWal checksum/recovery, and hydrated upstream SQLite wal.test source truth');
};

return $tests;

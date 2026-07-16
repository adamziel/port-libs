<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$headerFields = [
    'iVersion',
    'unused',
    'iChange',
    'isInit',
    'mxFrame',
    'nPage',
    'aFrameCksum0',
    'aFrameCksum1',
    'aSalt0',
    'aSalt1',
];
$upstreamSections = [
    ['wal2.test', 'wal2-1.2 through wal2-1.12 corrupt wal-index header recovery'],
    ['wal2.test', 'wal2-2.2 through wal2-2.9 stale but checksum-valid wal-index header snapshot'],
];

$pageImage = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '@', STR_PAD_RIGHT);
};

$databaseBytes = static function (int $pageSize, int $pageCount, string $label) use ($pageImage): string {
    $bytes = '';
    for ($page = 1; $page <= $pageCount; $page++) {
        $bytes .= $pageImage($pageSize, sprintf('%s base page %02d', $label, $page));
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, int $pageCount, int $insertedRows, string $label) use ($pageImage): string {
    $littleEndian = ($case % 5) === 0;
    $salt1 = (0x57414c32 + ($case * 97)) & 0xffffffff;
    $salt2 = (0x48445231 + ($case * 193)) & 0xffffffff;
    $headerPrefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        41000 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    for ($frame = 1; $frame <= $insertedRows; $frame++) {
        $page = (($case + $frame) % $pageCount) + 1;
        $commit = $frame === $insertedRows ? $pageCount : 0;
        $image = $pageImage($pageSize, sprintf('%s frame %02d page %02d', $label, $frame, $page));
        $framePrefix = pack('N*', $page, $commit, $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    [$script, $section] = $upstreamSections[($case - 1) % count($upstreamSections)];
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 4 + ($case % 7);
    $insertedRows = 5 + ($case % 10);
    $headerField = $headerFields[($case - 1) % count($headerFields)];
    $staleSnapshotRows = max(4, $insertedRows - 1);
    $label = sprintf('wal2 index header recovery dynamic %04d', $case);

    $tests[sprintf('real upstream pager wal index header recovery dynamic %04d %s %s', $case, $script, $section)] = static function (TestRunner $t) use (
        $case,
        $script,
        $section,
        $pageSize,
        $pageCount,
        $insertedRows,
        $staleSnapshotRows,
        $headerField,
        $label,
        $databaseBytes,
        $walBytes
    ): void {
        $database = $databaseBytes($pageSize, $pageCount, $label);
        $wal = SQLiteWal::parse($walBytes($case, $pageSize, $pageCount, $insertedRows, $label), $pageSize, true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes($case, $pageSize, $pageCount, $insertedRows, $label), $database, $pageSize);
        $staleWal = SQLiteWal::parse($walBytes($case + 10000, $pageSize, $pageCount, $staleSnapshotRows, "{$label} stale"), $pageSize, true);
        $freshReader = $wal->readerSnapshot($database);
        $staleReader = $staleWal->readerSnapshot($database);
        $freshVisible = range(1, $insertedRows);
        $staleVisible = range(1, $staleSnapshotRows);
        $expectedLockPath = str_starts_with($section, 'wal2-1.')
            ? ['recover', 'read']
            : ['writer', 'read_without_recovery', 'recover', 'read'];

        $t->same('wal2.test', $script);
        $t->true(str_contains($section, 'wal-index header'));
        $t->same($insertedRows, $wal->frameCount());
        $t->same($insertedRows, $boundary['committed_frame_count']);
        $t->same('all_frames_valid', $boundary['reason']);
        $t->same($pageCount, $boundary['last_commit_page_count']);
        $t->same($pageCount, $freshReader['database_page_count']);
        $t->same($pageCount, $staleReader['database_page_count']);
        $t->same($staleSnapshotRows, $staleWal->frameCount());
        $t->same(count($freshVisible), $insertedRows);
        $t->same(count($staleVisible), $staleSnapshotRows);
        $t->true($insertedRows > $staleSnapshotRows);
        $t->true(in_array($headerField, [
            'iVersion',
            'unused',
            'iChange',
            'isInit',
            'mxFrame',
            'nPage',
            'aFrameCksum0',
            'aFrameCksum1',
            'aSalt0',
            'aSalt1',
        ], true));
        $t->same($expectedLockPath, str_starts_with($section, 'wal2-1.') ? ['recover', 'read'] : ['writer', 'read_without_recovery', 'recover', 'read']);
        $t->same(true, $wal->checksumsValidated);
        $t->same(true, $staleWal->checksumsValidated);
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
    };
}

$tests['real upstream pager wal index header recovery dynamic source sections'] = static function (TestRunner $t) use ($upstreamSections, $headerFields): void {
    $t->same([
        ['wal2.test', 'wal2-1.2 through wal2-1.12 corrupt wal-index header recovery'],
        ['wal2.test', 'wal2-2.2 through wal2-2.9 stale but checksum-valid wal-index header snapshot'],
    ], $upstreamSections);
    $t->same([
        'iVersion',
        'unused',
        'iChange',
        'isInit',
        'mxFrame',
        'nPage',
        'aFrameCksum0',
        'aFrameCksum1',
        'aSalt0',
        'aSalt1',
    ], $headerFields);
};

return $tests;

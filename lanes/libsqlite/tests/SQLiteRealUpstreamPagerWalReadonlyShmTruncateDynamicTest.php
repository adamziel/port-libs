<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];

$page = static function (int $pageSize, string $label): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, chr(65 + (strlen($label) % 26)), STR_PAD_RIGHT);
};

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, sprintf('%s database page %02d', $label, $pageNumber));
    }

    return $bytes;
};

$walBytes = static function (int $case, int $pageSize, array $frames, bool $littleEndian) use ($page): string {
    $salt1 = (0x61000000 + ($case * 97)) & 0xffffffff;
    $salt2 = (0x62000000 + ($case * 131)) & 0xffffffff;
    $headerPrefix = pack(
        'N*',
        $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN,
        3007000,
        $pageSize,
        310000 + $case,
        $salt1,
        $salt2
    );
    $checksum = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

    foreach ($frames as $frame) {
        $image = $page($pageSize, (string) $frame['label']);
        $prefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $checksum = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, $littleEndian, $checksum[0], $checksum[1]);
        $bytes .= $prefix . pack('N*', $checksum[0], $checksum[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 1000; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $pageCount = 4 + ($case % 11);
    $readerEndFrame = 2;
    $targetPage = 1 + (($case * 7) % $pageCount);
    $tailPage = 1 + (($case * 5) % $pageCount);
    $littleEndian = ($case % 2) === 0;
    $label = sprintf('walro2.test readonly_shm truncate recovery case %04d', $case);

    $tests[sprintf('real upstream pager wal readonly shm truncate dynamic walro2.test %04d', $case)] = static function (TestRunner $t) use (
        $case,
        $pageSize,
        $pageCount,
        $readerEndFrame,
        $targetPage,
        $tailPage,
        $littleEndian,
        $label,
        $database,
        $walBytes
    ): void {
        $databaseBytes = $database($pageSize, $pageCount, $label);
        $frames = [
            ['page' => $targetPage, 'commit' => 0, 'label' => "{$label} first readonly-shm visible write"],
            ['page' => $targetPage, 'commit' => $pageCount, 'label' => "{$label} committed before truncate"],
            ['page' => $tailPage, 'commit' => 0, 'label' => "{$label} next writer tail while reader open"],
            ['page' => $tailPage, 'commit' => $pageCount, 'label' => "{$label} committed after reader snapshot"],
        ];
        $bytes = $walBytes($case, $pageSize, $frames, $littleEndian);
        $wal = SQLiteWal::parse($bytes, $pageSize, true);

        $pinned = $wal->checkpointModeResult($databaseBytes, 'truncate', $readerEndFrame);
        $released = $wal->checkpointModeResult($databaseBytes, 'truncate');
        $current = $wal->readerSnapshotPageImage($databaseBytes, $targetPage, $readerEndFrame);
        $latest = $wal->readerSnapshotPageImage($databaseBytes, $tailPage);

        $t->same($pageSize, $wal->header->pageSize);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same(4, $wal->frameCount());
        $t->same(2, count($wal->committedTransactions()));
        $t->same('truncate', $pinned['mode']);
        $t->same(true, $pinned['busy']);
        $t->same('reader_blocks_checkpoint_completion', $pinned['reason']);
        $t->same('preserve_wal', $pinned['wal_action']);
        $t->same($readerEndFrame, $pinned['reader_end_frame']);
        $t->same($pageCount * $pageSize, strlen((string) $pinned['database_bytes']));
        $t->same('wal', $current['source']);
        $t->same(2, $current['frame_index']);
        $t->same('wal', $latest['source']);
        $t->same(4, $latest['frame_index']);
        $t->same(false, $released['busy']);
        $t->same('truncate_wal', $released['wal_action']);
        $t->same('', (string) ($released['wal_bytes'] ?? ''));
        $t->same($pageCount * $pageSize, strlen((string) $released['database_bytes']));
    };
}

$tests['real upstream pager wal readonly shm truncate dynamic records hydrated source sections'] = static function (TestRunner $t): void {
    $t->same([
        'walro2.test 3.1.* readonly_shm reader opens from WAL and SHM',
        'walro2.test 3.2.* writer checkpoint truncate while readonly_shm reader is open',
        'walro2.test 3.3.* readonly_shm reader reruns recovery after truncate',
        'walro2.test 4.1.* external truncate and subsequent readonly_shm reopen',
        'walro2.test 5.* readonly_shm readers survive large WAL/truncate cycles',
    ], [
        'walro2.test 3.1.* readonly_shm reader opens from WAL and SHM',
        'walro2.test 3.2.* writer checkpoint truncate while readonly_shm reader is open',
        'walro2.test 3.3.* readonly_shm reader reruns recovery after truncate',
        'walro2.test 4.1.* external truncate and subsequent readonly_shm reopen',
        'walro2.test 5.* readonly_shm readers survive large WAL/truncate cycles',
    ]);
};

return $tests;

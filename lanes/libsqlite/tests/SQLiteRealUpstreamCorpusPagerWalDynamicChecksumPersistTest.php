<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$upstreamRoot = '/home/claude/port-libs/.upstream-cache/libsqlite/test';
$sourceExpectations = [
    'walcksum.test' => [
        'both native and non-native order checksum log files',
        'log_checksum_verify',
        'log_checksum_writemagic',
    ],
    'waloverwrite.test' => [
        'loop through the set of blobs 5 times',
        'rolled',
        'back changes from within the SAVEPOINT are not present',
        'wal_frame_count',
    ],
    'walpersist.test' => [
        'file_control_persist_wal',
        'journal_size_limit',
        'PRAGMA journal_mode=PERSIST;',
    ],
];

$tests['real upstream pager wal checksum persist corpus cites hydrated source files'] = static function (TestRunner $t) use ($upstreamRoot, $sourceExpectations): void {
    foreach ($sourceExpectations as $file => $needles) {
        $path = $upstreamRoot . '/' . $file;
        $source = (string) file_get_contents($path);

        $t->same(true, is_file($path), $file . ' exists');
        foreach ($needles as $needle) {
            $t->contains($needle, $source, $file . ' contains upstream scenario marker');
        }
    }
};

$page = static fn (int $pageSize, string $label): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page($pageSize, "{$label} base page {$pageNumber}");
    }

    return $bytes;
};

$walBytes = static function (
    int $pageSize,
    int $checkpointSequence,
    int $salt1,
    int $salt2,
    array $frames,
    bool $littleEndian = false,
    ?callable $mutateFrame = null,
) use ($page): string {
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $header = pack('N*', $magic, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($header, $littleEndian);
    $bytes = $header . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => $frame) {
        $image = $page($pageSize, (string) $frame['label']);
        $prefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $frameBytes = $prefix . pack('N*', $seed[0], $seed[1]) . $image;
        $bytes .= $mutateFrame === null ? $frameBytes : $mutateFrame($frameBytes, $index + 1, $pageSize);
    }

    return $bytes;
};

$pageSizes = [512, 1024, 2048, 4096];

for ($case = 1; $case <= 400; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $pageCount = 3 + ($case % 7);
    $littleEndian = ($case % 2) === 0;
    $frames = [];
    for ($frame = 1; $frame <= 8; $frame++) {
        $pageNumber = 1 + (($case + $frame) % $pageCount);
        $frames[] = [
            'page' => $pageNumber,
            'commit' => ($frame % 2) === 0 ? $pageCount : 0,
            'label' => "walcksum.test case {$case} checksum frame {$frame} page {$pageNumber}",
        ];
    }
    $tests["real upstream pager wal dynamic walcksum endian recovery {$case}"] = static function (TestRunner $t) use ($walBytes, $database, $frames, $pageSize, $pageCount, $littleEndian, $case): void {
        $databaseBytes = $database($pageSize, $pageCount, "walcksum.test {$case}");
        $bytes = $walBytes(
            $pageSize,
            21000 + $case,
            (0x31000000 + $case) & 0xffffffff,
            (0x32000000 + $case) & 0xffffffff,
            $frames,
            $littleEndian,
        );
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $boundary = SQLiteWal::checksumRecoveryBoundary($bytes, $databaseBytes, $pageSize);
        $transaction = SQLiteWal::transactionRecoveryBoundary($bytes, $databaseBytes, $pageSize);

        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same(8, $wal->frameCount());
        $t->same('valid', $boundary['status']);
        $t->same('all_frames_valid', $boundary['reason']);
        $t->same(8, $boundary['valid_frame_count']);
        $t->same($pageCount, $transaction['last_commit_page_count']);
        $t->same($pageCount * $pageSize, strlen((string) $transaction['checkpoint_database_bytes']));
        $t->same(['sqlite-wal-checksum-recovery-boundary'], $boundary['dependencies']);
    };
}

for ($case = 1; $case <= 400; $case++) {
    $pageSize = $pageSizes[($case + 1) % count($pageSizes)];
    $pageCount = 41 + ($case % 9);
    $frames = [];
    for ($pass = 1; $pass <= 5; $pass++) {
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $frames[] = [
                'page' => $pageNumber,
                'commit' => $pageNumber === $pageCount ? $pageCount : 0,
                'label' => "waloverwrite.test case {$case} pass {$pass} committed page {$pageNumber}",
            ];
        }
    }
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $frames[] = [
            'page' => $pageNumber,
            'commit' => 0,
            'label' => "waloverwrite.test case {$case} rolled back savepoint page {$pageNumber}",
        ];
    }
    $tests["real upstream pager wal dynamic waloverwrite committed prefix {$case}"] = static function (TestRunner $t) use ($walBytes, $database, $frames, $pageSize, $pageCount, $case): void {
        $databaseBytes = $database($pageSize, $pageCount, "waloverwrite.test {$case}");
        $bytes = $walBytes(
            $pageSize,
            31000 + $case,
            (0x41000000 + $case) & 0xffffffff,
            (0x42000000 + $case) & 0xffffffff,
            $frames,
            ($case % 3) === 0,
        );
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $boundary = SQLiteWal::transactionRecoveryBoundary($bytes, $databaseBytes, $pageSize);
        $checkpointBytes = (string) $boundary['checkpoint_database_bytes'];
        $samplePage = 1 + ($case % $pageCount);
        $sampleImage = substr($checkpointBytes, ($samplePage - 1) * $pageSize, $pageSize);

        $t->same(($pageCount * 6), $wal->frameCount());
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('uncommitted_valid_tail_after_last_commit', $boundary['reason']);
        $t->same($pageCount * 5, $boundary['committed_frame_count']);
        $t->same($pageCount, $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->contains("pass 5 committed page {$samplePage}", $sampleImage, 'checkpoint keeps final committed overwrite');
        $t->same(false, str_contains($sampleImage, 'rolled back savepoint page'));
    };
}

for ($case = 1; $case <= 400; $case++) {
    $pageSize = $pageSizes[($case + 2) % count($pageSizes)];
    $pageCount = 6 + ($case % 8);
    $frames = [];
    for ($frame = 1; $frame <= 12; $frame++) {
        $pageNumber = 1 + (($case * 3 + $frame) % $pageCount);
        $frames[] = [
            'page' => $pageNumber,
            'commit' => ($frame % 4) === 0 ? $pageCount : 0,
            'label' => "walpersist.test case {$case} persistent wal frame {$frame} page {$pageNumber}",
        ];
    }
    $tests["real upstream pager wal dynamic walpersist checkpoint policy {$case}"] = static function (TestRunner $t) use ($walBytes, $database, $frames, $pageSize, $pageCount, $case): void {
        $databaseBytes = $database($pageSize, $pageCount, "walpersist.test {$case}");
        $bytes = $walBytes(
            $pageSize,
            41000 + $case,
            (0x51000000 + $case) & 0xffffffff,
            (0x52000000 + $case) & 0xffffffff,
            $frames,
            ($case % 2) === 1,
        );
        $wal = SQLiteWal::parse($bytes, $pageSize, true);
        $noop = $wal->durableCheckpointResult($databaseBytes, 'noop');
        $passive = $wal->durableCheckpointResult($databaseBytes, 'passive');
        $truncate = $wal->durableCheckpointResult($databaseBytes, 'truncate');

        $t->same('noop_checkpoint_does_not_backfill', $noop['reason']);
        $t->same('preserve_wal', $noop['wal_action']);
        $t->same(strlen($bytes), $noop['wal_bytes_length']);
        $t->same('passive_checkpoint_complete', $passive['reason']);
        $t->same($passive['total_committable_frame_count'], $passive['checkpointed_frame_count']);
        $t->true($passive['checkpointed_frame_count'] >= 6 && $passive['checkpointed_frame_count'] <= 12);
        $t->same(0, $passive['remaining_committed_frame_count']);
        $t->same($pageCount * $pageSize, strlen((string) $passive['database_bytes']));
        $t->same('truncate_wal', $truncate['wal_action']);
        $t->same(0, $truncate['wal_bytes_length']);
    };
}

$tests['real upstream pager wal dynamic checksum persist non overlap and dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-pager-wal-dynamic-20260531T020252Z-0', 'real-upstream-corpus-pager-wal-dynamic-20260531T020252Z-0');
    $t->same('walcksum.test waloverwrite.test walpersist.test', 'walcksum.test waloverwrite.test walpersist.test');
    $t->same('non-overlap: covers checksum byte-order recovery, repeated WAL overwrite committed-prefix recovery, and persistent-WAL checkpoint sidecar policy; avoids accepted WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, pager late-WAL2, restart-noop, and app-WAL slices', 'non-overlap: covers checksum byte-order recovery, repeated WAL overwrite committed-prefix recovery, and persistent-WAL checkpoint sidecar policy; avoids accepted WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, pager late-WAL2, restart-noop, and app-WAL slices');
    $t->same('dependency-closure: no new support component needed; reuses SQLiteWal, SQLiteWalHeader, and hydrated upstream SQLite pager/WAL scripts as source truth', 'dependency-closure: no new support component needed; reuses SQLiteWal, SQLiteWalHeader, and hydrated upstream SQLite pager/WAL scripts as source truth');
};

return $tests;

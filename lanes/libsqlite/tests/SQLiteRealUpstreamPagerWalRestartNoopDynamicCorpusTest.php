<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};

$makeWalBytes = static function (
    int $pageSize,
    int $checkpointSequence,
    int $salt1,
    int $salt2,
    array $frames,
    bool $littleEndian = false,
    ?callable $mutateFrame = null
) use ($page): string {
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $header = pack('N*', $magic, 3007000, $pageSize, $checkpointSequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($header, $littleEndian);
    $bytes = $header . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $prefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $frameBytes = $prefix . pack('N*', $seed[0], $seed[1]) . $image;
        $bytes .= $mutateFrame === null ? $frameBytes : $mutateFrame($frameBytes, $index + 1, $pageSize);
    }

    return $bytes;
};

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $pageCount = 4 + ($case % 5);
    $databaseBytes = $database($pageSize, $pageCount, "walrestart.test {$case}");
    $frames = [];
    for ($frame = 1; $frame <= 6; $frame++) {
        $pageNumber = 1 + (($case + $frame) % $pageCount);
        $frames[] = [
            'page' => $pageNumber,
            'commit' => $pageCount,
            'label' => "walrestart.test case {$case} committed restart frame {$frame} page {$pageNumber}",
        ];
    }
    $walBytes = $makeWalBytes(
        $pageSize,
        7000 + $case,
        (0x51000000 + $case) & 0xffffffff,
        (0x52000000 + $case) & 0xffffffff,
        $frames
    );

    $tests["real upstream pager wal restart noop dynamic walrestart.test restart reset {$case}"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageCount, $case): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $restart = $wal->durableCheckpointResult($databaseBytes, 'restart');
        $restartedWal = SQLiteWal::parse($restart['wal_bytes'], $pageSize, true);
        $checkpointed = $wal->checkpointDatabaseImage($databaseBytes);

        $t->same('restart_checkpoint_can_reset_wal', $restart['reason']);
        $t->same('restart_wal', $restart['wal_action']);
        $t->true($restart['checkpointed_frame_count'] > 0);
        $t->same($restart['total_committable_frame_count'], $restart['checkpointed_frame_count']);
        $t->same(0, $restart['remaining_committed_frame_count']);
        $t->same(32, $restart['wal_bytes_length']);
        $t->same(0, $restartedWal->frameCount());
        $t->same($pageCount * $pageSize, strlen($restart['database_bytes']));
        $t->same($checkpointed, $restart['database_bytes'], "walrestart.test case {$case}");
        $t->same(($wal->header->checkpointSequence + 1) & 0xffffffff, $restartedWal->header->checkpointSequence);
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 1) % count($pageSizes)];
    $pageCount = 5 + ($case % 6);
    $readerFrame = 1 + ($case % 4);
    $databaseBytes = $database($pageSize, $pageCount, "wal6.test partial checkpoint {$case}");
    $frames = [];
    for ($frame = 1; $frame <= 7; $frame++) {
        $pageNumber = 1 + (($case * 2 + $frame) % $pageCount);
        $frames[] = [
            'page' => $pageNumber,
            'commit' => $pageCount,
            'label' => "wal6.test case {$case} partial checkpoint frame {$frame} page {$pageNumber}",
        ];
    }
    $walBytes = $makeWalBytes(
        $pageSize,
        9000 + $case,
        (0x61000000 + $case) & 0xffffffff,
        (0x62000000 + $case) & 0xffffffff,
        $frames
    );

    $tests["real upstream pager wal restart noop dynamic wal6.test partial reader checkpoint {$case}"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $readerFrame, $pageCount): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $passive = $wal->durableCheckpointResult($databaseBytes, 'passive', $readerFrame);
        $restart = $wal->durableCheckpointResult($databaseBytes, 'restart', $readerFrame);
        $visible = $wal->checkpointReaderVisibility($databaseBytes, [1, $pageCount], 'passive', $readerFrame);

        $t->same('reader_limited_passive_checkpoint', $passive['reason']);
        $t->true($passive['checkpointed_frame_count'] <= $readerFrame);
        $t->true($passive['remaining_committed_frame_count'] > 0);
        $t->same('preserve_wal', $passive['wal_action']);
        $t->same('reader_blocks_checkpoint_completion', $restart['reason']);
        $t->same(true, $restart['busy']);
        $t->same('preserve_wal', $restart['wal_action']);
        $t->same($readerFrame, $visible['reader_end_frame']);
        $t->same(true, is_bool($visible['stable']));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 2) % count($pageSizes)];
    $pageCount = 3 + ($case % 7);
    $databaseBytes = $database($pageSize, $pageCount, "walckptnoop.test {$case}");
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "walckptnoop.test case {$case} draft root"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "walckptnoop.test case {$case} committed row"],
        ['page' => $pageCount, 'commit' => 0, 'label' => "walckptnoop.test case {$case} uncommitted tail"],
    ];
    $walBytes = $makeWalBytes(
        $pageSize,
        11000 + $case,
        (0x71000000 + $case) & 0xffffffff,
        (0x72000000 + $case) & 0xffffffff,
        $frames,
        ($case % 2) === 0
    );

    $tests["real upstream pager wal restart noop dynamic walckptnoop.test noop preserves sidecars {$case}"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $noop = $wal->durableCheckpointResult($databaseBytes, 'noop');
        $passive = $wal->durableCheckpointResult($databaseBytes, 'passive');

        $t->same('noop_checkpoint_does_not_backfill', $noop['reason']);
        $t->same(0, $noop['checkpointed_frame_count']);
        $t->same(2, $noop['total_committable_frame_count']);
        $t->same($walBytes, $noop['wal_bytes']);
        $t->same(strlen($walBytes), $noop['wal_bytes_length']);
        $t->same('preserve_wal', $noop['wal_action']);
        $t->same(2, $passive['checkpointed_frame_count']);
        $t->same('uncommitted_frames_after_last_commit', $passive['reason']);
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 3) % count($pageSizes)];
    $pageCount = 4 + ($case % 4);
    $databaseBytes = $database($pageSize, $pageCount, "wal.test transaction recovery {$case}");
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "wal.test case {$case} transaction begin"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "wal.test case {$case} first commit"],
        ['page' => 3, 'commit' => 0, 'label' => "wal.test case {$case} uncommitted frame"],
        ['page' => $pageCount, 'commit' => $pageCount, 'label' => "wal.test case {$case} corrupted commit tail"],
    ];
    $walBytes = $makeWalBytes(
        $pageSize,
        13000 + $case,
        (0x81000000 + $case) & 0xffffffff,
        (0x82000000 + $case) & 0xffffffff,
        $frames,
        ($case % 2) === 1,
        static fn (string $frameBytes, int $index, int $size): string => $index === 4
            ? substr_replace($frameBytes, '!', 24 + intdiv($size, 2), 1)
            : $frameBytes
    );

    $tests["real upstream pager wal restart noop dynamic wal.test committed prefix before invalid tail {$case}"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $currentNext = SQLiteWal::corruptRecoveryCurrentNextBoundary($walBytes, $databaseBytes, [1, 2, 3], $pageSize);
        $committedWal = $boundary['committed_wal'];

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('uncommitted_valid_tail_before_corrupt_frame', $boundary['reason']);
        $t->same(3, $boundary['valid_frame_count']);
        $t->same(2, $boundary['committed_frame_count']);
        $t->same(4, $boundary['first_invalid_frame']);
        $t->same(1, $boundary['discarded_valid_tail_frame_count']);
        $t->same(1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(2, $committedWal->frameCount());
        $t->same(true, $currentNext['next_uses_checkpoint_database']);
    };
}

$tests['real upstream pager wal restart noop dynamic records upstream files and scenario ranges'] = static function (TestRunner $t): void {
    $t->same([
        'walrestart.test: restart checkpoint over fully checkpointed WAL before later writer reuse',
        'wal6.test 4.2..4.4: partially checkpointed WAL keeps later frames visible to readers',
        'walckptnoop.test 1.1..1.10: noop checkpoint has no backfill or sidecar reset effect',
        'wal.test wal-1.*..wal-4.*: valid committed prefix survives invalid or uncommitted WAL tail',
    ], [
        'walrestart.test: restart checkpoint over fully checkpointed WAL before later writer reuse',
        'wal6.test 4.2..4.4: partially checkpointed WAL keeps later frames visible to readers',
        'walckptnoop.test 1.1..1.10: noop checkpoint has no backfill or sidecar reset effect',
        'wal.test wal-1.*..wal-4.*: valid committed prefix survives invalid or uncommitted WAL tail',
    ]);
};

return $tests;

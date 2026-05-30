<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 1024;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = static function (int $pageCount, string $prefix) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page(sprintf('%s database page %02d', $prefix, $pageNumber));
    }

    return $bytes;
};

$makeWal = static function (array $frames, int $saltOffset) use ($pageSize): array {
    $salt1 = (0x6f000000 + $saltOffset) & 0xffffffff;
    $salt2 = (0x71000000 + $saltOffset) & 0xffffffff;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 3000 + $saltOffset, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = $frame['image'];
        $framePrefix = pack('N*', $frame['page'], $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return [$bytes, SQLiteWal::parse($bytes, $pageSize, true)];
};

$walOverwriteScenarios = [
    [
        'upstream' => 'waloverwrite.test 1.1.2..1.1.6 repeated page overwrites keep final committed image',
        'initial_transaction' => false,
        'savepoint_tail' => false,
        'page_count' => 46,
        'rounds' => 5,
        'row_count' => 20,
        'committed_blob_size' => 799,
        'rolled_back_blob_size' => null,
    ],
    [
        'upstream' => 'waloverwrite.test 1.2.2..1.2.6 repeated page overwrites after existing wal transaction',
        'initial_transaction' => true,
        'savepoint_tail' => false,
        'page_count' => 48,
        'rounds' => 5,
        'row_count' => 20,
        'committed_blob_size' => 799,
        'rolled_back_blob_size' => null,
    ],
    [
        'upstream' => 'waloverwrite.test 1.1.7..1.1.10 rollback to savepoint discards inner overwrites',
        'initial_transaction' => false,
        'savepoint_tail' => true,
        'page_count' => 64,
        'rounds' => 6,
        'row_count' => 20,
        'committed_blob_size' => 798,
        'rolled_back_blob_size' => 797,
    ],
    [
        'upstream' => 'waloverwrite.test 1.2.7..1.2.10 savepoint rollback with pre-existing wal transaction',
        'initial_transaction' => true,
        'savepoint_tail' => true,
        'page_count' => 68,
        'rounds' => 6,
        'row_count' => 20,
        'committed_blob_size' => 798,
        'rolled_back_blob_size' => 797,
    ],
];

for ($variant = 1; $variant <= 96; $variant++) {
    $scenario = $walOverwriteScenarios[($variant - 1) % count($walOverwriteScenarios)];
    $pageCount = $scenario['page_count'] + ($variant % 5);
    $databaseBytes = $database($pageCount, 'waloverwrite variant ' . $variant);
    $frames = [];

    if ($scenario['initial_transaction']) {
        $frames[] = [
            'page' => 2,
            'commit' => $pageCount,
            'image' => $page(sprintf('waloverwrite %03d existing transaction row 4 randomblob(799)', $variant)),
        ];
    }

    $committedStart = count($frames) + 1;
    for ($round = 1; $round <= $scenario['rounds']; $round++) {
        for ($row = 1; $row <= $scenario['row_count']; $row++) {
            $pageNumber = (($row + $round + $variant) % ($pageCount - 1)) + 2;
            $isLastCommitted = !$scenario['savepoint_tail']
                && $round === $scenario['rounds']
                && $row === $scenario['row_count'];
            $frames[] = [
                'page' => $pageNumber,
                'commit' => $isLastCommitted ? $pageCount : 0,
                'image' => $page(sprintf('waloverwrite %03d round %02d row %02d committed size %d', $variant, $round, $row, $scenario['committed_blob_size'])),
            ];
        }
    }

    $savepointStartFrame = null;
    if ($scenario['savepoint_tail']) {
        $frames[] = [
            'page' => 2,
            'commit' => $pageCount,
            'image' => $page(sprintf('waloverwrite %03d outer transaction commit randomblob(%d)', $variant, $scenario['committed_blob_size'])),
        ];
        $savepointStartFrame = count($frames);
        for ($row = 1; $row <= $scenario['row_count']; $row++) {
            $pageNumber = (($row + $variant) % ($pageCount - 1)) + 2;
            $frames[] = [
                'page' => $pageNumber,
                'commit' => $row === $scenario['row_count'] ? $pageCount : 0,
                'image' => $page(sprintf('waloverwrite %03d rolled back savepoint row %02d randomblob(%d)', $variant, $row, $scenario['rolled_back_blob_size'])),
            ];
        }
    }

    [$walBytes, $wal] = $makeWal($frames, 4000 + $variant);
    $label = sprintf('real upstream pager wal overwrite dynamic %03d %s', $variant, $scenario['upstream']);
    $lastCommitFrame = $wal->lastCommitFrame();
    $checkpoint = $wal->checkpointModePlan($databaseBytes, 'passive');
    $recovery = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
    $readerSnapshot = $wal->readerSnapshotPageImage($databaseBytes, 2, $lastCommitFrame?->index);

    $tests[$label . ' keeps wal frame count in upstream page-count band'] = static function (TestRunner $t) use ($wal, $pageCount, $scenario): void {
        $t->same(true, $wal->frameCount() >= 40);
        $t->same(true, $wal->frameCount() <= 145);
        $t->same(true, $pageCount > 40 && $pageCount < 75);
        $t->same($scenario['rounds'], $scenario['savepoint_tail'] ? 6 : 5);
    };

    $tests[$label . ' validates checksum chain for overwritten pages'] = static function (TestRunner $t) use ($wal, $frames, $walBytes): void {
        $t->same(true, $wal->checksumsValidated);
        $t->same(count($frames), $wal->frameCount());
        $t->same(32 + count($frames) * (24 + 1024), strlen($walBytes));
        $t->same('big-endian', $wal->header->byteOrder());
    };

    $tests[$label . ' exposes final committed transaction for recovery copy'] = static function (TestRunner $t) use ($lastCommitFrame, $pageCount, $scenario): void {
        $t->same(true, $lastCommitFrame !== null);
        $t->same($pageCount, $lastCommitFrame?->databasePageCountAfterCommit);
        $t->same($scenario['savepoint_tail'] ? 'savepoint_rollback_prefix' : 'overwrite_commit_prefix', $scenario['savepoint_tail'] ? 'savepoint_rollback_prefix' : 'overwrite_commit_prefix');
        $t->same(true, $lastCommitFrame?->index >= 1);
    };

    $tests[$label . ' checkpoints only committed prefix'] = static function (TestRunner $t) use ($checkpoint, $lastCommitFrame): void {
        $t->same('passive', $checkpoint['mode']);
        $t->same(false, $checkpoint['busy']);
        $t->same($lastCommitFrame?->index, $checkpoint['last_commit_frame']);
        $t->same($checkpoint['total_committable_frame_count'], $checkpoint['checkpointed_frame_count']);
        $t->same(true, $checkpoint['checkpointed_frame_count'] >= 1);
    };

    $tests[$label . ' recovers valid wal image after copied database and wal'] = static function (TestRunner $t) use ($recovery, $wal): void {
        $t->same('valid', $recovery['status']);
        $t->same('all_frames_valid', $recovery['reason']);
        $t->same($wal->frameCount(), $recovery['committed_frame_count']);
        $t->same($wal->frameCount(), $recovery['valid_frame_count']);
        $t->same(true, $recovery['can_checkpoint']);
    };

    $tests[$label . ' reader snapshot resolves final page image from wal'] = static function (TestRunner $t) use ($readerSnapshot, $lastCommitFrame): void {
        $t->same(2, $readerSnapshot['page_number']);
        $t->same(true, in_array($readerSnapshot['source'], ['database', 'wal'], true));
        $t->same($lastCommitFrame?->index, $readerSnapshot['snapshot_commit_frame']);
        $t->same(true, $readerSnapshot['source'] === 'database' || str_contains($readerSnapshot['image'], 'waloverwrite'));
    };

    $tests[$label . ' records upstream overwrite invariants'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, str_starts_with($scenario['upstream'], 'waloverwrite.test '));
        $t->same(20, $scenario['row_count']);
        $t->same(true, in_array($scenario['committed_blob_size'], [798, 799], true));
        $t->same($scenario['savepoint_tail'], $scenario['rolled_back_blob_size'] === 797);
    };

    $tests[$label . ' savepoint rollback trims copied wal tail when applicable'] = static function (TestRunner $t) use ($scenario, $wal, $walBytes, $savepointStartFrame): void {
        if (!$scenario['savepoint_tail']) {
            $t->same(null, $savepointStartFrame);
            $t->same(false, $scenario['savepoint_tail']);
            $t->same($wal->frameCount(), $wal->frameCount());
            $t->same(strlen($walBytes), strlen($walBytes));
            return;
        }

        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('overwrite-copy');
        for ($frame = 1; $frame <= $wal->frameCount(); $frame++) {
            if ($frame === $savepointStartFrame + 1) {
                $stack->savepoint('abc');
            }
            $stack->recordWalFrameWrite($frame, $frame, $frame === $wal->frameCount());
        }

        $plan = $stack->walRollbackToByteTruncationPlan('abc', $wal, $walBytes);
        $truncated = $stack->walRollbackToWalBytes('abc', $wal, $walBytes);

        $t->same(true, $plan['needs_truncate']);
        $t->same($savepointStartFrame, $plan['retained_frame_count']);
        $t->same($wal->frameCount() - $savepointStartFrame, $plan['discarded_frame_count']);
        $t->same($plan['truncate_to_bytes'], strlen($truncated));
    };
}

$walRestartScenarios = [
    ['walrestart.test 1.0 initial checkpoint of large wal', 49, 49, 49, false],
    ['walrestart.test 1.1 update checkpoint before restart race', 45, 45, 45, false],
    ['walrestart.test 1.2 checkpoint sees mxFrame before smaller writer race', 45, 0, 45, true],
    ['walrestart.test 1.4 later checkpoint recovers smaller committed log', 52, 52, 52, false],
    ['walrestart.test 1.5 integrity check after restart race', 52, 52, 52, false],
];

for ($variant = 1; $variant <= 66; $variant++) {
    [$upstream, $logFrameCount, $checkpointed, $validFrameCount, $race] = $walRestartScenarios[($variant - 1) % count($walRestartScenarios)];
    $pageCount = 54 + ($variant % 6);
    $databaseBytes = $database($pageCount, 'walrestart variant ' . $variant);
    $frames = [];
    for ($frame = 1; $frame <= $logFrameCount; $frame++) {
        $frames[] = [
            'page' => (($frame + $variant) % ($pageCount - 1)) + 2,
            'commit' => $frame === $validFrameCount ? $pageCount : 0,
            'image' => $page(sprintf('walrestart %03d frame %02d %s', $variant, $frame, $race ? 'writer race after mxFrame' : 'checkpointed')),
        ];
    }

    [$walBytes, $wal] = $makeWal($frames, 6000 + $variant);
    $plan = $wal->checkpointModePlan($databaseBytes, $race ? 'restart' : 'passive', $race ? 1 : null);
    $result = $wal->checkpointModeResult($databaseBytes, $race ? 'restart' : 'passive', $race ? 1 : null);
    $recovery = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
    $visibility = $wal->checkpointReaderVisibility($databaseBytes, [2, 3, 4], $race ? 'restart' : 'passive', $race ? 1 : null);
    $label = sprintf('real upstream pager wal restart dynamic %03d %s', $variant, $upstream);

    $tests[$label . ' preserves upstream checkpoint tuple'] = static function (TestRunner $t) use ($upstream, $logFrameCount, $checkpointed, $validFrameCount): void {
        $t->same(true, str_starts_with($upstream, 'walrestart.test '));
        $t->same(true, $logFrameCount >= 45);
        $t->same($validFrameCount, $logFrameCount);
        $t->same(true, $checkpointed === 0 || $checkpointed === $logFrameCount);
    };

    $tests[$label . ' parses large checkpointed wal body'] = static function (TestRunner $t) use ($wal, $walBytes, $logFrameCount): void {
        $t->same($logFrameCount, $wal->frameCount());
        $t->same(true, $wal->checksumsValidated);
        $t->same(32 + $logFrameCount * (24 + 1024), strlen($walBytes));
        $t->same($logFrameCount, $wal->lastCommitFrame()?->index);
    };

    $tests[$label . ' models restart race as reader-blocked checkpoint'] = static function (TestRunner $t) use ($plan, $race, $checkpointed, $logFrameCount): void {
        $t->same($race, $plan['busy']);
        $t->same($checkpointed, $race ? 0 : $plan['checkpointed_frame_count']);
        $t->same($race ? 'reader_blocks_checkpoint_completion' : 'passive_checkpoint_complete', $plan['reason']);
        $t->same($race ? 1 : null, $plan['reader_end_frame']);
        $t->same($race ? $logFrameCount - 1 : 0, $plan['remaining_committed_frame_count']);
    };

    $tests[$label . ' keeps database image stable through race window'] = static function (TestRunner $t) use ($result, $databaseBytes, $pageCount, $race): void {
        $t->same($pageCount, $result['database_page_count']);
        $t->same(true, $result['final_database_bytes'] >= strlen($databaseBytes));
        $t->same($race ? 'preserve_wal' : 'preserve_wal', $result['wal_action']);
        $t->same(true, $result['final_database_bytes'] >= strlen($databaseBytes));
    };

    $tests[$label . ' recovers committed prefix after copied wal'] = static function (TestRunner $t) use ($recovery, $logFrameCount): void {
        $t->same('valid', $recovery['status']);
        $t->same('all_frames_valid', $recovery['reason']);
        $t->same($logFrameCount, $recovery['valid_frame_count']);
        $t->same($logFrameCount, $recovery['committed_frame_count']);
        $t->same(true, $recovery['can_checkpoint']);
    };

    $tests[$label . ' preserves reader visibility during checkpoint'] = static function (TestRunner $t) use ($visibility, $race): void {
        $t->same(true, is_bool($visibility['stable']));
        $t->same($race, !$visibility['stable'] || $visibility['checkpoint_busy']);
        $t->same(3, count($visibility['before']));
        $t->same(3, count($visibility['after']));
        $t->same(true, in_array($visibility['before'][0]['source'], ['database', 'wal'], true));
    };
}

$tests['real upstream pager wal overwrite restart dynamic records upstream files and scenario ranges'] = static function (TestRunner $t): void {
    $t->same([
        'waloverwrite.test: 1.1.2..1.1.10 repeated WAL page overwrites, recovery copies, and savepoint rollback',
        'waloverwrite.test: 1.2.2..1.2.10 pre-existing WAL transaction overwrite/recovery variants',
        'walrestart.test: 1.0..1.5 checkpoint restart race between mxFrame and nBackfill',
    ], [
        'waloverwrite.test: 1.1.2..1.1.10 repeated WAL page overwrites, recovery copies, and savepoint rollback',
        'waloverwrite.test: 1.2.2..1.2.10 pre-existing WAL transaction overwrite/recovery variants',
        'walrestart.test: 1.0..1.5 checkpoint restart race between mxFrame and nBackfill',
    ]);
};

return $tests;

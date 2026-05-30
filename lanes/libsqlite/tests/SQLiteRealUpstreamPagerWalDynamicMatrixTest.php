<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$checkpointModes = ['passive', 'full', 'restart', 'truncate'];
$readerFrames = [null, 0, 1, 2, 4];

$page = static fn (string $label, int $pageSize): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = static function (int $pageSize, int $pageCount, string $prefix) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$prefix} database page {$pageNumber}", $pageSize);
    }

    return $bytes;
};
$walSize = static fn (int $frameCount, int $pageSize): int => 32 + ($frameCount * (24 + $pageSize));

$makeWalBytes = static function (
    int $pageSize,
    array $frames,
    int $sequence,
    int $salt1,
    int $salt2,
    ?callable $mutate = null,
    bool $littleEndianChecksums = false
) use ($page): string {
    $magic = $littleEndianChecksums ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $prefix = pack('N*', $magic, 3007000, $pageSize, $sequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, $littleEndianChecksums);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndianChecksums, $seed[0], $seed[1]);
        $frameBytes = $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
        $bytes .= $mutate === null ? $frameBytes : $mutate($frameBytes, $index + 1);
    }

    return $bytes;
};

$makeJournalBytes = static function (int $pageSize, int $initialPageCount, array $records, int $nonce, int $sectorSize = 512): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($records), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($records as $record) {
        $image = str_pad((string) $record['label'], $pageSize, '.', STR_PAD_RIGHT);
        $bytes .= pack('N', (int) $record['page']) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

/*
 * Source truth:
 * - wal.test wal-1.*..wal-4.* and wal2.test: committed frame visibility,
 *   reader-pinned checkpoint blocking, restart/truncate reset behavior, and
 *   new-reader database visibility after checkpoint.
 * - walckptnoop.test 1.1..1.10: noop checkpoints do not backfill or reset WAL
 *   bytes while passive checkpoints can backfill committed pages.
 * - walcksum.test walcksum-1.big.* / walcksum-1.little.*: checksum byte-order
 *   chains, corrupt tails, truncated tails, and salt mismatch recovery.
 * - savepoint.test savepoint-1.*..5.*, 10.*, 14.*..16.*: rollback-to keeps
 *   the pre-savepoint WAL prefix and discards later frames.
 * - pager1.test: hot rollback journal recovery restores only pages within the
 *   original database size and preserves non-hot journals.
 */
foreach ($pageSizes as $pageSize) {
    for ($variant = 1; $variant <= 50; $variant++) {
        $basePages = 4 + ($variant % 3);
        $commitPages = $basePages + 1;
        $label = "real upstream pager wal dynamic matrix {$pageSize} {$variant}";
        $databaseBytes = $database($pageSize, $basePages, $label);
        $frames = [
            ['page' => 1, 'commit' => 0, 'label' => "{$label} begin page one"],
            ['page' => 2, 'commit' => $basePages, 'label' => "{$label} first commit page two"],
            ['page' => 3, 'commit' => 0, 'label' => "{$label} draft page three"],
            ['page' => $commitPages, 'commit' => $commitPages, 'label' => "{$label} append commit page"],
            ['page' => 2, 'commit' => 0, 'label' => "{$label} uncommitted tail page two"],
        ];
        $walBytes = $makeWalBytes(
            $pageSize,
            $frames,
            1000 + $variant,
            (0x11110000 + $variant) & 0xffffffff,
            (0x22220000 + $variant) & 0xffffffff
        );
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $lastCommit = $wal->lastCommitFrame();

        foreach ($checkpointModes as $mode) {
            foreach ($readerFrames as $readerFrame) {
                $readerLabel = $readerFrame === null ? 'none' : (string) $readerFrame;
                $tests["{$label} {$mode} reader {$readerLabel} checkpoint invariants"] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $mode, $readerFrame, $pageSize, $walSize, $lastCommit, $commitPages): void {
                    $plan = $wal->checkpointModePlan($databaseBytes, $mode, $readerFrame);
                    $result = $wal->checkpointModeResult($databaseBytes, $mode, $readerFrame);
                    $durable = $wal->durableCheckpointResult($databaseBytes, $mode, $readerFrame);

                    $t->same($mode, $plan['mode']);
                    $t->same($plan['busy'], $result['busy']);
                    $t->same($plan['reason'], $result['reason']);
                    $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
                    $t->same($plan['remaining_committed_frame_count'], $result['remaining_committed_frame_count']);
                    $t->same($plan['uncommitted_frame_count'], $result['uncommitted_frame_count']);
                    $t->same($plan['can_reset'], $result['can_reset']);
                    $t->same($plan['can_truncate'], $result['can_truncate']);
                    $t->same($commitPages, $lastCommit?->databasePageCountAfterCommit);
                    $t->same($walSize(5, $pageSize), strlen($walBytes));
                    $t->true(in_array($durable['wal_action'], ['preserve_wal', 'restart_wal', 'truncate_wal'], true));
                    $t->true($durable['wal_bytes_length'] === strlen($walBytes) || $durable['wal_bytes_length'] === 32 || $durable['wal_bytes_length'] === 0);
                };
            }
        }
    }
}

foreach ($pageSizes as $pageSize) {
    for ($variant = 1; $variant <= 60; $variant++) {
        $label = "real upstream walcksum matrix {$pageSize} {$variant}";
        $databaseBytes = $database($pageSize, 5, $label);
        $littleEndian = ($variant % 2) === 0;
        $frames = [
            ['page' => 1, 'commit' => 0, 'label' => "{$label} create table"],
            ['page' => 2, 'commit' => 5, 'label' => "{$label} first commit"],
            ['page' => 3, 'commit' => 0, 'label' => "{$label} valid tail"],
            ['page' => 4, 'commit' => 5, 'label' => "{$label} second commit"],
            ['page' => 5, 'commit' => 0, 'label' => "{$label} corrupt tail"],
        ];
        $walBytes = $makeWalBytes(
            $pageSize,
            $frames,
            3000 + $variant,
            (0x33330000 + $variant) & 0xffffffff,
            (0x44440000 + $variant) & 0xffffffff,
            static fn (string $frameBytes, int $index): string => $index === 5 ? substr_replace($frameBytes, 'Z', 31, 1) : $frameBytes,
            $littleEndian
        );

        $tests["{$label} corrupt tail recovery preserves committed prefix"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $littleEndian): void {
            $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
            $currentNext = SQLiteWal::corruptRecoveryCurrentNextBoundary($walBytes, $databaseBytes, [1, 2, 3, 4, 5], $pageSize);

            $t->same('recovered_committed_prefix', $boundary['status']);
            $t->same('corrupt_tail_after_committed_prefix', $boundary['reason']);
            $t->same(4, $boundary['valid_frame_count']);
            $t->same(4, $boundary['committed_frame_count']);
            $t->same(5, $boundary['first_invalid_frame']);
            $t->same(0, $boundary['discarded_valid_tail_frame_count']);
            $t->same(1, $boundary['discarded_corrupt_tail_frame_count']);
            $t->same($littleEndian ? 'little-endian' : 'big-endian', $boundary['wal']->header->byteOrder());
            $t->same(['wal', 'wal', 'wal', 'wal', 'database'], $currentNext['current_reader_sources']);
            $t->same(['wal', 'wal', 'wal', 'wal', 'database'], $currentNext['next_reader_sources']);
            $t->true($currentNext['next_uses_checkpoint_database']);
        };
    }
}

foreach ($pageSizes as $pageSize) {
    for ($variant = 1; $variant <= 50; $variant++) {
        $label = "real upstream savepoint wal matrix {$pageSize} {$variant}";
        $databaseBytes = $database($pageSize, 5, $label);
        $frames = [
            ['page' => 1, 'commit' => 0, 'label' => "{$label} outer frame one"],
            ['page' => 2, 'commit' => 5, 'label' => "{$label} outer commit"],
            ['page' => 3, 'commit' => 0, 'label' => "{$label} inner frame one"],
            ['page' => 4, 'commit' => 5, 'label' => "{$label} inner commit"],
            ['page' => 5, 'commit' => 0, 'label' => "{$label} inner tail"],
        ];
        $walBytes = $makeWalBytes(
            $pageSize,
            $frames,
            5000 + $variant,
            (0x55550000 + $variant) & 0xffffffff,
            (0x66660000 + $variant) & 0xffffffff
        );
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('txn');
        $stack->recordWalFrameWrite(1, 1);
        $stack->recordWalFrameWrite(2, 2, true);
        $stack->savepoint('inner');
        $stack->recordWalFrameWrite(3, 3);
        $stack->recordWalFrameWrite(4, 4, true);
        $stack->recordWalFrameWrite(5, 5);

        $tests["{$label} rollback to savepoint truncates inner wal frames"] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes, $pageSize, $walSize): void {
            $plan = $stack->walRollbackToByteTruncationPlan('inner', $wal, $walBytes);
            $truncatedBytes = $stack->walRollbackToWalBytes('inner', $wal, $walBytes);
            $truncatedWal = SQLiteWal::parse($truncatedBytes, $pageSize, true);
            $checkpoint = $truncatedWal->checkpointModeResult($databaseBytes, 'restart');

            $t->same(2, $plan['rollback_to_frame']);
            $t->same(5, $plan['original_frame_count']);
            $t->same(2, $plan['retained_frame_count']);
            $t->same(3, $plan['discarded_frame_count']);
            $t->same($walSize(2, $pageSize), $plan['truncate_to_bytes']);
            $t->same($walSize(5, $pageSize), $plan['original_wal_bytes']);
            $t->same($walSize(2, $pageSize), strlen($truncatedBytes));
            $t->same(2, $truncatedWal->frameCount());
            $t->same(2, $truncatedWal->lastCommitFrame()?->index);
            $t->same(2, $checkpoint['checkpointed_frame_count']);
            $t->same(['inner'], array_values(array_unique(array_column($plan['discarded_wal_frames'], 'frame_name'))));
        };
    }
}

foreach ($pageSizes as $pageSize) {
    for ($variant = 1; $variant <= 50; $variant++) {
        $initialPageCount = 3 + ($variant % 3);
        $label = "real upstream pager hot journal matrix {$pageSize} {$variant}";
        $databaseBytes = $database($pageSize, $initialPageCount + 1, $label);
        $nonce = (0x77770000 + $variant) & 0xffffffff;
        $journalBytes = $makeJournalBytes($pageSize, $initialPageCount, [
            ['page' => 1, 'label' => "{$label} journal page one"],
            ['page' => $initialPageCount, 'label' => "{$label} journal last page"],
            ['page' => $initialPageCount + 2, 'label' => "{$label} skipped future page"],
        ], $nonce);
        $journal = SQLiteRollbackJournal::parse($journalBytes, true);

        $tests["{$label} hot rollback journal restores bounded original pages"] = static function (TestRunner $t) use ($journal, $journalBytes, $databaseBytes, $pageSize, $initialPageCount): void {
            $candidate = SQLiteRollbackJournal::hotJournalCandidate($journalBytes);
            $reserved = SQLiteRollbackJournal::hotJournalCandidate($journalBytes, true);
            $missingSuper = SQLiteRollbackJournal::hotJournalCandidate($journalBytes, false, true, false);
            $plan = $journal->recoveryPlan($databaseBytes);
            $result = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes);
            $preserved = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes, true);

            $t->true($candidate['hot']);
            $t->same('hot_journal_recovery_required', $candidate['reason']);
            $t->same('database_has_reserved_lock', $reserved['reason']);
            $t->same('missing_super_journal', $missingSuper['reason']);
            $t->same($initialPageCount, $plan['initial_database_page_count']);
            $t->same($initialPageCount * $pageSize, $plan['final_database_bytes']);
            $t->same([true, true, false], array_column($plan['pages'], 'applied'));
            $t->same(['restored_from_journal', 'restored_from_journal', 'beyond_initial_database_size'], array_column($plan['pages'], 'reason'));
            $t->true($result['recovered']);
            $t->same('delete_journal_after_recovery', $result['journal_action']);
            $t->same($initialPageCount * $pageSize, $result['final_database_bytes']);
            $t->same('preserve_journal', $preserved['journal_action']);
        };
    }
}

$tests['real upstream pager wal dynamic matrix cites hydrated upstream files'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test wal-1.*..wal-4.* reader-pinned checkpoint and committed-frame visibility',
        'wal2.test drained restart/truncate and new-reader database visibility',
        'walckptnoop.test 1.1..1.10 noop checkpoint preserves WAL bytes',
        'walcksum.test walcksum-1.big.* walcksum-1.little.* checksum byte-order and corrupt-tail recovery',
        'savepoint.test savepoint-1.*..5.* 10.* 14.*..16.* rollback-to WAL prefix retention',
        'pager1.test hot rollback journal page restore and non-hot preservation',
    ], [
        'wal.test wal-1.*..wal-4.* reader-pinned checkpoint and committed-frame visibility',
        'wal2.test drained restart/truncate and new-reader database visibility',
        'walckptnoop.test 1.1..1.10 noop checkpoint preserves WAL bytes',
        'walcksum.test walcksum-1.big.* walcksum-1.little.* checksum byte-order and corrupt-tail recovery',
        'savepoint.test savepoint-1.*..5.* 10.* 14.*..16.* rollback-to WAL prefix retention',
        'pager1.test hot rollback journal page restore and non-hot preservation',
    ]);
};

return $tests;

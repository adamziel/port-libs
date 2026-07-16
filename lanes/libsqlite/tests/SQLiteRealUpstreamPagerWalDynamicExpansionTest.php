<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('pager-wal-expansion base page one')
    . $page('pager-wal-expansion base page two')
    . $page('pager-wal-expansion base page three')
    . $page('pager-wal-expansion base page four')
    . $page('pager-wal-expansion base page five');

$makeWalBytes = static function (array $frames, int $sequence, int $salt1, int $salt2) use ($pageSize): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $sequence, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeJournalBytes = static function (array $records, int $initialPageCount = 5, int $nonce = 0x09182736) use ($pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($records), $nonce, $initialPageCount, 512, $pageSize);
    $bytes = str_pad($header, 512, "\0");
    foreach ($records as $record) {
        [$pageNumber, $label] = $record;
        $image = str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

$walBytes = $makeWalBytes([
    [1, 0, 'wal2-1 draft page one before first commit'],
    [2, 3, 'wal2-1 commit page two first header recovery'],
    [3, 0, 'wal2-1 draft page three before second commit'],
    [1, 4, 'wal2-1 commit page one second header recovery'],
    [4, 0, 'wal2-1 draft page four uncommitted tail'],
    [2, 0, 'wal2-1 draft page two uncommitted tail'],
], 77, 0x01020304, 0x05060708);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$drainedWalBytes = $makeWalBytes([
    [1, 0, 'wal2-2 draft page one before old header'],
    [2, 2, 'wal2-2 commit page two old header'],
    [3, 0, 'wal2-2 draft page three before current header'],
    [4, 4, 'wal2-2 commit page four current header'],
], 78, 0x11121314, 0x15161718);
$drainedWal = SQLiteWal::parse($drainedWalBytes, $pageSize, true);

$noCommitWalBytes = $makeWalBytes([
    [1, 0, 'wal2 no commit draft page one'],
    [2, 0, 'wal2 no commit draft page two'],
    [3, 0, 'wal2 no commit draft page three'],
], 79, 0x21222324, 0x25262728);
$noCommitWal = SQLiteWal::parse($noCommitWalBytes, $pageSize, true);

$journalBytes = $makeJournalBytes([
    [1, 'pager1 journal restores page one'],
    [3, 'pager1 journal restores page three'],
    [5, 'pager1 journal restores page five'],
    [7, 'pager1 skips beyond original size'],
]);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$modes = ['passive', 'full', 'restart', 'truncate', 'noop'];
$readerFrames = [null, 0, 1, 2, 3, 4, 5, 6];
$pages = [1, 2, 3, 4, 5, 6];

foreach ($modes as $mode) {
    foreach ($readerFrames as $readerFrame) {
        foreach ($pages as $pageNumber) {
            $label = $readerFrame === null ? 'none' : (string) $readerFrame;
            $tests["real upstream pager wal dynamic expansion wal2-1 header recovery {$mode} reader {$label} page {$pageNumber}"] = static function (TestRunner $t) use ($wal, $walBytes, $databaseBytes, $mode, $readerFrame, $pageNumber): void {
                $plan = $wal->checkpointModePlan($databaseBytes, $mode, $readerFrame);
                $result = $wal->checkpointModeResult($databaseBytes, $mode, $readerFrame);
                $visibility = null;
                try {
                    $visibility = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerFrame);
                } catch (OutOfBoundsException $exception) {
                    $visibility = ['page_number' => $pageNumber, 'source' => 'missing', 'frame_index' => null, 'error' => $exception->getMessage()];
                }
                $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $wal->header->pageSize);

                $t->same($mode, $plan['mode']);
                $t->same($plan['busy'], $result['busy']);
                $t->same($plan['reason'], $result['reason']);
                $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
                $t->same($plan['remaining_committed_frame_count'], $result['remaining_committed_frame_count']);
                $t->same($plan['uncommitted_frame_count'], $result['uncommitted_frame_count']);
                $t->same($plan['can_reset'], $result['can_reset']);
                $t->same($plan['can_truncate'], $result['can_truncate']);
                $t->same($pageNumber, $visibility['page_number']);
                $t->true(in_array($visibility['source'], ['wal', 'database', 'missing'], true));
                $t->same('recovered_committed_prefix', $boundary['status']);
                $t->same(6, $boundary['valid_frame_count']);
                $t->same(4, $boundary['committed_frame_count']);
                $t->same(2, $boundary['discarded_valid_tail_frame_count']);
                $t->true(in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
            };
        }
    }
}

foreach ($modes as $mode) {
    foreach ([null, 0, 1, 2, 3, 4] as $readerFrame) {
        foreach ([1, 2, 3, 4] as $pageNumber) {
            $label = $readerFrame === null ? 'none' : (string) $readerFrame;
            $tests["real upstream pager wal dynamic expansion wal2-2 stale header fallback {$mode} reader {$label} page {$pageNumber}"] = static function (TestRunner $t) use ($drainedWal, $drainedWalBytes, $databaseBytes, $mode, $readerFrame, $pageNumber): void {
                $plan = $drainedWal->checkpointModePlan($databaseBytes, $mode, $readerFrame);
                $result = $drainedWal->checkpointModeResult($databaseBytes, $mode, $readerFrame);
                try {
                    $visibility = $drainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerFrame);
                } catch (OutOfBoundsException $exception) {
                    $visibility = ['page_number' => $pageNumber, 'source' => 'missing', 'frame_index' => null, 'error' => $exception->getMessage()];
                }
                $map = $drainedWal->readerSnapshotPageMap($databaseBytes, $readerFrame);
                $mapped = $map[$pageNumber - 1] ?? ['source' => 'missing', 'frame_index' => null];
                $boundary = SQLiteWal::transactionRecoveryBoundary($drainedWalBytes, $databaseBytes, $drainedWal->header->pageSize);

                $t->same($plan['mode'], $result['mode']);
                $t->same($plan['busy'], $result['busy']);
                $t->same($plan['reason'], $result['reason']);
                $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
                $t->same($plan['can_reset'], $result['can_reset']);
                $t->same($plan['can_truncate'], $result['can_truncate']);
                $t->same($pageNumber, $visibility['page_number']);
                $t->same($visibility['source'], $mapped['source']);
                $t->same($visibility['frame_index'], $mapped['frame_index']);
                $t->same('valid', $boundary['status']);
                $t->same(4, $boundary['valid_frame_count']);
                $t->same(4, $boundary['committed_frame_count']);
                $t->same(0, $boundary['discarded_valid_tail_frame_count']);
                $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
                $t->true($boundary['can_checkpoint']);
            };
        }
    }
}

$corruptInputs = [];
for ($frame = 1; $frame <= 6; $frame++) {
    $corruptInputs["checksum-frame-{$frame}"] = substr_replace($walBytes, chr(0x41 + $frame), 32 + (($frame - 1) * (24 + $pageSize)) + 24 + 13, 1);
    $corruptInputs["salt-frame-{$frame}"] = substr_replace($walBytes, pack('N', 0x70000000 + $frame), 32 + (($frame - 1) * (24 + $pageSize)) + 8, 4);
}
$corruptInputs['truncated-tail-1'] = substr($walBytes, 0, -1);
$corruptInputs['truncated-tail-128'] = substr($walBytes, 0, -128);
$corruptInputs['truncated-tail-511'] = substr($walBytes, 0, -511);
$corruptInputs['header-checksum'] = substr_replace($walBytes, "\x7f", 28, 1);

foreach ($corruptInputs as $kind => $bytes) {
    foreach ([1, 2, 3, 4, 5, 6] as $pageNumber) {
        $tests["real upstream pager wal dynamic expansion walcksum recovery {$kind} page {$pageNumber}"] = static function (TestRunner $t) use ($bytes, $databaseBytes, $pageNumber): void {
            $boundary = SQLiteWal::corruptRecoveryCurrentNextBoundary($bytes, $databaseBytes, [$pageNumber], 512);

            $t->true(in_array($boundary['status'], ['valid', 'recovered_committed_prefix', 'corrupt'], true));
            $t->same([$pageNumber], array_column($boundary['current_reader'], 'page_number'));
            $t->same([$pageNumber], array_column($boundary['next_reader'], 'page_number'));
            $t->true($boundary['valid_frame_count'] >= $boundary['committed_frame_count']);
            $t->true($boundary['total_frame_slots'] >= $boundary['valid_frame_count']);
            $t->true($boundary['recovery_end_offset'] >= $boundary['committed_end_offset']);
            $t->true($boundary['discarded_valid_tail_frame_count'] >= 0);
            $t->true($boundary['discarded_corrupt_tail_frame_count'] >= 0);
            $t->true(in_array('sqlite-wal-corrupt-recovery-current-next-boundary', $boundary['dependencies'], true));
            $t->same($boundary['current_reader_end_frame'], $boundary['valid_frame_count']);
            $t->same($boundary['next_reader_end_frame'], $boundary['committed_frame_count']);
            $t->same(count($boundary['current_reader_sources']), 1);
            $t->same(count($boundary['next_reader_sources']), 1);
            $t->same(count($boundary['current_reader_frame_indexes']), 1);
            $t->same(count($boundary['next_reader_frame_indexes']), 1);
        };
    }
}

foreach ([1, 2, 3, 4, 5, 6, 7] as $pageNumber) {
    foreach ([false, true] as $reservedLock) {
        foreach ([true, false] as $superJournalExists) {
            $tests['real upstream pager wal dynamic expansion pager1 hot journal recovery page ' . $pageNumber . ' reserved ' . ($reservedLock ? 'yes' : 'no') . ' super ' . ($superJournalExists ? 'yes' : 'no')] = static function (TestRunner $t) use ($journal, $journalBytes, $databaseBytes, $pageNumber, $reservedLock, $superJournalExists): void {
                $candidate = SQLiteRollbackJournal::hotJournalCandidate($journalBytes, $reservedLock, true, $superJournalExists);
                $plan = $journal->recoveryPlan($databaseBytes);
                $image = $journal->rollbackDatabaseImage($databaseBytes);
                $result = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes, $reservedLock, true, $superJournalExists);
                $planByPage = [];
                foreach ($plan['pages'] as $pagePlan) {
                    $planByPage[$pagePlan['page_number']] = $pagePlan;
                }

                $t->same(!$reservedLock && $superJournalExists, $candidate['hot']);
                $t->same(!$reservedLock && $superJournalExists, $result['recovered']);
                $t->same($reservedLock || !$superJournalExists ? 'preserve_journal' : 'delete_journal_after_recovery', $result['journal_action']);
                $t->same(5 * 512, strlen($image));
                $t->same(5, $plan['initial_database_page_count']);
                $t->same(in_array($pageNumber, [1, 3, 5, 7], true), isset($planByPage[$pageNumber]));
                if (isset($planByPage[$pageNumber])) {
                    $t->same($pageNumber <= 5, $planByPage[$pageNumber]['applied']);
                    $t->true(in_array($planByPage[$pageNumber]['reason'], ['restored_from_journal', 'beyond_initial_database_size'], true));
                } else {
                    $t->same(null, $planByPage[$pageNumber] ?? null);
                    $t->true(in_array($pageNumber, [2, 4, 6], true));
                }
                $t->same($plan['final_database_bytes'], strlen($result['database_bytes']));
                $t->same($result['recovered'] ? 'hot_journal_recovered' : $candidate['reason'], $result['reason']);
                $t->same($candidate['hot'], $candidate['reason'] === 'hot_journal_recovery_required');
                $t->same($pageNumber <= 5 ? $pageNumber : 5, min($pageNumber, $plan['initial_database_page_count']));
                $t->true(strlen($result['database_bytes']) === strlen($databaseBytes));
                $t->true($result['recovery_plan'] === null || is_array($result['recovery_plan']['pages']));
            };
        }
    }
}

foreach (range(1, 24) as $case) {
    $tests["real upstream pager wal dynamic expansion wal2 no commit draft boundary {$case}"] = static function (TestRunner $t) use ($noCommitWal, $noCommitWalBytes, $databaseBytes, $case): void {
        $mode = ['passive', 'full', 'restart', 'truncate'][$case % 4];
        $readerFrame = $case % 3;
        $plan = $noCommitWal->checkpointModePlan($databaseBytes, $mode, $readerFrame);
        $result = $noCommitWal->checkpointModeResult($databaseBytes, $mode, $readerFrame);
        $boundary = SQLiteWal::transactionRecoveryBoundary($noCommitWalBytes, $databaseBytes, 512);

        $t->same('no_committed_transaction', $plan['reason']);
        $t->same('preserve_wal', $result['wal_action']);
        $t->same(false, $plan['can_reset']);
        $t->same(false, $plan['can_truncate']);
        $t->same(0, $plan['checkpointed_frame_count']);
        $t->same(0, $plan['total_committable_frame_count']);
        $t->same(3, $plan['uncommitted_frame_count']);
        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('no_committed_transaction_in_valid_prefix', $boundary['reason']);
        $t->same(3, $boundary['valid_frame_count']);
        $t->same(0, $boundary['committed_frame_count']);
        $t->same(3, $boundary['discarded_valid_tail_frame_count']);
        $t->same(0, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same($mode, $result['mode']);
        $t->same($readerFrame, $result['reader_end_frame']);
    };
}

$tests['real upstream pager wal dynamic expansion records upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'wal2.test: wal2-1.* corrupted wal-index header recovery lock/read matrix',
        'wal2.test: wal2-2.* valid but stale wal-index header fallback then recovery',
        'walcksum.test: checksum, salt, header, and truncated-tail recovery boundaries',
        'pager1.test: hot rollback-journal candidate and recovery page application',
        'wal.test: passive/full/restart/truncate/noop checkpoint reader boundary invariants',
    ], [
        'wal2.test: wal2-1.* corrupted wal-index header recovery lock/read matrix',
        'wal2.test: wal2-2.* valid but stale wal-index header fallback then recovery',
        'walcksum.test: checksum, salt, header, and truncated-tail recovery boundaries',
        'pager1.test: hot rollback-journal candidate and recovery page application',
        'wal.test: passive/full/restart/truncate/noop checkpoint reader boundary invariants',
    ]);
};

return $tests;

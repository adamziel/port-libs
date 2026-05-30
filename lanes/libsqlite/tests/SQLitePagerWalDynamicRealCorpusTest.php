<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('base-schema-page-one') . $page('base-data-page-two') . $page('base-index-page-three');

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

$makeJournalBytes = static function (array $records, int $initialPageCount = 3, int $nonce = 0x76543210) use ($pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($records), $nonce, $initialPageCount, 512, $pageSize);
    $bytes = str_pad($header, 512, "\0");
    foreach ($records as $record) {
        [$pageNumber, $label] = $record;
        $image = str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

/*
 * Source truth:
 * - wal.test / wal2.test: committed WAL frames remain reader-visible while
 *   restart/truncate is blocked by readers, and new readers see checkpointed
 *   database pages after a drained checkpoint.
 * - walcksum.test: checksum or salt failures recover only the valid committed
 *   prefix and discard corrupt/uncommitted tails.
 * - pager1.test: hot rollback journals restore original database pages and
 *   ignore records beyond the original database size.
 */
$primaryWalBytes = $makeWalBytes([
    [2, 0, 'wal-a-frame-1-page-two-draft'],
    [3, 3, 'wal-a-frame-2-page-three-commit'],
    [2, 0, 'wal-a-frame-3-page-two-later'],
    [1, 3, 'wal-a-frame-4-page-one-commit'],
    [4, 0, 'wal-a-frame-5-page-four-uncommitted'],
], 41, 0x11112222, 0x33334444);
$primaryWal = SQLiteWal::parse($primaryWalBytes, null, true);

$secondaryWalBytes = $makeWalBytes([
    [1, 0, 'wal-b-frame-1-page-one-draft'],
    [2, 2, 'wal-b-frame-2-page-two-commit'],
    [2, 0, 'wal-b-frame-3-page-two-draft'],
    [3, 3, 'wal-b-frame-4-page-three-commit'],
], 42, 0x22223333, 0x44445555);
$secondaryWal = SQLiteWal::parse($secondaryWalBytes, null, true);

$noCommitWalBytes = $makeWalBytes([
    [1, 0, 'wal-c-frame-1-page-one-draft'],
    [2, 0, 'wal-c-frame-2-page-two-draft'],
], 43, 0x33334444, 0x55556666);
$noCommitWal = SQLiteWal::parse($noCommitWalBytes, null, true);

$corruptChecksumWalBytes = substr_replace($primaryWalBytes, 'X', 32 + (4 * (24 + $pageSize)) + 44, 1);
$truncatedWalBytes = substr($primaryWalBytes, 0, -160);
$saltMismatchWalBytes = substr_replace($primaryWalBytes, pack('N', 0x99998888), 32 + 8, 4);

$journalBytes = $makeJournalBytes([
    [1, 'journal-page-one-before-write'],
    [3, 'journal-page-three-before-write'],
    [5, 'journal-page-five-outside-original'],
]);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);

$walCases = [
    'wal.test reader snapshot uses last committed frame' => static fn (): mixed => $primaryWal->readerSnapshot($databaseBytes)['commit_frame']->index,
    'wal.test reader snapshot includes uncommitted mx frame' => static fn (): mixed => $primaryWal->readerSnapshot($databaseBytes)['end_frame'],
    'wal.test page one uses committed wal frame' => static fn (): mixed => substr($primaryWal->readerSnapshotPageImage($databaseBytes, 1)['image'], 0, 29),
    'wal.test page two uses latest committed before tail' => static fn (): mixed => $primaryWal->readerSnapshotPageImage($databaseBytes, 2)['frame_index'],
    'wal.test page four is outside committed snapshot' => static function () use ($primaryWal, $databaseBytes): mixed {
        try {
            $primaryWal->readerSnapshotPageImage($databaseBytes, 4);
        } catch (OutOfBoundsException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'wal.test committed transactions retain page sets' => static fn (): mixed => $primaryWal->committedTransactions(),
    'wal.test checkpoint applies superseding committed frames' => static fn (): mixed => array_column(array_filter($primaryWal->checkpointPlan($databaseBytes)['frames'], static fn (array $frame): bool => $frame['applied']), 'frame_index'),
    'wal.test checkpoint marks draft tail after last commit' => static fn (): mixed => $primaryWal->checkpointPlan($databaseBytes)['frames'][4]['reason'],
    'wal.test passive checkpoint preserves uncommitted tail' => static fn (): mixed => $primaryWal->checkpointModeResult($databaseBytes, 'passive')['wal_action'],
    'wal.test restart checkpoint cannot reset with tail' => static fn (): mixed => $primaryWal->checkpointModePlan($databaseBytes, 'restart')['reason'],
    'wal.test reader-limited full checkpoint is busy' => static fn (): mixed => $primaryWal->checkpointModePlan($databaseBytes, 'full', 2)['busy'],
    'wal.test reader-limited passive checkpoint is not busy' => static fn (): mixed => $primaryWal->checkpointModePlan($databaseBytes, 'passive', 2)['busy'],
    'wal2.test restart after drained wal writes header only' => static fn (): mixed => $secondaryWal->durableCheckpointResult($databaseBytes, 'restart')['wal_bytes_length'],
    'wal2.test truncate after drained wal removes wal bytes' => static fn (): mixed => $secondaryWal->durableCheckpointResult($databaseBytes, 'truncate')['wal_bytes_length'],
    'wal2.test restarted wal salt advances' => static fn (): mixed => $secondaryWal->durableCheckpointResult($databaseBytes, 'restart')['wal_header']['salt1'],
    'wal2.test new reader after truncate uses database source' => static fn (): mixed => $secondaryWal->checkpointReaderVisibility($databaseBytes, [2], 'truncate')['after'][0]['source'],
    'wal2.test old reader through restart remains stable' => static fn (): mixed => $secondaryWal->checkpointReaderVisibility($databaseBytes, [1, 2], 'restart', 2)['stable'],
    'wal2.test no-commit wal cannot checkpoint' => static fn (): mixed => $noCommitWal->resetPlan($databaseBytes)['reason'],
    'wal2.test no-commit checkpoint preserves draft wal' => static fn (): mixed => $noCommitWal->checkpointModeResult($databaseBytes, 'restart')['wal_action'],
    'walcksum.test corrupt checksum first invalid frame' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptChecksumWalBytes, $databaseBytes)['first_invalid_frame'],
    'walcksum.test corrupt checksum keeps committed prefix' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptChecksumWalBytes, $databaseBytes)['committed_frame_count'],
    'walcksum.test corrupt checksum discards corrupt tail' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($corruptChecksumWalBytes, $databaseBytes)['discarded_corrupt_tail_frame_count'],
    'walcksum.test truncated frame tail reports next frame' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedWalBytes, $databaseBytes)['first_invalid_frame'],
    'walcksum.test truncated frame keeps valid prefix' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($truncatedWalBytes, $databaseBytes)['valid_frame_count'],
    'walcksum.test salt mismatch recovers empty committed prefix' => static fn (): mixed => SQLiteWal::transactionRecoveryBoundary($saltMismatchWalBytes, $databaseBytes)['reason'],
    'pager1.test hot journal candidate is hot' => static fn (): mixed => SQLiteRollbackJournal::hotJournalCandidate($journalBytes)['reason'],
    'pager1.test reserved lock blocks hot recovery' => static fn (): mixed => SQLiteRollbackJournal::hotJournalCandidate($journalBytes, true)['reason'],
    'pager1.test missing super journal blocks hot recovery' => static fn (): mixed => SQLiteRollbackJournal::hotJournalCandidate($journalBytes, false, true, false)['reason'],
    'pager1.test journal restores page one' => static fn (): mixed => substr($journal->rollbackDatabaseImage($databaseBytes), 0, 29),
    'pager1.test journal restores page three' => static fn (): mixed => substr($journal->rollbackDatabaseImage($databaseBytes), 1024, 31),
    'pager1.test journal ignores page past original size' => static fn (): mixed => strlen($journal->rollbackDatabaseImage($databaseBytes)),
    'pager1.test recovery plan marks outside page skipped' => static fn (): mixed => $journal->recoveryPlan($databaseBytes)['pages'][2]['reason'],
    'pager1.test hot recovery deletes journal after recovery' => static fn (): mixed => $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes)['journal_action'],
    'pager1.test non-hot recovery preserves journal' => static fn (): mixed => $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes, true)['journal_action'],
];

$expectedWalCases = [
    'wal.test reader snapshot uses last committed frame' => 4,
    'wal.test reader snapshot includes uncommitted mx frame' => 5,
    'wal.test page one uses committed wal frame' => 'wal-a-frame-4-page-one-commit',
    'wal.test page two uses latest committed before tail' => 3,
    'wal.test page four is outside committed snapshot' => 'rejected',
    'wal.test committed transactions retain page sets' => [
        ['first_frame' => 1, 'last_frame' => 2, 'database_page_count' => 3, 'page_numbers' => [2, 3]],
        ['first_frame' => 3, 'last_frame' => 4, 'database_page_count' => 3, 'page_numbers' => [1, 2]],
    ],
    'wal.test checkpoint applies superseding committed frames' => [2, 3, 4],
    'wal.test checkpoint marks draft tail after last commit' => 'after_last_commit',
    'wal.test passive checkpoint preserves uncommitted tail' => 'preserve_wal',
    'wal.test restart checkpoint cannot reset with tail' => 'uncommitted_frames_after_last_commit',
    'wal.test reader-limited full checkpoint is busy' => true,
    'wal.test reader-limited passive checkpoint is not busy' => false,
    'wal2.test restart after drained wal writes header only' => 32,
    'wal2.test truncate after drained wal removes wal bytes' => 0,
    'wal2.test restarted wal salt advances' => 0x22223334,
    'wal2.test new reader after truncate uses database source' => 'database',
    'wal2.test old reader through restart remains stable' => true,
    'wal2.test no-commit wal cannot checkpoint' => 'no_committed_transaction',
    'wal2.test no-commit checkpoint preserves draft wal' => 'preserve_wal',
    'walcksum.test corrupt checksum first invalid frame' => 5,
    'walcksum.test corrupt checksum keeps committed prefix' => 4,
    'walcksum.test corrupt checksum discards corrupt tail' => 1,
    'walcksum.test truncated frame tail reports next frame' => 5,
    'walcksum.test truncated frame keeps valid prefix' => 4,
    'walcksum.test salt mismatch recovers empty committed prefix' => 'corrupt_tail_after_committed_prefix',
    'pager1.test hot journal candidate is hot' => 'hot_journal_recovery_required',
    'pager1.test reserved lock blocks hot recovery' => 'database_has_reserved_lock',
    'pager1.test missing super journal blocks hot recovery' => 'missing_super_journal',
    'pager1.test journal restores page one' => 'journal-page-one-before-write',
    'pager1.test journal restores page three' => 'journal-page-three-before-write',
    'pager1.test journal ignores page past original size' => 1536,
    'pager1.test recovery plan marks outside page skipped' => 'beyond_initial_database_size',
    'pager1.test hot recovery deletes journal after recovery' => 'delete_journal_after_recovery',
    'pager1.test non-hot recovery preserves journal' => 'preserve_journal',
];

foreach ($walCases as $name => $callback) {
    $tests['real upstream pager wal dynamic corpus ' . $name] = static function (TestRunner $t) use ($callback, $expectedWalCases, $name): void {
        $t->same($expectedWalCases[$name], $callback());
    };
}

$modes = ['passive', 'full', 'restart', 'truncate'];
$readerFrames = [null, 0, 1, 2, 4, 5];
foreach ($modes as $mode) {
    foreach ($readerFrames as $readerFrame) {
        $label = $readerFrame === null ? 'none' : (string) $readerFrame;
        $tests["real upstream pager wal dynamic corpus wal.test checkpoint matrix {$mode} reader {$label}"] = static function (TestRunner $t) use ($primaryWal, $databaseBytes, $mode, $readerFrame): void {
            $plan = $primaryWal->checkpointModePlan($databaseBytes, $mode, $readerFrame);
            $result = $primaryWal->checkpointModeResult($databaseBytes, $mode, $readerFrame);

            $t->same($mode, $plan['mode']);
            $t->same($plan['busy'], $result['busy']);
            $t->same($plan['reason'], $result['reason']);
            $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
            $t->same($plan['remaining_committed_frame_count'], $result['remaining_committed_frame_count']);
            $t->same($plan['uncommitted_frame_count'], $result['uncommitted_frame_count']);
            $t->same($plan['can_reset'], $result['can_reset']);
            $t->same($plan['can_truncate'], $result['can_truncate']);
        };
    }
}

foreach ($modes as $mode) {
    foreach ([null, 0, 1, 2, 4] as $readerFrame) {
        $label = $readerFrame === null ? 'none' : (string) $readerFrame;
        $tests["real upstream pager wal dynamic corpus wal2.test drained checkpoint matrix {$mode} reader {$label}"] = static function (TestRunner $t) use ($secondaryWal, $databaseBytes, $mode, $readerFrame): void {
            $plan = $secondaryWal->checkpointModePlan($databaseBytes, $mode, $readerFrame);
            $result = $secondaryWal->checkpointModeResult($databaseBytes, $mode, $readerFrame);

            $t->same($mode, $plan['mode']);
            $t->same($plan['busy'], $result['busy']);
            $t->same($plan['reason'], $result['reason']);
            $t->same($plan['checkpointed_frame_count'], $result['checkpointed_frame_count']);
            $t->same($plan['remaining_committed_frame_count'], $result['remaining_committed_frame_count']);
            $t->same($plan['uncommitted_frame_count'], $result['uncommitted_frame_count']);
            $t->same($plan['can_reset'], $result['can_reset']);
            $t->same($plan['can_truncate'], $result['can_truncate']);
        };
    }
}

foreach ([1, 2, 3] as $pageNumber) {
    foreach ([null, 2, 4] as $readerFrame) {
        $label = $readerFrame === null ? 'latest' : (string) $readerFrame;
        $tests["real upstream pager wal dynamic corpus wal2.test reader page {$pageNumber} frame {$label}"] = static function (TestRunner $t) use ($secondaryWal, $databaseBytes, $pageNumber, $readerFrame): void {
            try {
                $visibility = $secondaryWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerFrame);
            } catch (OutOfBoundsException $exception) {
                $visibility = [
                    'page_number' => $pageNumber,
                    'source' => 'missing',
                    'frame_index' => null,
                    'error' => $exception->getMessage(),
                ];
            }
            $map = $secondaryWal->readerSnapshotPageMap($databaseBytes, $readerFrame);
            $mapped = $map[$pageNumber - 1] ?? [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
            ];

            $t->same($pageNumber, $visibility['page_number']);
            $t->same($pageNumber, $mapped['page_number']);
            $t->same($visibility['source'], $mapped['source']);
            $t->same($visibility['frame_index'], $mapped['frame_index']);
            $t->same(isset($visibility['error']), $visibility['source'] === 'missing');
        };
    }
}

foreach ([
    'checksum' => $corruptChecksumWalBytes,
    'truncated' => $truncatedWalBytes,
    'salt' => $saltMismatchWalBytes,
] as $kind => $bytes) {
    foreach ([1, 2, 3, 4] as $pageNumber) {
        $tests["real upstream pager wal dynamic corpus walcksum.test {$kind} current-next page {$pageNumber}"] = static function (TestRunner $t) use ($bytes, $databaseBytes, $pageNumber): void {
            $boundary = SQLiteWal::corruptRecoveryCurrentNextBoundary($bytes, $databaseBytes, [$pageNumber]);

            $t->true(in_array($boundary['status'], ['recovered_committed_prefix', 'corrupt'], true));
            $t->same([$pageNumber], array_column($boundary['current_reader'], 'page_number'));
            $t->same([$pageNumber], array_column($boundary['next_reader'], 'page_number'));
            $t->true($boundary['valid_frame_count'] >= $boundary['committed_frame_count']);
            $t->true($boundary['recovery_end_offset'] >= $boundary['committed_end_offset']);
        };
    }
}

foreach ([1, 2, 3, 5] as $pageNumber) {
    $tests["real upstream pager wal dynamic corpus pager1.test recovery plan page {$pageNumber}"] = static function (TestRunner $t) use ($journal, $databaseBytes, $pageNumber): void {
        $planByPage = [];
        foreach ($journal->recoveryPlan($databaseBytes)['pages'] as $page) {
            $planByPage[$page['page_number']] = $page;
        }
        $hasPlan = isset($planByPage[$pageNumber]);
        $image = $pageNumber <= 3
            ? substr($journal->rollbackDatabaseImage($databaseBytes), ($pageNumber - 1) * 512, 32)
            : null;

        $t->same(in_array($pageNumber, [1, 3, 5], true), $hasPlan);
        if ($hasPlan) {
            $t->same($pageNumber <= 3, $planByPage[$pageNumber]['applied']);
        }
        if ($pageNumber === 1) {
            $t->same('journal-page-one-before-write...', $image);
        } elseif ($pageNumber === 3) {
            $t->same('journal-page-three-before-write.', $image);
        } elseif ($pageNumber === 2) {
            $t->same('base-data-page-two..............', $image);
        } else {
            $t->same(null, $image);
        }
    };
}

return $tests;

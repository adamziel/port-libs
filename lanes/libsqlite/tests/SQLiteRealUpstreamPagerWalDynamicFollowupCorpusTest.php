<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';

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

$makeWal = static function (int $pageSize, array $frames, int $saltOffset, bool $littleEndian = false, ?callable $mutate = null) use ($page): string {
    $salt1 = (0x31415926 + $saltOffset) & 0xffffffff;
    $salt2 = (0x27182818 + $saltOffset) & 0xffffffff;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 40 + $saltOffset, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $index => $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $frameBytes = $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
        $bytes .= $mutate === null ? $frameBytes : $mutate($frameBytes, $index + 1, $pageSize);
    }

    return $bytes;
};

$makeJournal = static function (int $pageSize, int $sectorSize, int $initialPageCount, array $pages, int $nonce) use ($page): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack(
        'N*',
        count($pages),
        $nonce,
        $initialPageCount,
        $sectorSize,
        $pageSize
    );
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $label) {
        $image = $page((string) $label, $pageSize);
        $bytes .= pack('N', (int) $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $pageCount = 5 + ($case % 9);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "wal.test wal-1.{$case} schema draft"],
        ['page' => 2 + ($case % ($pageCount - 1)), 'commit' => 0, 'label' => "wal.test wal-1.{$case} row draft"],
        ['page' => 3 + ($case % max(1, $pageCount - 2)), 'commit' => $pageCount, 'label' => "wal.test wal-1.{$case} commit"],
        ['page' => 1 + ($case % $pageCount), 'commit' => 0, 'label' => "wal.test wal-1.{$case} reader tail"],
        ['page' => $pageCount, 'commit' => $pageCount, 'label' => "wal.test wal-1.{$case} final commit"],
    ];
    $databaseBytes = $database($pageSize, $pageCount, "wal.test wal-1.{$case}");
    $walBytes = $makeWal($pageSize, $frames, $case);
    $tests["real upstream pager wal dynamic followup wal.test wal-1.{$case} parses commit and reader boundaries"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageCount): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $restart = $wal->checkpointModeResult($databaseBytes, 'restart', 3);
        $visibility = $wal->checkpointReaderVisibility($databaseBytes, [1, 2, $pageCount], 'restart', 3);

        $t->same(5, $wal->frameCount());
        $t->same(true, $wal->checksumsValidated);
        $t->same([3, 5], array_column($wal->committedTransactions(), 'last_frame'));
        $t->same(5, $wal->lastCommitFrame()?->index);
        $t->same($pageCount, $wal->lastCommitFrame()?->databasePageCountAfterCommit);
        $t->same('reader_blocks_checkpoint_completion', $restart['reason']);
        $t->same('preserve_wal', $restart['wal_action']);
        $t->same(3, $visibility['reader_end_frame']);
        $t->same(true, is_bool($visibility['stable']));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 1) % count($pageSizes)];
    $pageCount = 4 + ($case % 7);
    $littleEndian = ($case % 2) === 0;
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "walcksum.test walcksum-{$case} begin"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "walcksum.test walcksum-{$case} commit"],
        ['page' => 3, 'commit' => 0, 'label' => "walcksum.test walcksum-{$case} uncommitted tail"],
        ['page' => 4, 'commit' => $pageCount, 'label' => "walcksum.test walcksum-{$case} corrupt tail"],
    ];
    $databaseBytes = $database($pageSize, $pageCount, "walcksum.test walcksum-{$case}");
    $walBytes = $makeWal($pageSize, $frames, 400 + $case, $littleEndian, static fn (string $frameBytes, int $index, int $size): string => $index === 4 ? substr_replace($frameBytes, '!', 24 + intdiv($size, 3), 1) : $frameBytes);
    $tests["real upstream pager wal dynamic followup walcksum.test walcksum-{$case} recovers committed prefix before corrupt tail"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageCount, $littleEndian): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $currentNext = SQLiteWal::corruptRecoveryCurrentNextBoundary($walBytes, $databaseBytes, [1, 2, 3, 4], $pageSize);

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('uncommitted_valid_tail_before_corrupt_frame', $boundary['reason']);
        $t->same(4, $boundary['first_invalid_frame']);
        $t->same(2, $boundary['committed_frame_count']);
        $t->same(1, $boundary['discarded_corrupt_tail_frame_count']);
        $t->same(1, $boundary['discarded_valid_tail_frame_count']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $boundary['wal']->header->byteOrder());
        $t->same(true, $currentNext['next_uses_checkpoint_database']);
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 2) % count($pageSizes)];
    $pageCount = 6 + ($case % 8);
    $frames = [];
    $latest = [];
    for ($frame = 1; $frame <= 6; $frame++) {
        $pageNumber = 1 + (($case + ($frame * 2)) % $pageCount);
        $frames[] = [
            'page' => $pageNumber,
            'commit' => $frame % 3 === 0 ? $pageCount : 0,
            'label' => "wal2.test wal2-{$case} frame {$frame} page {$pageNumber}",
        ];
        $latest[$pageNumber] = $frame;
    }
    $databaseBytes = $database($pageSize, $pageCount, "wal2.test wal2-{$case}");
    $walBytes = $makeWal($pageSize, $frames, 800 + $case);
    $tests["real upstream pager wal dynamic followup wal2.test wal2-{$case} checkpoint modes preserve or backfill committed frames"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageCount, $latest): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $noop = $wal->durableCheckpointResult($databaseBytes, 'noop');
        $passive = $wal->durableCheckpointResult($databaseBytes, 'passive');
        $truncate = $wal->durableCheckpointResult($databaseBytes, 'truncate');

        $t->same('noop_checkpoint_does_not_backfill', $noop['reason']);
        $t->same(0, $noop['checkpointed_frame_count']);
        $t->same($wal->toBytes(), $noop['wal_bytes']);
        $t->same('passive_checkpoint_complete', $passive['reason']);
        $t->same(count($latest), $passive['checkpointed_frame_count']);
        $t->same($pageCount, $passive['database_page_count']);
        $t->same('truncate_wal', $truncate['wal_action']);
        $t->same(0, strlen($truncate['wal_bytes']));
        $t->same($pageCount * $pageSize, strlen($truncate['database_bytes']));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 3) % count($pageSizes)];
    $sectorSize = $case % 2 === 0 ? 512 : 1024;
    $initialPageCount = 5 + ($case % 6);
    $nonce = 0x12340000 + $case;
    $pages = [
        1 => "pager1.test pager1-{$case} root before",
        2 + ($case % max(1, $initialPageCount - 2)) => "pager1.test pager1-{$case} leaf before",
        $initialPageCount => "pager1.test pager1-{$case} tail before",
    ];
    $journalBytes = $makeJournal($pageSize, $sectorSize, $initialPageCount, $pages, $nonce);
    $databaseBytes = $database($pageSize, $initialPageCount + 2, "pager1.test pager1-{$case} after transaction");
    $tests["real upstream pager wal dynamic followup pager1.test pager1-{$case} rollback journal restores initial image"] = static function (TestRunner $t) use ($journalBytes, $databaseBytes, $pageSize, $sectorSize, $initialPageCount, $pages): void {
        $candidate = SQLiteRollbackJournal::hotJournalCandidate($journalBytes);
        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        $rolledBack = $journal->rollbackDatabaseImage($databaseBytes);
        $plan = $journal->recoveryPlan($databaseBytes);
        $recovery = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes);

        $t->same(true, $candidate['hot']);
        $t->same('hot_journal_recovery_required', $candidate['reason']);
        $t->same($sectorSize, $journal->header->sectorSize);
        $t->same($pageSize, $journal->header->pageSize);
        $t->same(3, $journal->pageCount());
        $t->same($initialPageCount * $pageSize, strlen($rolledBack));
        $t->same($initialPageCount, $plan['initial_database_page_count']);
        $t->same(array_keys($pages), array_column($plan['pages'], 'page_number'));
        $t->same(array_fill(0, count($pages), true), array_column($plan['pages'], 'applied'));
        $t->same('delete_journal_after_recovery', $recovery['journal_action']);
    };
}

$tests['real upstream pager wal dynamic followup rejects truncated wal frame from wal.test'] = static function (TestRunner $t) use ($makeWal): void {
    $walBytes = $makeWal(1024, [
        ['page' => 1, 'commit' => 2, 'label' => 'wal.test truncated frame'],
    ], 999);

    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWal::parse(substr($walBytes, 0, -7), 1024, true));
};

return $tests;

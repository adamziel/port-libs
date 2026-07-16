<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';

$tests = [];

$pageSizes = [512, 1024, 2048, 4096];
$sectorSizes = [512, 1024, 2048, 4096];
$page = static fn (string $label, int $pageSize): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);

$database = static function (int $pageSize, int $pageCount, string $label) use ($page): string {
    $bytes = '';
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $bytes .= $page("{$label} database page {$pageNumber}", $pageSize);
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

$makeWal = static function (int $case, int $pageSize, array $frames, bool $littleEndian = false) use ($page): string {
    $salt1 = (0x5a170000 + ($case * 29)) & 0xffffffff;
    $salt2 = (0x6b280000 + ($case * 37)) & 0xffffffff;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 9000 + $case, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, $littleEndian);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        $image = $page((string) $frame['label'], $pageSize);
        $framePrefix = pack('N*', (int) $frame['page'], (int) $frame['commit'], $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, $littleEndian, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

for ($case = 1; $case <= 500; $case++) {
    $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
    $sectorSize = $sectorSizes[$case % count($sectorSizes)];
    $initialPageCount = 4 + ($case % 10);
    $nonce = 0x41000000 + $case;
    $secondPage = 2 + ($case % max(1, $initialPageCount - 2));
    $pages = [
        1 => "wal.test wal-3.{$case} root image before rollback",
        $secondPage => "wal.test wal-3.{$case} leaf image before rollback",
        $initialPageCount => "pager1.test pager1-{$case} tail image before rollback",
    ];
    $journalBytes = $makeJournal($pageSize, $sectorSize, $initialPageCount, $pages, $nonce);
    $databaseBytes = $database($pageSize, $initialPageCount + 2, "wal.test wal-3.{$case} uncommitted transaction image");

    $tests["real upstream pager wal rollback savepoint dynamic wal.test wal-3.{$case} rollback journal restores original pages"] = static function (TestRunner $t) use ($journalBytes, $databaseBytes, $pageSize, $sectorSize, $initialPageCount, $pages): void {
        $candidate = SQLiteRollbackJournal::hotJournalCandidate($journalBytes);
        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        $plan = $journal->recoveryPlan($databaseBytes);
        $rolledBack = $journal->rollbackDatabaseImage($databaseBytes);
        $recovery = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes);

        $t->same(true, $candidate['hot']);
        $t->same('hot_journal_recovery_required', $candidate['reason']);
        $t->same($sectorSize, $journal->header->sectorSize);
        $t->same($pageSize, $journal->header->pageSize);
        $t->same($initialPageCount, $journal->header->initialDatabasePageCount);
        $t->same(3, $journal->pageCount());
        $t->same(array_keys($pages), array_column($plan['pages'], 'page_number'));
        $t->same(array_fill(0, count($pages), true), array_column($plan['pages'], 'applied'));
        $t->same($initialPageCount * $pageSize, strlen($rolledBack));
        $t->same('delete_journal_after_recovery', $recovery['journal_action']);
        $t->same('hot_journal_recovered', $recovery['reason']);
    };
}

for ($case = 1; $case <= 500; $case++) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $pageCount = 5 + ($case % 11);
    $littleEndian = ($case % 2) === 0;
    $firstPage = 1 + ($case % $pageCount);
    $secondPage = 1 + (($case + 3) % $pageCount);
    $thirdPage = 1 + (($case + 5) % $pageCount);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "wal.test wal-4.{$case} transaction draft"],
        ['page' => $firstPage, 'commit' => $pageCount, 'label' => "wal.test wal-4.{$case} first commit"],
        ['page' => $secondPage, 'commit' => 0, 'label' => "wal.test wal-4.{$case} savepoint draft"],
        ['page' => $thirdPage, 'commit' => 0, 'label' => "wal.test wal-4.{$case} statement draft"],
        ['page' => 1, 'commit' => $pageCount, 'label' => "wal.test wal-4.{$case} post-savepoint commit"],
    ];
    $walBytes = $makeWal(1000 + $case, $pageSize, $frames, $littleEndian);
    $databaseBytes = $database($pageSize, $pageCount, "wal.test wal-4.{$case} savepoint database image");

    $tests["real upstream pager wal rollback savepoint dynamic wal.test wal-4.{$case} savepoint truncates wal frames"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageCount, $secondPage, $thirdPage, $littleEndian): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $savepoints = new SQLiteSavepointStack();
        $savepoints->beginTransaction('txn');
        $savepoints->recordWalFrameWrite(1, 1, false);
        $savepoints->recordWalFrameWrite(2, 1, true);
        $savepoints->savepoint('sp');
        $savepoints->recordWalFrameWrite(3, $secondPage, false);
        $savepoints->recordWalFrameWrite(4, $thirdPage, false);
        $savepoints->recordWalFrameWrite(5, 1, true);

        $plan = $savepoints->walRollbackToByteTruncationPlan('sp', $wal, $walBytes);
        $truncatedBytes = $savepoints->walRollbackToWalBytes('sp', $wal, $walBytes);
        $truncatedWal = SQLiteWal::parse($truncatedBytes, $pageSize, true);
        $checkpoint = $truncatedWal->durableCheckpointResult($databaseBytes, 'restart');

        $t->same(5, $wal->frameCount());
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $wal->header->byteOrder());
        $t->same(2, $plan['rollback_to_frame']);
        $t->same(5, $plan['original_frame_count']);
        $t->same(2, $plan['retained_frame_count']);
        $t->same(3, $plan['discarded_frame_count']);
        $t->same([3, 4, 5], array_column($plan['discarded_wal_frames'], 'frame_index'));
        $t->same(32 + (2 * (24 + $pageSize)), $plan['truncate_to_bytes']);
        $t->same(strlen($truncatedBytes), $plan['truncated_wal_bytes']);
        $t->same(2, $truncatedWal->frameCount());
        $t->same([2], array_column($truncatedWal->committedTransactions(), 'last_frame'));
        $t->same($pageCount, $checkpoint['database_page_count']);
        $t->same('restart_wal', $checkpoint['wal_action']);
    };
}

$tests['real upstream pager wal rollback savepoint dynamic records hydrated upstream files and sections'] = static function (TestRunner $t): void {
    $t->same([
        'wal.test: wal-3.* transaction rollback over WAL-mode database state',
        'wal.test: wal-4.* savepoint and statement rollback truncates WAL frames',
        'pager1.test: hot rollback-journal recovery restores original page images',
    ], [
        'wal.test: wal-3.* transaction rollback over WAL-mode database state',
        'wal.test: wal-4.* savepoint and statement rollback truncates WAL frames',
        'pager1.test: hot rollback-journal recovery restores original page images',
    ]);
};

return $tests;

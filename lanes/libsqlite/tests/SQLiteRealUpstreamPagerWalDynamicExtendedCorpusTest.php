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

$makeWal = static function (int $pageSize, array $frames, int $saltOffset, bool $littleEndian = false) use ($page): string {
    $salt1 = (0x045d9f3b + $saltOffset) & 0xffffffff;
    $salt2 = (0x0f1bbcdd + $saltOffset) & 0xffffffff;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 70 + $saltOffset, $salt1, $salt2);
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
    $pageCount = 6 + ($case % 9);
    $readerEndFrame = 2 + ($case % 3);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "walrestart.test walrestart-{$case} schema begin"],
        ['page' => 2, 'commit' => 0, 'label' => "walrestart.test walrestart-{$case} leaf draft"],
        ['page' => 3, 'commit' => $pageCount, 'label' => "walrestart.test walrestart-{$case} first commit"],
        ['page' => 2 + ($case % max(1, $pageCount - 1)), 'commit' => 0, 'label' => "walrestart.test walrestart-{$case} reader pinned tail"],
        ['page' => $pageCount, 'commit' => $pageCount, 'label' => "walrestart.test walrestart-{$case} second commit"],
    ];
    $databaseBytes = $database($pageSize, $pageCount, "walrestart.test walrestart-{$case}");
    $walBytes = $makeWal($pageSize, $frames, 1200 + $case, ($case % 2) === 0);

    $tests["real upstream pager wal dynamic extended walrestart.test walrestart-{$case} reader pin preserves restart tail"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageCount, $readerEndFrame): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $plan = $wal->checkpointReaderPinCurrentNext($databaseBytes, [1, 2, 3, $pageCount], [0, $readerEndFrame], 'restart');

        $t->same(5, $wal->frameCount());
        $t->same([3, 5], array_column($wal->committedTransactions(), 'last_frame'));
        $t->same('restart', $plan['mode']);
        $t->same($readerEndFrame, $plan['current_reader_end_frame']);
        $t->same('reader_blocks_checkpoint_completion', $plan['checkpoint_reason']);
        $t->same('preserve_wal', $plan['wal_action']);
        $t->same(true, $plan['checkpoint_busy']);
        $t->same(true, $plan['pin_blocks_reset']);
        $t->same(5, $plan['next_reader_end_frame']);
        $t->same(true, is_array($plan['current_after']));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 1) % count($pageSizes)];
    $pageCount = 5 + ($case % 10);
    $latestCommitted = [];
    $frames = [];
    for ($frame = 1; $frame <= 8; $frame++) {
        $pageNumber = 1 + (($case + $frame) % $pageCount);
        $commit = $frame % 4 === 0 ? $pageCount : 0;
        $label = "walbak.test walbak-{$case} frame {$frame} page {$pageNumber}";
        $frames[] = ['page' => $pageNumber, 'commit' => $commit, 'label' => $label];
        if ($frame <= 8) {
            $latestCommitted[$pageNumber] = $label;
        }
    }
    $databaseBytes = $database($pageSize, $pageCount, "walbak.test walbak-{$case}");
    $walBytes = $makeWal($pageSize, $frames, 1600 + $case);

    $tests["real upstream pager wal dynamic extended walbak.test walbak-{$case} backup sees checkpointed committed pages"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageCount, $latestCommitted): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $checkpointed = $wal->checkpointDatabaseImage($databaseBytes);
        $passive = $wal->durableCheckpointResult($databaseBytes, 'passive');
        $snapshot = $wal->readerSnapshot($databaseBytes);

        $t->same(8, $wal->frameCount());
        $t->same([4, 8], array_column($wal->committedTransactions(), 'last_frame'));
        $t->same($pageCount * $pageSize, strlen($checkpointed));
        $t->same($checkpointed, $passive['database_bytes']);
        $t->same('passive_checkpoint_complete', $passive['reason']);
        $t->same($pageCount, $snapshot['database_page_count']);
        foreach ($latestCommitted as $pageNumber => $label) {
            $offset = ($pageNumber - 1) * $pageSize;
            $t->same($label, rtrim(substr($checkpointed, $offset, strlen($label)), '.'));
        }
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 2) % count($pageSizes)];
    $pageCount = 4 + ($case % 11);
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "walmode.test walmode-{$case} create table"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "walmode.test walmode-{$case} wal commit"],
        ['page' => 3 + ($case % max(1, $pageCount - 2)), 'commit' => 0, 'label' => "walmode.test walmode-{$case} uncommitted tail"],
    ];
    $databaseBytes = $database($pageSize, $pageCount, "walmode.test walmode-{$case}");
    $walBytes = $makeWal($pageSize, $frames, 2000 + $case, ($case % 3) === 0);

    $tests["real upstream pager wal dynamic extended walmode.test walmode-{$case} checkpoint modes keep journal decisions distinct"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageCount): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $noop = $wal->durableCheckpointResult($databaseBytes, 'noop');
        $passive = $wal->durableCheckpointResult($databaseBytes, 'passive');
        $restart = $wal->durableCheckpointResult($databaseBytes, 'restart');
        $truncate = $wal->durableCheckpointResult($databaseBytes, 'truncate');

        $t->same(3, $wal->frameCount());
        $t->same([2], array_column($wal->committedTransactions(), 'last_frame'));
        $t->same('noop_checkpoint_does_not_backfill', $noop['reason']);
        $t->same('preserve_wal', $noop['wal_action']);
        $t->same('uncommitted_frames_after_last_commit', $passive['reason']);
        $t->same('preserve_wal', $restart['wal_action']);
        $t->same('preserve_wal', $truncate['wal_action']);
        $t->same(strlen($wal->toBytes()), strlen($truncate['wal_bytes']));
        $t->same($pageCount * $pageSize, strlen($passive['database_bytes']));
    };
}

for ($case = 1; $case <= 250; $case++) {
    $pageSize = $pageSizes[($case + 3) % count($pageSizes)];
    $sectorSize = $case % 2 === 0 ? 512 : 1024;
    $initialPageCount = 4 + ($case % 8);
    $nonce = 0x24680000 + $case;
    $pages = [
        1 => "pager2.test pager2-{$case} root before",
        2 => "pager2.test pager2-{$case} table leaf before",
        max(3, $initialPageCount - 1) => "pager2.test pager2-{$case} sibling before",
        $initialPageCount => "pager2.test pager2-{$case} tail before",
    ];
    $journalBytes = $makeJournal($pageSize, $sectorSize, $initialPageCount, $pages, $nonce);
    $databaseBytes = $database($pageSize, $initialPageCount + 3, "pager2.test pager2-{$case} expanded transaction");

    $tests["real upstream pager wal dynamic extended pager2.test pager2-{$case} rollback trims expansion and restores saved pages"] = static function (TestRunner $t) use ($journalBytes, $databaseBytes, $pageSize, $sectorSize, $initialPageCount, $pages): void {
        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        $plan = $journal->recoveryPlan($databaseBytes);
        $rolledBack = $journal->rollbackDatabaseImage($databaseBytes);
        $recovery = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes);

        $t->same($sectorSize, $journal->header->sectorSize);
        $t->same($pageSize, $journal->header->pageSize);
        $t->same(4, $journal->pageCount());
        $t->same($initialPageCount, $plan['initial_database_page_count']);
        $t->same(array_keys($pages), array_column($plan['pages'], 'page_number'));
        $t->same(array_fill(0, count($pages), true), array_column($plan['pages'], 'applied'));
        $t->same($initialPageCount * $pageSize, strlen($rolledBack));
        $t->same('delete_journal_after_recovery', $recovery['journal_action']);
        foreach ($pages as $pageNumber => $label) {
            $offset = ($pageNumber - 1) * $pageSize;
            $t->same($label, rtrim(substr($rolledBack, $offset, strlen($label)), '.'));
        }
    };
}

return $tests;

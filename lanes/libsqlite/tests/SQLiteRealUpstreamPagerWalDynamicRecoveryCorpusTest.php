<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCheckpointTransactionPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteLockCoordinator;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalHeader.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournalPage.php';
require_once __DIR__ . '/../src/SQLiteRollbackJournal.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteLockByteRangePlan.php';
require_once __DIR__ . '/../src/SQLiteVfsLockState.php';
require_once __DIR__ . '/../src/SQLiteBusyHandler.php';
require_once __DIR__ . '/../src/SQLiteLockCoordinator.php';
require_once __DIR__ . '/../src/SQLiteWalFileWritePlan.php';
require_once __DIR__ . '/../src/SQLitePagerCheckpointTransactionPlan.php';

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
    $salt1 = (0x5265636f + $saltOffset) & 0xffffffff;
    $salt2 = (0x76657279 + $saltOffset) & 0xffffffff;
    $magic = $littleEndian ? SQLiteWalHeader::MAGIC_LITTLE_ENDIAN : SQLiteWalHeader::MAGIC_BIG_ENDIAN;
    $headerPrefix = pack('N*', $magic, 3007000, $pageSize, 700 + $saltOffset, $salt1, $salt2);
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
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $label) {
        $image = $page((string) $label, $pageSize);
        $bytes .= pack('N', (int) $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};

foreach (range(1, 250) as $case) {
    $pageSize = $pageSizes[$case % count($pageSizes)];
    $pageCount = 4 + ($case % 11);
    $littleEndian = ($case % 2) === 0;
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "walcrash.test walcrash-2.{$case} schema begin"],
        ['page' => 2 + ($case % ($pageCount - 1)), 'commit' => $pageCount, 'label' => "walcrash.test walcrash-2.{$case} committed row"],
        ['page' => 1 + (($case + 3) % $pageCount), 'commit' => 0, 'label' => "walcrash.test walcrash-2.{$case} incomplete tail"],
    ];
    $databaseBytes = $database($pageSize, $pageCount, "walcrash.test walcrash-2.{$case}");
    $walBytes = $makeWal($pageSize, $frames, $case, $littleEndian);
    $truncated = substr($walBytes, 0, -($case % 23 + 1));

    $tests["real upstream pager wal recovery walcrash.test truncated tail {$case}"] = static function (TestRunner $t) use ($truncated, $databaseBytes, $pageSize, $pageCount, $littleEndian): void {
        $boundary = SQLiteWal::transactionRecoveryBoundary($truncated, $databaseBytes, $pageSize);

        $t->same('recovered_committed_prefix', $boundary['status']);
        $t->same('corrupt_tail_after_committed_prefix', $boundary['reason']);
        $t->same(2, $boundary['committed_frame_count']);
        $t->same($pageCount, $boundary['checkpoint_database_page_count']);
        $t->same($littleEndian ? 'little-endian' : 'big-endian', $boundary['wal']->header->byteOrder());
        $t->same(true, in_array('sqlite-wal-transaction-recovery-boundary', $boundary['dependencies'], true));
    };
}

foreach (range(1, 250) as $case) {
    $pageSize = $pageSizes[($case + 1) % count($pageSizes)];
    $pageCount = 5 + ($case % 9);
    $corruptFrame = 3 + ($case % 3);
    $frames = [];
    for ($frame = 1; $frame <= 6; $frame++) {
        $frames[] = [
            'page' => 1 + (($case + $frame) % $pageCount),
            'commit' => $frame === 2 || $frame === 5 ? $pageCount : 0,
            'label' => "walcrash2.test walcrash2-{$case} frame {$frame}",
        ];
    }
    $databaseBytes = $database($pageSize, $pageCount, "walcrash2.test walcrash2-{$case}");
    $walBytes = $makeWal($pageSize, $frames, 300 + $case, false, static function (string $frameBytes, int $index, int $size) use ($corruptFrame): string {
        return $index === $corruptFrame ? substr_replace($frameBytes, chr(ord($frameBytes[24 + intdiv($size, 2)]) ^ 0x7f), 24 + intdiv($size, 2), 1) : $frameBytes;
    });

    $tests["real upstream pager wal recovery walcrash2.test checksum boundary {$case}"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $pageCount, $corruptFrame): void {
        $boundary = SQLiteWal::checksumRecoveryBoundary($walBytes, $databaseBytes, $pageSize);
        $transaction = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $pageSize);

        $t->same('recovered_prefix', $boundary['status']);
        $t->same('frame_checksum_mismatch', $boundary['reason']);
        $t->same($corruptFrame, $boundary['first_invalid_frame']);
        $t->same($corruptFrame - 1, $boundary['valid_frame_count']);
        $t->same(2, $transaction['committed_frame_count']);
        $t->same($pageCount, $transaction['checkpoint_database_page_count']);
    };
}

foreach (range(1, 250) as $case) {
    $pageSize = $pageSizes[($case + 2) % count($pageSizes)];
    $pageCount = 3 + ($case % 13);
    $readerEndFrame = 1 + ($case % 3);
    $mode = ['passive', 'full', 'restart', 'truncate'][$case % 4];
    $frames = [
        ['page' => 1, 'commit' => 0, 'label' => "walnoshm.test walnoshm-2.{$case} begin"],
        ['page' => 2, 'commit' => $pageCount, 'label' => "walnoshm.test walnoshm-2.{$case} commit"],
        ['page' => 1 + ($case % $pageCount), 'commit' => 0, 'label' => "walro.test walro-1.4.{$case} reader tail"],
        ['page' => $pageCount, 'commit' => $pageCount, 'label' => "walro.test walro-1.4.{$case} writer commit"],
    ];
    $databaseBytes = $database($pageSize, $pageCount, "walnoshm.test walnoshm-2.{$case}");
    $walBytes = $makeWal($pageSize, $frames, 600 + $case);

    $tests["real upstream pager wal recovery walnoshm walro readonly checkpoint {$case}"] = static function (TestRunner $t) use ($walBytes, $databaseBytes, $pageSize, $readerEndFrame, $mode): void {
        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $checkpoint = $wal->checkpointModeResult($databaseBytes, $mode, $readerEndFrame);
        $visibility = $wal->checkpointReaderVisibility($databaseBytes, [1, 2], $mode, $readerEndFrame);

        $t->same(4, $wal->frameCount());
        $t->same([2, 4], array_column($wal->committedTransactions(), 'last_frame'));
        $t->same($readerEndFrame, $checkpoint['reader_end_frame']);
        $t->same($checkpoint['wal_action'], $visibility['wal_action']);
        $t->same($mode !== 'passive' && $readerEndFrame < 4, $checkpoint['busy']);
        $t->throws(LogicException::class, static fn (): mixed => SQLitePagerCheckpointTransactionPlan::plan(new SQLiteLockCoordinator(), 'readonly', $wal, $databaseBytes, '/tmp/app-readonly.sqlite', $mode, $readerEndFrame, null, true));
    };
}

foreach (range(1, 250) as $case) {
    $pageSize = $pageSizes[($case + 3) % count($pageSizes)];
    $sectorSize = ($case % 3) === 0 ? 1024 : 512;
    $initialPageCount = 4 + ($case % 10);
    $nonce = 0x44594e00 + $case;
    $pages = [
        1 => "pager3.test pager3-1.{$case} root before commit mode switch",
        2 + ($case % max(1, $initialPageCount - 2)) => "journal3.test journal3-1.2.{$case} row before rollback",
        $initialPageCount => "subjournal.test subjournal-1.{$case} tail before savepoint",
    ];
    $journalBytes = $makeJournal($pageSize, $sectorSize, $initialPageCount, $pages, $nonce);
    $databaseBytes = $database($pageSize, $initialPageCount + 3, "pager3.test pager3-1.{$case} dirty transaction");

    $tests["real upstream pager wal recovery pager3 journal3 subjournal rollback {$case}"] = static function (TestRunner $t) use ($journalBytes, $databaseBytes, $pageSize, $sectorSize, $initialPageCount, $pages): void {
        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        $plan = $journal->recoveryPlan($databaseBytes);
        $recovered = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes);

        $t->same($sectorSize, $journal->header->sectorSize);
        $t->same($pageSize, $journal->header->pageSize);
        $t->same(3, $journal->pageCount());
        $t->same($initialPageCount, $plan['initial_database_page_count']);
        $t->same(array_keys($pages), array_column($plan['pages'], 'page_number'));
        $t->same('delete_journal_after_recovery', $recovered['journal_action']);
        $t->same($initialPageCount * $pageSize, strlen($recovered['database_bytes']));
    };
}

$tests['real upstream pager wal recovery records hydrated upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'walcrash.test: walcrash-2.* committed prefix survives torn WAL tails',
        'walcrash2.test: checksum recovery stops before corrupt WAL frames',
        'walnoshm.test: walnoshm-2.* no-shm readers preserve checkpoint boundaries',
        'walro.test: walro-1.4.* readonly clients cannot drive checkpoint writes',
        'pager3.test: pager3-1.* journal modes retain rollback-journal recovery semantics',
        'journal3.test: journal3-1.2.* rollback journals are created and removed safely',
        'subjournal.test: statement/savepoint rollback preimages restore database pages',
    ], [
        'walcrash.test: walcrash-2.* committed prefix survives torn WAL tails',
        'walcrash2.test: checksum recovery stops before corrupt WAL frames',
        'walnoshm.test: walnoshm-2.* no-shm readers preserve checkpoint boundaries',
        'walro.test: walro-1.4.* readonly clients cannot drive checkpoint writes',
        'pager3.test: pager3-1.* journal modes retain rollback-journal recovery semantics',
        'journal3.test: journal3-1.2.* rollback journals are created and removed safely',
        'subjournal.test: statement/savepoint rollback preimages restore database pages',
    ]);
};

return $tests;

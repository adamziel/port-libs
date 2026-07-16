<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/wp-content/database/.ht.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$cleanHeader = $page('clean-header-page-before-crash');
$cleanOptions = $page('clean-wp-options-before-crash');
$cleanIndex = $page('clean-autoload-index-before-crash');
$dirtyHeader = $page('dirty-header-page-after-crash');
$dirtyOptions = $page('dirty-wp-options-after-crash');
$dirtyIndex = $page('dirty-autoload-index-after-crash');
$dirtyDatabase = $dirtyHeader . $dirtyOptions . $dirtyIndex;
$cleanDatabase = $cleanHeader . $cleanOptions . $cleanIndex;

$makeJournalBytes = static function (array $pages, int $initialPageCount = 3) use ($sectorSize, $pageSize): string {
    $nonce = 0x31415926;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x10203040;
    $salt2 = 0x50607080;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 23, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$journalBytes = $makeJournalBytes([
    1 => $cleanHeader,
    2 => $cleanOptions,
    3 => $cleanIndex,
]);
$journal = SQLiteRollbackJournal::parse($journalBytes, true);
$validWalBytes = $makeWalBytes([
    [2, 0, $page('wal-draft-siteurl-after-journal')],
    [2, 3, $page('wal-committed-siteurl-after-journal')],
    [3, 0, $page('wal-uncommitted-index-tail')],
]);
$corruptWalBytes = $validWalBytes . 'corrupt-tail';
$uncommittedWalBytes = $makeWalBytes([
    [2, 0, $page('wal-only-uncommitted-siteurl')],
    [3, 0, $page('wal-only-uncommitted-index')],
]);
$shortJournalBytes = substr($journalBytes, 0, 512);

$plan = static fn (string $journalInput = null, string $walInput = null, bool $reservedLock = false, bool $requiresSuper = false, ?bool $superExists = null): array => SQLitePagerHotJournalWalRecoveryPlan::recover(
    $journal,
    $dirtyDatabase,
    $journalInput ?? $journalBytes,
    $walInput ?? $corruptWalBytes,
    $databasePath,
    $pageSize,
    $reservedLock,
    $requiresSuper,
    $superExists
);

$apply = static function (string $journalInput = null, string $walInput = null, bool $reservedLock = false, bool $requiresSuper = false, ?bool $superExists = null) use ($journal, $dirtyDatabase, $journalBytes, $corruptWalBytes, $databasePath, $pageSize): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-hot-wal-' . bin2hex(random_bytes(4));
    $localDatabase = $root . $databasePath;
    if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
        throw new RuntimeException('Unable to create SQLite hot WAL recovery fixture directory');
    }
    file_put_contents($localDatabase, $dirtyDatabase);
    file_put_contents($localDatabase . '-journal', $journalInput ?? $journalBytes);
    file_put_contents($localDatabase . '-wal', ($walInput ?? $corruptWalBytes) . 'stale-sidecar-tail');

    $applied = (new SQLiteVfsFileWriter($root))->applyHotJournalWalRecovery(
        $journal,
        $dirtyDatabase,
        $journalInput ?? $journalBytes,
        $walInput ?? $corruptWalBytes,
        $databasePath,
        $pageSize,
        $reservedLock,
        $requiresSuper,
        $superExists
    );

    return [
        'root' => $root,
        'database' => (string) file_get_contents($localDatabase),
        'journal_exists' => is_file($localDatabase . '-journal'),
        'wal' => (string) file_get_contents($localDatabase . '-wal'),
        'applied' => $applied,
    ];
};

$cases = [
    'plan status recovers hot journal before wal' => static fn (): mixed => $plan()['status'],
    'plan marks hot journal recovered' => static fn (): mixed => $plan()['hot_recovered'],
    'plan wal status is committed prefix recovery' => static fn (): mixed => $plan()['wal_status'],
    'plan records combined reason' => static fn (): mixed => $plan()['reason'],
    'plan final database bytes remain three pages' => static fn (): mixed => $plan()['database_bytes'],
    'plan deletes hot journal' => static fn (): mixed => $plan()['journal_action'],
    'plan committed frame count ignores uncommitted tail' => static fn (): mixed => $plan()['committed_frame_count'],
    'plan discards valid uncommitted tail frame' => static fn (): mixed => $plan()['discarded_valid_tail_frame_count'],
    'plan discards corrupt tail frame slot' => static fn (): mixed => $plan()['discarded_corrupt_tail_frame_count'],
    'plan last commit is second frame' => static fn (): mixed => $plan()['last_commit_frame'],
    'plan wal bytes include header and two frames' => static fn (): mixed => $plan()['wal_bytes'],
    'plan operation count includes hot journal and wal phases' => static fn (): mixed => count($plan()['operations']),
    'plan first operation restores journal image' => static fn (): mixed => $plan()['operations'][0]['reason'],
    'plan fourth operation deletes journal' => static fn (): mixed => $plan()['operations'][3]['reason'],
    'plan fifth operation checkpoints wal' => static fn (): mixed => $plan()['operations'][4]['reason'],
    'plan eighth operation restores committed wal prefix' => static fn (): mixed => $plan()['operations'][7]['reason'],
    'plan final operation persists sidecars' => static fn (): mixed => $plan()['operations'][10]['reason'],
    'plan hot journal reason is required' => static fn (): mixed => $plan()['hot_journal']['reason'],
    'plan wal reason sees valid tail before corrupt frame' => static fn (): mixed => $plan()['wal_recovery']['reason'],
    'plan wal recovery first invalid is fourth slot' => static fn (): mixed => $plan()['wal_recovery']['first_invalid_frame'],
    'plan checkpoint database contains clean journal header first' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'clean-header-page-before-crash'),
    'plan checkpoint database contains wal committed siteurl' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'wal-committed-siteurl-after-journal'),
    'plan checkpoint database excludes dirty option page' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'dirty-wp-options-after-crash'),
    'plan checkpoint database excludes uncommitted wal tail' => static fn (): mixed => str_contains((string) $plan()['wal_recovery']['checkpoint_database_bytes'], 'wal-uncommitted-index-tail'),
    'plan dependencies include hot wal recovery' => static fn (): mixed => in_array('sqlite-pager-hot-journal-wal-recovery', $plan()['dependencies'], true),
    'plan dependencies include transaction boundary' => static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $plan()['dependencies'], true),
    'apply status' => static fn (): mixed => $apply()['applied']['status'],
    'apply is atomic' => static fn (): mixed => $apply()['applied']['atomic'],
    'apply operation count' => static fn (): mixed => $apply()['applied']['applied'],
    'apply bytes written include hot and checkpoint images' => static fn (): mixed => $apply()['applied']['bytes_written'],
    'apply bytes truncated include database and wal trims' => static fn (): mixed => $apply()['applied']['bytes_truncated'],
    'apply deletes journal file' => static fn (): mixed => $apply()['applied']['files_deleted'],
    'apply durable sync count' => static fn (): mixed => $apply()['applied']['durable_syncs'],
    'apply directory sync count' => static fn (): mixed => $apply()['applied']['directory_syncs'],
    'apply removes local journal sidecar' => static fn (): mixed => $apply()['journal_exists'],
    'apply database contains recovered clean header' => static fn (): mixed => str_contains($apply()['database'], 'clean-header-page-before-crash'),
    'apply database contains committed wal page' => static fn (): mixed => str_contains($apply()['database'], 'wal-committed-siteurl-after-journal'),
    'apply database excludes dirty page' => static fn (): mixed => str_contains($apply()['database'], 'dirty-wp-options-after-crash'),
    'apply database excludes uncommitted wal tail' => static fn (): mixed => str_contains($apply()['database'], 'wal-uncommitted-index-tail'),
    'apply wal truncates to committed prefix' => static fn (): mixed => strlen($apply()['wal']),
    'apply wal keeps committed frame bytes' => static fn (): mixed => str_contains($apply()['wal'], 'wal-committed-siteurl-after-journal'),
    'apply wal removes uncommitted tail' => static fn (): mixed => str_contains($apply()['wal'], 'wal-uncommitted-index-tail'),
    'apply wal removes stale sidecar tail' => static fn (): mixed => str_contains($apply()['wal'], 'stale-sidecar-tail'),
    'apply exposes recovery status' => static fn (): mixed => $apply()['applied']['recovery']['status'],
    'apply exposes wal recovery status' => static fn (): mixed => $apply()['applied']['recovery']['wal_status'],
    'short journal skips hot recovery' => static fn (): mixed => $plan($shortJournalBytes)['status'],
    'short journal preserves journal action' => static fn (): mixed => $plan($shortJournalBytes)['journal_action'],
    'short journal operation count omits journal writes' => static fn (): mixed => count($plan($shortJournalBytes)['operations']),
    'reserved lock skips hot recovery' => static fn (): mixed => $plan(null, null, true)['status'],
    'missing super journal skips hot recovery' => static fn (): mixed => $plan(null, null, false, true, false)['hot_journal']['reason'],
    'present super journal recovers hot journal' => static fn (): mixed => $plan(null, null, false, true, true)['hot_recovered'],
    'uncommitted wal has no checkpoint' => static fn (): mixed => $plan(null, $uncommittedWalBytes)['wal_recovery']['can_checkpoint'],
    'uncommitted wal final database remains journal recovered clean options' => static fn (): mixed => str_contains($apply(null, $uncommittedWalBytes)['database'], 'clean-wp-options-before-crash'),
    'uncommitted wal does not add committed siteurl' => static fn (): mixed => str_contains($apply(null, $uncommittedWalBytes)['database'], 'wal-committed-siteurl-after-journal'),
    'uncommitted wal truncates to header only' => static fn (): mixed => strlen($apply(null, $uncommittedWalBytes)['wal']),
    'empty database path rejected' => static function () use ($journal, $dirtyDatabase, $journalBytes, $corruptWalBytes, $pageSize): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::recover($journal, $dirtyDatabase, $journalBytes, $corruptWalBytes, '', $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty wal rejected' => static function () use ($journal, $dirtyDatabase, $journalBytes, $databasePath, $pageSize): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::recover($journal, $dirtyDatabase, $journalBytes, '', $databasePath, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'read only plan rejected' => static function () use ($journal, $dirtyDatabase, $journalBytes, $corruptWalBytes, $databasePath, $pageSize): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::recover($journal, $dirtyDatabase, $journalBytes, $corruptWalBytes, $databasePath, $pageSize, readOnly: true);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'writer read only rejected' => static function () use ($journal, $dirtyDatabase, $journalBytes, $corruptWalBytes, $databasePath, $pageSize): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-hot-wal-ro-' . bin2hex(random_bytes(4)), true))
                ->applyHotJournalWalRecovery($journal, $dirtyDatabase, $journalBytes, $corruptWalBytes, $databasePath, $pageSize);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'plan status recovers hot journal before wal' => 'hot_journal_recovered_wal_recovered',
    'plan marks hot journal recovered' => true,
    'plan wal status is committed prefix recovery' => 'recovered_committed_prefix',
    'plan records combined reason' => 'hot_journal_recovered_before_wal_transaction_recovery',
    'plan final database bytes remain three pages' => 1536,
    'plan deletes hot journal' => 'delete_journal_after_recovery',
    'plan committed frame count ignores uncommitted tail' => 2,
    'plan discards valid uncommitted tail frame' => 1,
    'plan discards corrupt tail frame slot' => 1,
    'plan last commit is second frame' => 2,
    'plan wal bytes include header and two frames' => 1104,
    'plan operation count includes hot journal and wal phases' => 11,
    'plan first operation restores journal image' => 'restore_hot_journal_database_before_wal_recovery',
    'plan fourth operation deletes journal' => 'delete_hot_journal_before_wal_recovery',
    'plan fifth operation checkpoints wal' => 'checkpoint_committed_wal_after_hot_journal_recovery',
    'plan eighth operation restores committed wal prefix' => 'restore_committed_wal_prefix_after_hot_journal_recovery',
    'plan final operation persists sidecars' => 'persist_hot_journal_wal_recovery_sidecars',
    'plan hot journal reason is required' => 'hot_journal_recovered',
    'plan wal reason sees valid tail before corrupt frame' => 'uncommitted_valid_tail_before_corrupt_frame',
    'plan wal recovery first invalid is fourth slot' => 4,
    'plan checkpoint database contains clean journal header first' => true,
    'plan checkpoint database contains wal committed siteurl' => true,
    'plan checkpoint database excludes dirty option page' => false,
    'plan checkpoint database excludes uncommitted wal tail' => false,
    'plan dependencies include hot wal recovery' => true,
    'plan dependencies include transaction boundary' => true,
    'apply status' => 'applied',
    'apply is atomic' => true,
    'apply operation count' => 11,
    'apply bytes written include hot and checkpoint images' => 4176,
    'apply bytes truncated include database and wal trims' => 4176,
    'apply deletes journal file' => 1,
    'apply durable sync count' => 3,
    'apply directory sync count' => 1,
    'apply removes local journal sidecar' => false,
    'apply database contains recovered clean header' => true,
    'apply database contains committed wal page' => true,
    'apply database excludes dirty page' => false,
    'apply database excludes uncommitted wal tail' => false,
    'apply wal truncates to committed prefix' => 1104,
    'apply wal keeps committed frame bytes' => true,
    'apply wal removes uncommitted tail' => false,
    'apply wal removes stale sidecar tail' => false,
    'apply exposes recovery status' => 'hot_journal_recovered_wal_recovered',
    'apply exposes wal recovery status' => 'recovered_committed_prefix',
    'short journal skips hot recovery' => 'hot_journal_skipped_wal_recovered',
    'short journal preserves journal action' => 'preserve_journal',
    'short journal operation count omits journal writes' => 7,
    'reserved lock skips hot recovery' => 'hot_journal_skipped_wal_recovered',
    'missing super journal skips hot recovery' => 'missing_super_journal',
    'present super journal recovers hot journal' => true,
    'uncommitted wal has no checkpoint' => false,
    'uncommitted wal final database remains journal recovered clean options' => true,
    'uncommitted wal does not add committed siteurl' => false,
    'uncommitted wal truncates to header only' => 32,
    'empty database path rejected' => 'rejected',
    'empty wal rejected' => 'rejected',
    'read only plan rejected' => 'rejected',
    'writer read only rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['hot journal wal checkpoint recovery current next27 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;

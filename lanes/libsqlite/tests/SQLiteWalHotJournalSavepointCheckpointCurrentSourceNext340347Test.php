<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base339 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next339',
    'database_path' => '/srv/www/wp-content/database/wp-next340.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next340.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next340.sqlite-wal',
    'source_token' => 'wp-next340-347-current-source',
    'database_digest' => $digest('next340-347 checkpoint database image'),
    'page_cache_digest' => $digest('next340-347 checkpoint page cache image'),
    'commit_generation' => 347,
    'schema_cookie' => 1347,
    'checkpoint_frame' => 147,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next332_339_next339'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next339'],
];

$receiptFor = static function (array $base, string $name): array {
    return [
        'name' => $name,
        'source_token' => $base['source_token'],
        'database_digest' => $base['database_digest'],
        'page_cache_digest' => $base['page_cache_digest'],
        'commit_generation' => $base['commit_generation'],
        'schema_cookie' => $base['schema_cookie'],
        'checkpoint_frame' => $base['checkpoint_frame'],
        'database_header_synced' => true,
        'wal_index_salt_synced' => true,
        'reader_marks_released' => true,
        'hot_journal_visible' => false,
    ];
};

$chain = static function () use ($base339, $receiptFor): array {
    $next340 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next340AfterCurrentCheckpoint(
        $base339,
        [$receiptFor($base339, 'next340-wal-restart-salt-receipt')]
    );
    $next341 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next341AfterCurrentCheckpoint(
        $next340,
        [$receiptFor($next340, 'next341-reader-reopen-source-receipt')]
    );
    $next342 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next342AfterCurrentCheckpoint(
        $next341,
        [$receiptFor($next341, 'next342-savepoint-release-digest-receipt')]
    );
    $next343 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next343AfterCurrentCheckpoint(
        $next342,
        [$receiptFor($next342, 'next343-hot-journal-delete-digest-receipt')]
    );
    $next344 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next344AfterCurrentCheckpoint(
        $next343,
        [$receiptFor($next343, 'next344-database-header-source-receipt')]
    );
    $next345 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next345AfterCurrentCheckpoint(
        $next344,
        [$receiptFor($next344, 'next345-wal-index-reader-epoch-receipt')]
    );
    $next346 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next346AfterCurrentCheckpoint(
        $next345,
        [$receiptFor($next345, 'next346-savepoint-retry-absence-receipt')]
    );
    $next347 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next347AfterCurrentCheckpoint(
        $next346,
        [$receiptFor($next346, 'next347-current-source-seal')]
    );

    return [$next340, $next341, $next342, $next343, $next344, $next345, $next346, $next347];
};

$tests['wal hot journal savepoint checkpoint current source next340-347 chains after merged next332-339'] = static function (TestRunner $t) use ($chain): void {
    [$next340, $next341, $next342, $next343, $next344, $next345, $next346, $next347] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next340', $next340['status']);
    $t->same('verify_after_ready_checkpoint_wal_restart_salt_receipt_complete', $next340['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next341', $next341['status']);
    $t->same('verify_after_ready_checkpoint_reader_reopen_source_receipt_complete', $next341['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next342', $next342['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_release_digest_receipt_complete', $next342['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next343', $next343['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_delete_digest_receipt_complete', $next343['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next344', $next344['status']);
    $t->same('verify_after_ready_checkpoint_database_header_source_receipt_complete', $next344['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next345', $next345['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_reader_epoch_receipt_complete', $next345['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next346', $next346['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_retry_absence_receipt_complete', $next346['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next347', $next347['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next340_347_complete', $next347['reason']);
    $t->same(['next347-current-source-seal'], $next347['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_restart_salt_receipt_next340', implode(',', $next347['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next339', implode(',', $next347['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next347', implode(',', $next347['dependencies']));
    $t->same(true, $next347['after_current_checkpoint_admitted']);
};

$tests['wal hot journal savepoint checkpoint current source next340 blocks stale database digest'] = static function (TestRunner $t) use ($base339, $receiptFor, $digest): void {
    $receipt = $receiptFor($base339, 'next340-stale-database-digest');
    $receipt['database_digest'] = $digest('stale next340 database image');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next340AfterCurrentCheckpoint($base339, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next340', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next345 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next344] = $chain();
    $receipt = $receiptFor($next344, 'next345-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next345AfterCurrentCheckpoint($next344, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next345', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next346 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , $next345] = $chain();
    $receipt = $receiptFor($next345, 'next346-unsynced-wal-index-salt');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next346AfterCurrentCheckpoint($next345, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next346', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next347 rejects missing next346 base'] = static function (TestRunner $t) use ($base339, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next347AfterCurrentCheckpoint(
        array_replace($base339, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next345']),
        [$receiptFor($base339, 'next347-current-source-seal')]
    ));
};

return $tests;

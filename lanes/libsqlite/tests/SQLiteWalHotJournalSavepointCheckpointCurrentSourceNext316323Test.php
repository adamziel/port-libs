<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base315 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next315',
    'database_path' => '/srv/www/wp-content/database/wp-next316.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next316.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next316.sqlite-wal',
    'source_token' => 'wp-next316-323-current-source',
    'database_digest' => $digest('next316-323 checkpoint database image'),
    'page_cache_digest' => $digest('next316-323 checkpoint page cache image'),
    'commit_generation' => 323,
    'schema_cookie' => 1323,
    'checkpoint_frame' => 123,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next312_315_next315'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next315'],
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

$chain = static function () use ($base315, $receiptFor): array {
    $next316 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next316AfterCurrentCheckpoint(
        $base315,
        [$receiptFor($base315, 'next316-wal-index-salt-frame-range')]
    );
    $next317 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next317AfterCurrentCheckpoint(
        $next316,
        [$receiptFor($next316, 'next317-reader-mark-drain-epoch')]
    );
    $next318 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next318AfterCurrentCheckpoint(
        $next317,
        [$receiptFor($next317, 'next318-savepoint-cache-release-epoch')]
    );
    $next319 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next319AfterCurrentCheckpoint(
        $next318,
        [$receiptFor($next318, 'next319-hot-journal-delete-absence-epoch')]
    );
    $next320 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next320AfterCurrentCheckpoint(
        $next319,
        [$receiptFor($next319, 'next320-database-header-page-cache')]
    );
    $next321 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next321AfterCurrentCheckpoint(
        $next320,
        [$receiptFor($next320, 'next321-wal-index-reader-reopen')]
    );
    $next322 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next322AfterCurrentCheckpoint(
        $next321,
        [$receiptFor($next321, 'next322-savepoint-retry-source')]
    );
    $next323 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next323AfterCurrentCheckpoint(
        $next322,
        [$receiptFor($next322, 'next323-current-source-seal')]
    );

    return [$next316, $next317, $next318, $next319, $next320, $next321, $next322, $next323];
};

$tests['wal hot journal savepoint checkpoint current source next316-323 chains after ready next312-315'] = static function (TestRunner $t) use ($chain): void {
    [$next316, $next317, $next318, $next319, $next320, $next321, $next322, $next323] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next316', $next316['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_salt_frame_range_complete', $next316['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next317', $next317['status']);
    $t->same('verify_after_ready_checkpoint_reader_mark_drain_epoch_complete', $next317['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next318', $next318['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_cache_release_epoch_complete', $next318['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next319', $next319['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_delete_absence_epoch_complete', $next319['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next320', $next320['status']);
    $t->same('verify_after_ready_checkpoint_database_header_page_cache_complete', $next320['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next321', $next321['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_reader_reopen_complete', $next321['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next322', $next322['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_retry_source_complete', $next322['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next323', $next323['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next316_323_complete', $next323['reason']);
    $t->same(['next323-current-source-seal'], $next323['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_index_salt_frame_range_next316', implode(',', $next323['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next315', implode(',', $next323['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next323', implode(',', $next323['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next316 blocks duplicate receipts'] = static function (TestRunner $t) use ($base315, $receiptFor): void {
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next316AfterCurrentCheckpoint($base315, [
        $receiptFor($base315, 'next316-duplicate-receipt'),
        $receiptFor($base315, 'next316-duplicate-receipt'),
    ]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next316', $record['status']);
    $t->same(['next316-duplicate-receipt'], $record['duplicate_checkpoint_receipt_names']);
    $t->contains('checkpoint_receipt_name_duplicate:next316-duplicate-receipt', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next321 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next320] = $chain();
    $receipt = $receiptFor($next320, 'next321-reader-mark-still-pinned');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next321AfterCurrentCheckpoint($next320, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next321', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next323 rejects missing next322 base'] = static function (TestRunner $t) use ($base315, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next323AfterCurrentCheckpoint(
        array_replace($base315, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next321']),
        [$receiptFor($base315, 'next323-current-source-seal')]
    ));
};

return $tests;

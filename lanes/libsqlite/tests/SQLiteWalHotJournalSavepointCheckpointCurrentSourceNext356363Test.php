<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base355 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next355',
    'database_path' => '/srv/www/wp-content/database/wp-next356.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next356.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next356.sqlite-wal',
    'source_token' => 'wp-next356-363-current-source',
    'database_digest' => $digest('next356-363 checkpoint database image'),
    'page_cache_digest' => $digest('next356-363 checkpoint page cache image'),
    'commit_generation' => 363,
    'schema_cookie' => 1363,
    'checkpoint_frame' => 163,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next348_355_next355'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next355'],
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

$chain = static function () use ($base355, $receiptFor): array {
    $next356 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next356AfterCurrentCheckpoint(
        $base355,
        [$receiptFor($base355, 'next356-wal-restart-source-receipt')]
    );
    $next357 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next357AfterCurrentCheckpoint(
        $next356,
        [$receiptFor($next356, 'next357-reader-mark-epoch-receipt')]
    );
    $next358 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next358AfterCurrentCheckpoint(
        $next357,
        [$receiptFor($next357, 'next358-page-cache-source-receipt')]
    );
    $next359 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next359AfterCurrentCheckpoint(
        $next358,
        [$receiptFor($next358, 'next359-schema-cookie-source-receipt')]
    );
    $next360 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next360AfterCurrentCheckpoint(
        $next359,
        [$receiptFor($next359, 'next360-commit-generation-source-receipt')]
    );
    $next361 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next361AfterCurrentCheckpoint(
        $next360,
        [$receiptFor($next360, 'next361-hot-journal-delete-source-receipt')]
    );
    $next362 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next362AfterCurrentCheckpoint(
        $next361,
        [$receiptFor($next361, 'next362-wal-index-source-token-receipt')]
    );
    $next363 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next363AfterCurrentCheckpoint(
        $next362,
        [$receiptFor($next362, 'next363-current-source-seal')]
    );

    return [$next356, $next357, $next358, $next359, $next360, $next361, $next362, $next363];
};

$tests['wal hot journal savepoint checkpoint current source next356-363 chains after merged next348-355'] = static function (TestRunner $t) use ($chain): void {
    [$next356, $next357, $next358, $next359, $next360, $next361, $next362, $next363] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next356', $next356['status']);
    $t->same('verify_after_ready_checkpoint_wal_restart_source_receipt_complete', $next356['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next357', $next357['status']);
    $t->same('verify_after_ready_checkpoint_reader_mark_epoch_receipt_complete', $next357['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next358', $next358['status']);
    $t->same('verify_after_ready_checkpoint_page_cache_source_receipt_complete', $next358['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next359', $next359['status']);
    $t->same('verify_after_ready_checkpoint_schema_cookie_source_receipt_complete', $next359['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next360', $next360['status']);
    $t->same('verify_after_ready_checkpoint_commit_generation_source_receipt_complete', $next360['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next361', $next361['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_delete_source_receipt_complete', $next361['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next362', $next362['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_source_token_receipt_complete', $next362['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next363', $next363['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next356_363_complete', $next363['reason']);
    $t->same(['next363-current-source-seal'], $next363['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_restart_source_receipt_next356', implode(',', $next363['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next355', implode(',', $next363['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next363', implode(',', $next363['dependencies']));
    $t->same(true, $next363['after_current_checkpoint_admitted']);
};

$tests['wal hot journal savepoint checkpoint current source next356 blocks missing wal index salt sync'] = static function (TestRunner $t) use ($base355, $receiptFor): void {
    $receipt = $receiptFor($base355, 'next356-unsynced-wal-index');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next356AfterCurrentCheckpoint($base355, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next356', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next357 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [$next356] = $chain();
    $receipt = $receiptFor($next356, 'next357-reader-mark-still-pinned');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next357AfterCurrentCheckpoint($next356, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next357', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next359 blocks stale schema cookie'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next358] = $chain();
    $receipt = $receiptFor($next358, 'next359-stale-schema-cookie');
    $receipt['schema_cookie'] = 1362;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next359AfterCurrentCheckpoint($next358, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next359', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next360 blocks stale commit generation'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , $next359] = $chain();
    $receipt = $receiptFor($next359, 'next360-stale-generation');
    $receipt['commit_generation'] = 362;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next360AfterCurrentCheckpoint($next359, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next360', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next362 blocks duplicate source token receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , $next361] = $chain();
    $receipt = $receiptFor($next361, 'next362-duplicate-source-token');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next362AfterCurrentCheckpoint($next361, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next362', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next362-duplicate-source-token'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next363 rejects missing next362 base'] = static function (TestRunner $t) use ($base355, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next363AfterCurrentCheckpoint(
        array_replace($base355, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next361']),
        [$receiptFor($base355, 'next363-current-source-seal')]
    ));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base331 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next331',
    'database_path' => '/srv/www/wp-content/database/wp-next332.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next332.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next332.sqlite-wal',
    'source_token' => 'wp-next332-339-current-source',
    'database_digest' => $digest('next332-339 checkpoint database image'),
    'page_cache_digest' => $digest('next332-339 checkpoint page cache image'),
    'commit_generation' => 339,
    'schema_cookie' => 1339,
    'checkpoint_frame' => 139,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next324_331_next331'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next331'],
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

$chain = static function () use ($base331, $receiptFor): array {
    $next332 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt(
        $base331,
        [$receiptFor($base331, 'next332-wal-restart-generation')],
        332
    );
    $next333 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt(
        $next332,
        [$receiptFor($next332, 'next333-reader-reopen-token')],
        333
    );
    $next334 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt(
        $next333,
        [$receiptFor($next333, 'next334-savepoint-release-token')],
        334
    );
    $next335 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt(
        $next334,
        [$receiptFor($next334, 'next335-hot-journal-delete-token')],
        335
    );
    $next336 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt(
        $next335,
        [$receiptFor($next335, 'next336-database-header-epoch')],
        336
    );
    $next337 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt(
        $next336,
        [$receiptFor($next336, 'next337-wal-index-reader-boundary')],
        337
    );
    $next338 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt(
        $next337,
        [$receiptFor($next337, 'next338-savepoint-retry-absence-token')],
        338
    );
    $next339 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt(
        $next338,
        [$receiptFor($next338, 'next339-current-source-seal')],
        339
    );

    return [$next332, $next333, $next334, $next335, $next336, $next337, $next338, $next339];
};

$tests['wal hot journal savepoint checkpoint current source next332-339 chains after merged next324-331'] = static function (TestRunner $t) use ($chain): void {
    [$next332, $next333, $next334, $next335, $next336, $next337, $next338, $next339] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next332', $next332['status']);
    $t->same('verify_after_ready_checkpoint_wal_restart_generation_complete', $next332['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next333', $next333['status']);
    $t->same('verify_after_ready_checkpoint_reader_reopen_token_complete', $next333['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next334', $next334['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_release_token_complete', $next334['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next335', $next335['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_delete_token_complete', $next335['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next336', $next336['status']);
    $t->same('verify_after_ready_checkpoint_database_header_epoch_complete', $next336['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next337', $next337['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_reader_boundary_complete', $next337['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next338', $next338['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_retry_absence_token_complete', $next338['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next339', $next339['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next332_339_complete', $next339['reason']);
    $t->same(['next339-current-source-seal'], $next339['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_restart_generation_next332', implode(',', $next339['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next331', implode(',', $next339['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next339', implode(',', $next339['dependencies']));
    $t->same(true, $next339['after_current_checkpoint_admitted']);
};

$tests['wal hot journal savepoint checkpoint current source next332 blocks stale source token'] = static function (TestRunner $t) use ($base331, $receiptFor): void {
    $receipt = $receiptFor($base331, 'next332-stale-source-token');
    $receipt['source_token'] = 'wp-next332-339-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt($base331, [$receipt], 332);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next332', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next335 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next334] = $chain();
    $receipt = $receiptFor($next334, 'next335-visible-hot-journal');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt($next334, [$receipt], 335);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next335', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next337 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next336] = $chain();
    $receipt = $receiptFor($next336, 'next337-duplicate-receipt');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt($next336, [$receipt, $receipt], 337);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next337', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next337-duplicate-receipt'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next339 rejects missing next338 base'] = static function (TestRunner $t) use ($base331, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointReceipt(
        array_replace($base331, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next337']),
        [$receiptFor($base331, 'next339-current-source-seal')],
        339
    ));
};

return $tests;

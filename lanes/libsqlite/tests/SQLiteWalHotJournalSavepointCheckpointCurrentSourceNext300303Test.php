<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base299 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next299',
    'database_path' => '/srv/www/wp-content/database/wp-next300.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next300.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next300.sqlite-wal',
    'source_token' => 'wp-next300-303-current-source',
    'database_digest' => $digest('next300-303 checkpoint database image'),
    'page_cache_digest' => $digest('next300-303 checkpoint page cache image'),
    'commit_generation' => 303,
    'schema_cookie' => 1303,
    'checkpoint_frame' => 103,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next296_299_next299'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next299'],
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

$chain = static function () use ($base299, $receiptFor): array {
    $next300 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        $base299,
        [$receiptFor($base299, 'next300-wal-index-epoch')], 300
    );
    $next301 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        $next300,
        [$receiptFor($next300, 'next301-savepoint-release-receipt')], 301
    );
    $next302 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        $next301,
        [$receiptFor($next301, 'next302-hot-journal-absence-receipt')], 302
    );
    $next303 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        $next302,
        [$receiptFor($next302, 'next303-current-source-seal')], 303
    );

    return [$next300, $next301, $next302, $next303];
};

$tests['wal hot journal savepoint checkpoint current source next300-303 chains after ready next296-299'] = static function (TestRunner $t) use ($chain): void {
    [$next300, $next301, $next302, $next303] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next300', $next300['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_epoch_complete', $next300['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next301', $next301['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_release_receipt_complete', $next301['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next302', $next302['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_absence_receipt_complete', $next302['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next303', $next303['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next300_303_complete', $next303['reason']);
    $t->same(['next303-current-source-seal'], $next303['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_index_epoch_next300', implode(',', $next303['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next299', implode(',', $next303['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next303', implode(',', $next303['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next300 blocks unsynced database header'] = static function (TestRunner $t) use ($base299, $receiptFor): void {
    $receipt = $receiptFor($base299, 'next300-unsynced-database-header');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base299, [$receipt], 300);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next300', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next302 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next301] = $chain();
    $receipt = $receiptFor($next301, 'next302-reader-marks-pinned');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next301, [$receipt], 302);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next302', $record['status']);
    $t->contains('checkpoint_reader_marks_not_released', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next303 rejects missing next302 base'] = static function (TestRunner $t) use ($base299, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base299, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next301']),
        [$receiptFor($base299, 'next303-current-source-seal')], 303
    ));
};

return $tests;

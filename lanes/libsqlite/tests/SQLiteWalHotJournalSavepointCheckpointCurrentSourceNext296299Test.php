<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base295 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next295',
    'database_path' => '/srv/www/wp-content/database/wp-next296.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next296.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next296.sqlite-wal',
    'source_token' => 'wp-next296-299-current-source',
    'database_digest' => $digest('next296-299 checkpoint database image'),
    'page_cache_digest' => $digest('next296-299 checkpoint page cache image'),
    'commit_generation' => 299,
    'schema_cookie' => 1299,
    'checkpoint_frame' => 99,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next292_295_next295'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next295'],
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

$chain = static function () use ($base295, $receiptFor): array {
    $next296 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next296AfterCurrentCheckpoint(
        $base295,
        [$receiptFor($base295, 'next296-wal-header-generation')]
    );
    $next297 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next297AfterCurrentCheckpoint(
        $next296,
        [$receiptFor($next296, 'next297-savepoint-cache-epoch')]
    );
    $next298 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next298AfterCurrentCheckpoint(
        $next297,
        [$receiptFor($next297, 'next298-hot-journal-delete-receipt')]
    );
    $next299 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next299AfterCurrentCheckpoint(
        $next298,
        [$receiptFor($next298, 'next299-current-source-seal')]
    );

    return [$next296, $next297, $next298, $next299];
};

$tests['wal hot journal savepoint checkpoint current source next296-299 chains after next292-295'] = static function (TestRunner $t) use ($chain): void {
    [$next296, $next297, $next298, $next299] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next296', $next296['status']);
    $t->same('verify_after_ready_checkpoint_wal_header_generation_complete', $next296['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next297', $next297['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_cache_epoch_complete', $next297['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next298', $next298['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_delete_receipt_complete', $next298['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next299', $next299['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next296_299_complete', $next299['reason']);
    $t->same(['next299-current-source-seal'], $next299['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_header_generation_next296', implode(',', $next299['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next295', implode(',', $next299['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next299', implode(',', $next299['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next296 blocks visible hot journal'] = static function (TestRunner $t) use ($base295, $receiptFor): void {
    $receipt = $receiptFor($base295, 'next296-visible-hot-journal');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next296AfterCurrentCheckpoint($base295, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next296', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next298 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next297] = $chain();
    $receipt = $receiptFor($next297, 'next298-unsynced-wal-index');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next298AfterCurrentCheckpoint($next297, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next298', $record['status']);
    $t->contains('checkpoint_wal_index_salt_not_synced', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next299 rejects missing next298 base'] = static function (TestRunner $t) use ($base295, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next299AfterCurrentCheckpoint(
        array_replace($base295, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next297']),
        [$receiptFor($base295, 'next299-current-source-seal')]
    ));
};

return $tests;

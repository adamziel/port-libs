<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base323 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next323',
    'database_path' => '/srv/www/wp-content/database/wp-next324.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next324.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next324.sqlite-wal',
    'source_token' => 'wp-next324-331-current-source',
    'database_digest' => $digest('next324-331 checkpoint database image'),
    'page_cache_digest' => $digest('next324-331 checkpoint page cache image'),
    'commit_generation' => 331,
    'schema_cookie' => 1331,
    'checkpoint_frame' => 131,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next316_323_next323'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next323'],
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

$chain = static function () use ($base323, $receiptFor): array {
    $next324 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next324AfterCurrentCheckpoint(
        $base323,
        [$receiptFor($base323, 'next324-wal-index-generation-receipt')]
    );
    $next325 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next325AfterCurrentCheckpoint(
        $next324,
        [$receiptFor($next324, 'next325-reader-reopen-epoch-receipt')]
    );
    $next326 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next326AfterCurrentCheckpoint(
        $next325,
        [$receiptFor($next325, 'next326-savepoint-release-source-receipt')]
    );
    $next327 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next327AfterCurrentCheckpoint(
        $next326,
        [$receiptFor($next326, 'next327-hot-journal-delete-source-receipt')]
    );
    $next328 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next328AfterCurrentCheckpoint(
        $next327,
        [$receiptFor($next327, 'next328-database-page-cache-generation')]
    );
    $next329 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next329AfterCurrentCheckpoint(
        $next328,
        [$receiptFor($next328, 'next329-wal-frame-reader-boundary')]
    );
    $next330 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next330AfterCurrentCheckpoint(
        $next329,
        [$receiptFor($next329, 'next330-savepoint-retry-hot-journal-absence')]
    );
    $next331 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next331AfterCurrentCheckpoint(
        $next330,
        [$receiptFor($next330, 'next331-current-source-seal')]
    );

    return [$next324, $next325, $next326, $next327, $next328, $next329, $next330, $next331];
};

$tests['wal hot journal savepoint checkpoint current source next324-331 chains after ready next316-323'] = static function (TestRunner $t) use ($chain): void {
    [$next324, $next325, $next326, $next327, $next328, $next329, $next330, $next331] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next324', $next324['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_generation_receipt_complete', $next324['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next325', $next325['status']);
    $t->same('verify_after_ready_checkpoint_reader_reopen_epoch_receipt_complete', $next325['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next326', $next326['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_release_source_receipt_complete', $next326['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next327', $next327['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_delete_source_receipt_complete', $next327['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next328', $next328['status']);
    $t->same('verify_after_ready_checkpoint_database_page_cache_generation_complete', $next328['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next329', $next329['status']);
    $t->same('verify_after_ready_checkpoint_wal_frame_reader_boundary_complete', $next329['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next330', $next330['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_retry_hot_journal_absence_complete', $next330['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next331', $next331['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next324_331_complete', $next331['reason']);
    $t->same(['next331-current-source-seal'], $next331['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_index_generation_receipt_next324', implode(',', $next331['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next323', implode(',', $next331['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next331', implode(',', $next331['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next324 blocks stale page cache digest'] = static function (TestRunner $t) use ($base323, $receiptFor): void {
    $receipt = $receiptFor($base323, 'next324-stale-page-cache-digest');
    $receipt['page_cache_digest'] = hash('sha256', 'stale next324 page cache image');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next324AfterCurrentCheckpoint($base323, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next324', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next329 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next328] = $chain();
    $receipt = $receiptFor($next328, 'next329-unsynced-wal-index-salt');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next329AfterCurrentCheckpoint($next328, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next329', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next331 rejects missing next330 base'] = static function (TestRunner $t) use ($base323, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next331AfterCurrentCheckpoint(
        array_replace($base323, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next329']),
        [$receiptFor($base323, 'next331-current-source-seal')]
    ));
};

return $tests;

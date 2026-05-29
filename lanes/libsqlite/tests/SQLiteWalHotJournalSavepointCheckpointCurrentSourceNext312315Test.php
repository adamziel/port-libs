<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base311 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next311',
    'database_path' => '/srv/www/wp-content/database/wp-next312.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next312.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next312.sqlite-wal',
    'source_token' => 'wp-next312-315-current-source',
    'database_digest' => $digest('next312-315 checkpoint database image'),
    'page_cache_digest' => $digest('next312-315 checkpoint page cache image'),
    'commit_generation' => 315,
    'schema_cookie' => 1315,
    'checkpoint_frame' => 115,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next308_311_next311'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next311'],
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

$chain = static function () use ($base311, $receiptFor): array {
    $next312 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next312AfterCurrentCheckpoint(
        $base311,
        [$receiptFor($base311, 'next312-wal-index-frame-range')]
    );
    $next313 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next313AfterCurrentCheckpoint(
        $next312,
        [$receiptFor($next312, 'next313-reader-mark-epoch')]
    );
    $next314 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next314AfterCurrentCheckpoint(
        $next313,
        [$receiptFor($next313, 'next314-savepoint-hot-journal-delete')]
    );
    $next315 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next315AfterCurrentCheckpoint(
        $next314,
        [$receiptFor($next314, 'next315-current-source-seal')]
    );

    return [$next312, $next313, $next314, $next315];
};

$tests['wal hot journal savepoint checkpoint current source next312-315 chains after ready next308-311'] = static function (TestRunner $t) use ($chain): void {
    [$next312, $next313, $next314, $next315] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next312', $next312['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_frame_range_complete', $next312['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next313', $next313['status']);
    $t->same('verify_after_ready_checkpoint_reader_mark_epoch_complete', $next313['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next314', $next314['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_release_hot_journal_delete_complete', $next314['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next315', $next315['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next312_315_complete', $next315['reason']);
    $t->same(['next315-current-source-seal'], $next315['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_index_frame_range_next312', implode(',', $next315['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next311', implode(',', $next315['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next315', implode(',', $next315['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next312 blocks wal digest mismatch'] = static function (TestRunner $t) use ($base311, $receiptFor, $digest): void {
    $receipt = $receiptFor($base311, 'next312-wal-digest-mismatch');
    $receipt['page_cache_digest'] = $digest('stale next312 page cache image');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next312AfterCurrentCheckpoint($base311, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next312', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next314 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next313] = $chain();
    $receipt = $receiptFor($next313, 'next314-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next314AfterCurrentCheckpoint($next313, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next314', $record['status']);
    $t->contains('checkpoint_hot_journal_visible', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next315 rejects missing next314 base'] = static function (TestRunner $t) use ($base311, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next315AfterCurrentCheckpoint(
        array_replace($base311, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next313']),
        [$receiptFor($base311, 'next315-current-source-seal')]
    ));
};

return $tests;

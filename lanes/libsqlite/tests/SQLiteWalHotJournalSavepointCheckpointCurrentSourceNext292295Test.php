<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base291 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next291',
    'database_path' => '/srv/www/wp-content/database/wp-next292.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next292.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next292.sqlite-wal',
    'source_token' => 'wp-next292-295-current-source',
    'database_digest' => $digest('next292-295 checkpoint database image'),
    'page_cache_digest' => $digest('next292-295 checkpoint page cache image'),
    'commit_generation' => 295,
    'schema_cookie' => 1295,
    'checkpoint_frame' => 95,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next288_291_next291'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next291'],
];

$receiptFor = static function (array $base, int $next, string $name): array {
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

$chain = static function () use ($base291, $receiptFor): array {
    $next292 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        $base291,
        [$receiptFor($base291, 292, 'next292-reader-epoch-carry')],
        292
    );
    $next293 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        $next292,
        [$receiptFor($next292, 293, 'next293-savepoint-release-fence')],
        293
    );
    $next294 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        $next293,
        [$receiptFor($next293, 294, 'next294-hot-journal-absence-fence')],
        294
    );
    $next295 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        $next294,
        [$receiptFor($next294, 295, 'next295-current-source-seal')],
        295
    );

    return [$next292, $next293, $next294, $next295];
};

$tests['wal hot journal savepoint checkpoint current source next292-295 chains after ready next288-291'] = static function (TestRunner $t) use ($chain): void {
    [$next292, $next293, $next294, $next295] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next292', $next292['status']);
    $t->same('verify_after_ready_checkpoint_reader_epoch_carry_complete', $next292['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next293', $next293['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_release_fence_complete', $next293['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next294', $next294['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_absence_fence_complete', $next294['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next295', $next295['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next292_295_complete', $next295['reason']);
    $t->same(['next295-current-source-seal'], $next295['accepted_checkpoint_receipt_names']);
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next292', implode(',', $next295['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next295', implode(',', $next295['dependencies']));
    $t->contains('next295 only advances the after-current WAL checkpoint receipt chain', $next295['non_overlap']);
};

$tests['wal hot journal savepoint checkpoint current source next292 blocks stale receipt image'] = static function (TestRunner $t) use ($base291, $receiptFor, $digest): void {
    $receipt = $receiptFor($base291, 292, 'next292-stale-image');
    $receipt['database_digest'] = $digest('stale next292 database image');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base291, [$receipt], 292);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next292', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next295 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next294] = $chain();
    $receipt = $receiptFor($next294, 295, 'next295-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next294, [$receipt, $receipt], 295);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next295', $record['status']);
    $t->contains('checkpoint_receipt_name_duplicate:next295-current-source-seal', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next292 rejects missing ready next291 base'] = static function (TestRunner $t) use ($base291, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base291, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next290']),
        [$receiptFor($base291, 292, 'next292-reader-epoch-carry')],
        292
    ));
};

return $tests;

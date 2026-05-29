<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base379 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next379',
    'database_path' => '/srv/www/wp-content/database/wp-next380.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next380.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next380.sqlite-wal',
    'source_token' => 'wp-next380-387-current-source',
    'database_digest' => $digest('next380-387 checkpoint database image'),
    'page_cache_digest' => $digest('next380-387 checkpoint page cache image'),
    'commit_generation' => 387,
    'schema_cookie' => 1387,
    'checkpoint_frame' => 187,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next372_379_next379'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next379'],
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

$chain = static function () use ($base379, $receiptFor): array {
    $next380 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next380AfterCurrentCheckpoint(
        $base379,
        [$receiptFor($base379, 'next380-restart-salt-source-epoch')]
    );
    $next381 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next381AfterCurrentCheckpoint(
        $next380,
        [$receiptFor($next380, 'next381-reader-mark-release-source')]
    );
    $next382 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next382AfterCurrentCheckpoint(
        $next381,
        [$receiptFor($next381, 'next382-page-cache-source-generation')]
    );
    $next383 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next383AfterCurrentCheckpoint(
        $next382,
        [$receiptFor($next382, 'next383-schema-cookie-source-epoch')]
    );
    $next384 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next384AfterCurrentCheckpoint(
        $next383,
        [$receiptFor($next383, 'next384-commit-generation-source-epoch')]
    );
    $next385 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next385AfterCurrentCheckpoint(
        $next384,
        [$receiptFor($next384, 'next385-hot-journal-delete-source-epoch')]
    );
    $next386 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next386AfterCurrentCheckpoint(
        $next385,
        [$receiptFor($next385, 'next386-wal-index-salt-source-epoch')]
    );
    $next387 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next387AfterCurrentCheckpoint(
        $next386,
        [$receiptFor($next386, 'next387-current-source-seal')]
    );

    return [$next380, $next381, $next382, $next383, $next384, $next385, $next386, $next387];
};

$tests['wal hot journal savepoint checkpoint current source next380-387 chains after merged next372-379'] = static function (TestRunner $t) use ($chain): void {
    [$next380, $next381, $next382, $next383, $next384, $next385, $next386, $next387] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next380', $next380['status']);
    $t->same('verify_after_ready_checkpoint_restart_salt_source_epoch_complete', $next380['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next381', $next381['status']);
    $t->same('verify_after_ready_checkpoint_reader_mark_release_source_complete', $next381['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next382', $next382['status']);
    $t->same('verify_after_ready_checkpoint_page_cache_source_generation_complete', $next382['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next383', $next383['status']);
    $t->same('verify_after_ready_checkpoint_schema_cookie_source_epoch_complete', $next383['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next384', $next384['status']);
    $t->same('verify_after_ready_checkpoint_commit_generation_source_epoch_complete', $next384['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next385', $next385['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_delete_source_epoch_complete', $next385['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next386', $next386['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_salt_source_epoch_complete', $next386['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next387', $next387['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next380_387_complete', $next387['reason']);
    $t->same(['next387-current-source-seal'], $next387['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_restart_salt_source_epoch_next380', implode(',', $next387['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next379', implode(',', $next387['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next387', implode(',', $next387['dependencies']));
    $t->same(true, $next387['after_current_checkpoint_admitted']);
};

$tests['wal hot journal savepoint checkpoint current source next380 blocks unsynced database header'] = static function (TestRunner $t) use ($base379, $receiptFor): void {
    $receipt = $receiptFor($base379, 'next380-unsynced-database-header');
    $receipt['database_header_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next380AfterCurrentCheckpoint($base379, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next380', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next381 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [$next380] = $chain();
    $receipt = $receiptFor($next380, 'next381-duplicate-reader-release');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next381AfterCurrentCheckpoint($next380, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next381', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next381-duplicate-reader-release'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next382 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor, $digest): void {
    [, $next381] = $chain();
    $receipt = $receiptFor($next381, 'next382-stale-page-cache');
    $receipt['page_cache_digest'] = $digest('stale page cache image for next382');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next382AfterCurrentCheckpoint($next381, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next382', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next384 blocks commit generation mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , $next383] = $chain();
    $receipt = $receiptFor($next383, 'next384-stale-commit-generation');
    $receipt['commit_generation'] = 386;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next384AfterCurrentCheckpoint($next383, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next384', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next386 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , $next385] = $chain();
    $receipt = $receiptFor($next385, 'next386-stale-checkpoint-frame');
    $receipt['checkpoint_frame'] = 186;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next386AfterCurrentCheckpoint($next385, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next386', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next387 rejects missing next386 base'] = static function (TestRunner $t) use ($base379, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next387AfterCurrentCheckpoint(
        array_replace($base379, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next385']),
        [$receiptFor($base379, 'next387-current-source-seal')]
    ));
};

return $tests;

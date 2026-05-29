<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base303 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next303',
    'database_path' => '/srv/www/wp-content/database/wp-next304.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next304.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next304.sqlite-wal',
    'source_token' => 'wp-next304-307-current-source',
    'database_digest' => $digest('next304-307 checkpoint database image'),
    'page_cache_digest' => $digest('next304-307 checkpoint page cache image'),
    'commit_generation' => 307,
    'schema_cookie' => 1307,
    'checkpoint_frame' => 107,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next300_303_next303'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next303'],
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

$chain = static function () use ($base303, $receiptFor): array {
    $next304 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next304AfterCurrentCheckpoint(
        $base303,
        [$receiptFor($base303, 'next304-wal-salt-epoch')]
    );
    $next305 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next305AfterCurrentCheckpoint(
        $next304,
        [$receiptFor($next304, 'next305-savepoint-release-epoch')]
    );
    $next306 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next306AfterCurrentCheckpoint(
        $next305,
        [$receiptFor($next305, 'next306-hot-journal-delete-epoch')]
    );
    $next307 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next307AfterCurrentCheckpoint(
        $next306,
        [$receiptFor($next306, 'next307-current-source-seal')]
    );

    return [$next304, $next305, $next306, $next307];
};

$tests['wal hot journal savepoint checkpoint current source next304-307 chains after ready next300-303'] = static function (TestRunner $t) use ($chain): void {
    [$next304, $next305, $next306, $next307] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next304', $next304['status']);
    $t->same('verify_after_ready_checkpoint_wal_salt_epoch_complete', $next304['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next305', $next305['status']);
    $t->same('verify_after_ready_checkpoint_savepoint_release_epoch_complete', $next305['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next306', $next306['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_delete_epoch_complete', $next306['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next307', $next307['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next304_307_complete', $next307['reason']);
    $t->same(['next307-current-source-seal'], $next307['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_wal_salt_epoch_next304', implode(',', $next307['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next303', implode(',', $next307['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next307', implode(',', $next307['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next304 blocks wal salt not synced'] = static function (TestRunner $t) use ($base303, $receiptFor): void {
    $receipt = $receiptFor($base303, 'next304-unsynced-wal-salt');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next304AfterCurrentCheckpoint($base303, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next304', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next306 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next305] = $chain();
    $receipt = $receiptFor($next305, 'next306-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next306AfterCurrentCheckpoint($next305, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next306', $record['status']);
    $t->contains('checkpoint_hot_journal_visible', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next307 rejects missing next306 base'] = static function (TestRunner $t) use ($base303, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next307AfterCurrentCheckpoint(
        array_replace($base303, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next305']),
        [$receiptFor($base303, 'next307-current-source-seal')]
    ));
};

return $tests;

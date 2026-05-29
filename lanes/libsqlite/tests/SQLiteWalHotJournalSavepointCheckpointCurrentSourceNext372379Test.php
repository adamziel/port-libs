<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base371 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next371',
    'database_path' => '/srv/www/wp-content/database/wp-next372.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next372.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next372.sqlite-wal',
    'source_token' => 'wp-next372-379-current-source',
    'database_digest' => $digest('next372-379 checkpoint database image'),
    'page_cache_digest' => $digest('next372-379 checkpoint page cache image'),
    'commit_generation' => 379,
    'schema_cookie' => 1379,
    'checkpoint_frame' => 179,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next364_371_next371'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next371'],
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

$chain = static function () use ($base371, $receiptFor): array {
    $next372 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next372AfterCurrentCheckpoint(
        $base371,
        [$receiptFor($base371, 'next372-restart-salt-epoch-receipt')]
    );
    $next373 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next373AfterCurrentCheckpoint(
        $next372,
        [$receiptFor($next372, 'next373-reader-mark-source-receipt')]
    );
    $next374 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next374AfterCurrentCheckpoint(
        $next373,
        [$receiptFor($next373, 'next374-page-cache-generation-receipt')]
    );
    $next375 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next375AfterCurrentCheckpoint(
        $next374,
        [$receiptFor($next374, 'next375-schema-cookie-source-receipt')]
    );
    $next376 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next376AfterCurrentCheckpoint(
        $next375,
        [$receiptFor($next375, 'next376-commit-generation-source-receipt')]
    );
    $next377 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next377AfterCurrentCheckpoint(
        $next376,
        [$receiptFor($next376, 'next377-hot-journal-delete-epoch-receipt')]
    );
    $next378 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next378AfterCurrentCheckpoint(
        $next377,
        [$receiptFor($next377, 'next378-wal-index-salt-source-receipt')]
    );
    $next379 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next379AfterCurrentCheckpoint(
        $next378,
        [$receiptFor($next378, 'next379-current-source-seal')]
    );

    return [$next372, $next373, $next374, $next375, $next376, $next377, $next378, $next379];
};

$tests['wal hot journal savepoint checkpoint current source next372-379 chains after merged next364-371'] = static function (TestRunner $t) use ($chain): void {
    [$next372, $next373, $next374, $next375, $next376, $next377, $next378, $next379] = $chain();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next372', $next372['status']);
    $t->same('verify_after_ready_checkpoint_restart_salt_epoch_receipt_complete', $next372['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next373', $next373['status']);
    $t->same('verify_after_ready_checkpoint_reader_mark_source_receipt_complete', $next373['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next374', $next374['status']);
    $t->same('verify_after_ready_checkpoint_page_cache_generation_receipt_complete', $next374['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next375', $next375['status']);
    $t->same('verify_after_ready_checkpoint_schema_cookie_source_receipt_complete', $next375['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next376', $next376['status']);
    $t->same('verify_after_ready_checkpoint_commit_generation_source_receipt_complete', $next376['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next377', $next377['status']);
    $t->same('verify_after_ready_checkpoint_hot_journal_delete_epoch_receipt_complete', $next377['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next378', $next378['status']);
    $t->same('verify_after_ready_checkpoint_wal_index_salt_source_receipt_complete', $next378['reason']);
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next379', $next379['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next372_379_complete', $next379['reason']);
    $t->same(['next379-current-source-seal'], $next379['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_restart_salt_epoch_receipt_next372', implode(',', $next379['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next371', implode(',', $next379['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next379', implode(',', $next379['dependencies']));
    $t->same(true, $next379['after_current_checkpoint_admitted']);
};

$tests['wal hot journal savepoint checkpoint current source next372 blocks unsynced wal index salt'] = static function (TestRunner $t) use ($base371, $receiptFor): void {
    $receipt = $receiptFor($base371, 'next372-unsynced-wal-index-salt');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next372AfterCurrentCheckpoint($base371, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next372', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next373 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [$next372] = $chain();
    $receipt = $receiptFor($next372, 'next373-reader-mark-still-pinned');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next373AfterCurrentCheckpoint($next372, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next373', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next374 blocks database digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor, $digest): void {
    [, $next373] = $chain();
    $receipt = $receiptFor($next373, 'next374-stale-database-digest');
    $receipt['database_digest'] = $digest('stale database image for next374');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next374AfterCurrentCheckpoint($next373, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next374', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next375 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next374] = $chain();
    $receipt = $receiptFor($next374, 'next375-stale-schema-cookie');
    $receipt['schema_cookie'] = 1378;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next375AfterCurrentCheckpoint($next374, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next375', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next377 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next376] = $chain();
    $receipt = $receiptFor($next376, 'next377-visible-hot-journal');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next377AfterCurrentCheckpoint($next376, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next377', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next379 rejects missing next378 base'] = static function (TestRunner $t) use ($base371, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next379AfterCurrentCheckpoint(
        array_replace($base371, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next377']),
        [$receiptFor($base371, 'next379-current-source-seal')]
    ));
};

return $tests;

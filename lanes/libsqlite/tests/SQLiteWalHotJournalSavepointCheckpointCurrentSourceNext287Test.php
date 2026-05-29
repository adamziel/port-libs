<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next286',
    'database_path' => '/srv/www/wp-content/database/wp-next287.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next287.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next287.sqlite-wal',
    'source_token' => 'wp-next287-current-source',
    'database_digest' => $digest('next287 database header'),
    'page_cache_digest' => $digest('next287 page cache'),
    'commit_generation' => 287,
    'schema_cookie' => 1287,
    'checkpoint_frame' => 287,
    'operation_names' => ['verify_after_ready_checkpoint_page_cache_digest_carry_next286'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next286'],
];
$receipt = [
    'name' => 'next287-current-source-window-seal',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 287,
    'schema_cookie' => 1287,
    'checkpoint_frame' => 287,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next287AfterCurrentCheckpoint($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next287 seals current source window'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next287', $record['status']);
    $t->same('seal_after_ready_checkpoint_current_source_next_window_complete', $record['reason']);
    $t->same(['next287-current-source-window-seal'], $record['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next_window_next287', implode(',', $record['operation_names']));
    $t->contains('next287 only advances the after-current WAL checkpoint receipt chain', $record['non_overlap']);
};

$tests['wal hot journal savepoint checkpoint current source next287 blocks duplicate receipts'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $record = $plan(receipts: [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next287', $record['status']);
    $t->contains('checkpoint_receipt_name_duplicate:next287-current-source-window-seal', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next287 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next285'])));
};

return $tests;

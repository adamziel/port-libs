<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next285',
    'database_path' => '/srv/www/wp-content/database/wp-next286.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next286.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next286.sqlite-wal',
    'source_token' => 'wp-next286-current-source',
    'database_digest' => $digest('next286 database header'),
    'page_cache_digest' => $digest('next286 page cache'),
    'commit_generation' => 286,
    'schema_cookie' => 1286,
    'checkpoint_frame' => 286,
    'operation_names' => ['verify_after_ready_checkpoint_reader_mark_release_next285'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next285'],
];
$receipt = [
    'name' => 'next286-page-cache-digest-carry',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 286,
    'schema_cookie' => 1286,
    'checkpoint_frame' => 286,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next286AfterCurrentCheckpoint($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next286 verifies page cache digest carry'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next286', $record['status']);
    $t->same('verify_after_ready_checkpoint_page_cache_digest_carry_complete', $record['reason']);
    $t->same(['next286-page-cache-digest-carry'], $record['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_page_cache_digest_carry_next286', implode(',', $record['operation_names']));
};

$tests['wal hot journal savepoint checkpoint current source next286 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($plan, $receipt, $digest): void {
    $badReceipt = array_replace($receipt, ['page_cache_digest' => $digest('stale next286 page cache')]);
    $record = $plan(receipts: [$badReceipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next286', $record['status']);
    $t->contains('checkpoint_page_cache_digest_mismatch', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next286 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next284'])));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next269',
    'database_path' => '/srv/www/wp-content/database/wp-next270.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next270.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next270.sqlite-wal',
    'source_token' => 'wp-next270-current-source',
    'database_digest' => $digest('next270 database header'),
    'page_cache_digest' => $digest('next270 page cache'),
    'commit_generation' => 270,
    'schema_cookie' => 1270,
    'checkpoint_frame' => 270,
    'operation_names' => ['verify_reader_marks_after_wal_index_salt_next266'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next269'],
];
$receipt = [
    'name' => 'next270-after-current-seal',
    'source_token' => $base['source_token'],
    'database_digest' => $base['database_digest'],
    'page_cache_digest' => $base['page_cache_digest'],
    'commit_generation' => 270,
    'schema_cookie' => 1270,
    'checkpoint_frame' => 270,
    'database_header_synced' => true,
    'wal_index_salt_synced' => true,
    'reader_marks_released' => true,
    'hot_journal_visible' => false,
];

$plan = static fn (?array $inputBase = null, ?array $receipts = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next270AfterCurrentCheckpoint($inputBase ?? $base, $receipts ?? [$receipt]);

$tests['wal hot journal savepoint checkpoint current source next270 seals after-current chain'] = static function (TestRunner $t) use ($plan): void {
    $record = $plan();

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next270', $record['status']);
    $t->same('verify_after_current_checkpoint_hot_journal_retired_complete', $record['reason']);
    $t->same(['next270-after-current-seal'], $record['accepted_checkpoint_receipt_names']);
    $t->same([], $record['blocked_reasons']);
    $t->contains('verify_after_current_checkpoint_hot_journal_retired_next270', implode(',', $record['operation_names']));
    $t->contains('does not repeat next260 admission', $record['non_overlap']);
};

$tests['wal hot journal savepoint checkpoint current source next270 blocks duplicate receipts'] = static function (TestRunner $t) use ($plan, $receipt): void {
    $record = $plan(receipts: [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next270', $record['status']);
    $t->same(['next270-after-current-seal'], $record['duplicate_checkpoint_receipt_names']);
    $t->contains('checkpoint_receipt_name_duplicate:next270-after-current-seal', implode(',', $record['blocked_reasons']));
};

$tests['wal hot journal savepoint checkpoint current source next270 rejects wrong base'] = static function (TestRunner $t) use ($plan, $base): void {
    $t->throws(Throwable::class, static fn () => $plan(array_replace($base, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next268'])));
};

return $tests;

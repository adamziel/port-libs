<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base387 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next387',
    'database_path' => '/srv/www/wp-content/database/wp-next388.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next388.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next388.sqlite-wal',
    'source_token' => 'wp-next388-403-current-source',
    'database_digest' => $digest('next388-403 checkpoint database image'),
    'page_cache_digest' => $digest('next388-403 checkpoint page cache image'),
    'commit_generation' => 403,
    'schema_cookie' => 1403,
    'checkpoint_frame' => 203,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next380_387_next387'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next387'],
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

$chain = static function () use ($base387, $receiptFor): array {
    $next388 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next388AfterCurrentCheckpoint($base387, [$receiptFor($base387, 'next388-restart-salt-receipt-generation')]);
    $next389 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next389AfterCurrentCheckpoint($next388, [$receiptFor($next388, 'next389-reader-mark-release-generation')]);
    $next390 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next390AfterCurrentCheckpoint($next389, [$receiptFor($next389, 'next390-page-cache-source-token-generation')]);
    $next391 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next391AfterCurrentCheckpoint($next390, [$receiptFor($next390, 'next391-schema-cookie-source-generation')]);
    $next392 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next392AfterCurrentCheckpoint($next391, [$receiptFor($next391, 'next392-commit-generation-source-token')]);
    $next393 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next393AfterCurrentCheckpoint($next392, [$receiptFor($next392, 'next393-hot-journal-absence-generation')]);
    $next394 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next394AfterCurrentCheckpoint($next393, [$receiptFor($next393, 'next394-wal-index-salt-generation')]);
    $next395 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next395AfterCurrentCheckpoint($next394, [$receiptFor($next394, 'next395-current-source-seal')]);
    $next396 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next396AfterCurrentCheckpoint($next395, [$receiptFor($next395, 'next396-restart-salt-receipt-source-token')]);
    $next397 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next397AfterCurrentCheckpoint($next396, [$receiptFor($next396, 'next397-reader-mark-release-source-token')]);
    $next398 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next398AfterCurrentCheckpoint($next397, [$receiptFor($next397, 'next398-page-cache-digest-generation')]);
    $next399 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next399AfterCurrentCheckpoint($next398, [$receiptFor($next398, 'next399-schema-cookie-digest-generation')]);
    $next400 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next400AfterCurrentCheckpoint($next399, [$receiptFor($next399, 'next400-commit-generation-digest-source')]);
    $next401 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next401AfterCurrentCheckpoint($next400, [$receiptFor($next400, 'next401-hot-journal-delete-digest-generation')]);
    $next402 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next402AfterCurrentCheckpoint($next401, [$receiptFor($next401, 'next402-wal-index-salt-digest-generation')]);
    $next403 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next403AfterCurrentCheckpoint($next402, [$receiptFor($next402, 'next403-current-source-seal')]);

    return [$next388, $next389, $next390, $next391, $next392, $next393, $next394, $next395, $next396, $next397, $next398, $next399, $next400, $next401, $next402, $next403];
};

$tests['wal hot journal savepoint checkpoint current source next388-403 chains after merged next380-387'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        388 => 'verify_after_ready_checkpoint_restart_salt_receipt_generation_complete',
        389 => 'verify_after_ready_checkpoint_reader_mark_release_generation_complete',
        390 => 'verify_after_ready_checkpoint_page_cache_source_token_generation_complete',
        391 => 'verify_after_ready_checkpoint_schema_cookie_source_generation_complete',
        392 => 'verify_after_ready_checkpoint_commit_generation_source_token_complete',
        393 => 'verify_after_ready_checkpoint_hot_journal_absence_generation_complete',
        394 => 'verify_after_ready_checkpoint_wal_index_salt_generation_complete',
        395 => 'seal_after_ready_checkpoint_current_source_next388_395_complete',
        396 => 'verify_after_ready_checkpoint_restart_salt_receipt_source_token_complete',
        397 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        398 => 'verify_after_ready_checkpoint_page_cache_digest_generation_complete',
        399 => 'verify_after_ready_checkpoint_schema_cookie_digest_generation_complete',
        400 => 'verify_after_ready_checkpoint_commit_generation_digest_source_complete',
        401 => 'verify_after_ready_checkpoint_hot_journal_delete_digest_generation_complete',
        402 => 'verify_after_ready_checkpoint_wal_index_salt_digest_generation_complete',
        403 => 'seal_after_ready_checkpoint_current_source_next396_403_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 388];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next403 = $chainRows[15];
    $t->same(['next403-current-source-seal'], $next403['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_restart_salt_receipt_generation_next388', implode(',', $next403['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next388_395_next395', implode(',', $next403['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next387', implode(',', $next403['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next403', implode(',', $next403['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next388 blocks wal index salt not synced'] = static function (TestRunner $t) use ($base387, $receiptFor): void {
    $receipt = $receiptFor($base387, 'next388-unsynced-wal-index-salt');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next388AfterCurrentCheckpoint($base387, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next388', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next390 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next389] = $chain();
    $receipt = $receiptFor($next389, 'next390-wrong-source-token');
    $receipt['source_token'] = 'wrong-next390-source-token';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next390AfterCurrentCheckpoint($next389, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next390', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next393 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next392] = $chain();
    $receipt = $receiptFor($next392, 'next393-visible-hot-journal');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next393AfterCurrentCheckpoint($next392, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next393', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next395 rejects missing next394 base'] = static function (TestRunner $t) use ($base387, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next395AfterCurrentCheckpoint(
        array_replace($base387, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next393']),
        [$receiptFor($base387, 'next395-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next398 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor, $digest): void {
    [, , , , , , , , , $next397] = $chain();
    $receipt = $receiptFor($next397, 'next398-stale-page-cache-digest');
    $receipt['page_cache_digest'] = $digest('stale page cache digest next398');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next398AfterCurrentCheckpoint($next397, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next398', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next400 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , $next399] = $chain();
    $receipt = $receiptFor($next399, 'next400-stale-schema-cookie');
    $receipt['schema_cookie'] = 1402;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next400AfterCurrentCheckpoint($next399, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next400', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next403 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next402] = $chain();
    $receipt = $receiptFor($next402, 'next403-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next403AfterCurrentCheckpoint($next402, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next403', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next403-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

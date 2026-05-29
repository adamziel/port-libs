<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base899 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next899',
    'database_path' => '/srv/www/wp-content/database/wp-next900.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next900.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next900.sqlite-wal',
    'source_token' => 'wp-next900-915-current-source',
    'database_digest' => $digest('next900-915 checkpoint database image'),
    'page_cache_digest' => $digest('next900-915 checkpoint page cache image'),
    'commit_generation' => 900,
    'schema_cookie' => 1900,
    'checkpoint_frame' => 700,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next876_883_next883',
        'seal_after_ready_checkpoint_current_source_next884_891_next891',
        'seal_after_ready_checkpoint_current_source_next892_899_next899',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next883',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next899',
    ],
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

$chain = static function () use ($base899, $receiptFor): array {
    $next900 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next900AfterCurrentCheckpoint($base899, [$receiptFor($base899, 'next900-restart-salt-database-digest')]);
    $next901 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next901AfterCurrentCheckpoint($next900, [$receiptFor($next900, 'next901-reader-release-checkpoint-frame')]);
    $next902 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next902AfterCurrentCheckpoint($next901, [$receiptFor($next901, 'next902-page-cache-source-token')]);
    $next903 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next903AfterCurrentCheckpoint($next902, [$receiptFor($next902, 'next903-schema-cookie-database-header')]);
    $next904 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next904AfterCurrentCheckpoint($next903, [$receiptFor($next903, 'next904-commit-generation-wal-index')]);
    $next905 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next905AfterCurrentCheckpoint($next904, [$receiptFor($next904, 'next905-hot-journal-reader-release')]);
    $next906 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next906AfterCurrentCheckpoint($next905, [$receiptFor($next905, 'next906-wal-index-page-cache')]);
    $next907 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next907AfterCurrentCheckpoint($next906, [$receiptFor($next906, 'next907-current-source-seal')]);
    $next908 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next908AfterCurrentCheckpoint($next907, [$receiptFor($next907, 'next908-restart-salt-database-header')]);
    $next909 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next909AfterCurrentCheckpoint($next908, [$receiptFor($next908, 'next909-reader-release-source-token')]);
    $next910 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next910AfterCurrentCheckpoint($next909, [$receiptFor($next909, 'next910-page-cache-database-digest')]);
    $next911 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next911AfterCurrentCheckpoint($next910, [$receiptFor($next910, 'next911-checkpoint-frame-schema-cookie')]);
    $next912 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next912AfterCurrentCheckpoint($next911, [$receiptFor($next911, 'next912-commit-generation-checkpoint-frame')]);
    $next913 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next913AfterCurrentCheckpoint($next912, [$receiptFor($next912, 'next913-hot-journal-page-cache')]);
    $next914 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next914AfterCurrentCheckpoint($next913, [$receiptFor($next913, 'next914-wal-index-reader-release')]);
    $next915 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next915AfterCurrentCheckpoint($next914, [$receiptFor($next914, 'next915-current-source-seal')]);

    return [$next900, $next901, $next902, $next903, $next904, $next905, $next906, $next907, $next908, $next909, $next910, $next911, $next912, $next913, $next914, $next915];
};

$tests['wal hot journal savepoint checkpoint current source next900-915 chains from next899'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        900 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        901 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        902 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        903 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        904 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        905 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        906 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        907 => 'seal_after_ready_checkpoint_current_source_next900_907_complete',
        908 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        909 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        910 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        911 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        912 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        913 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        914 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        915 => 'seal_after_ready_checkpoint_current_source_next908_915_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 900];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next900 = $chainRows[0];
    $next915 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next899', $next900['base_status']);
    $t->same(['next915-current-source-seal'], $next915['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next884_891_next891', implode(',', $next915['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next892_899_next899', implode(',', $next915['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next900_907_next907', implode(',', $next915['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next899', implode(',', $next915['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next915', implode(',', $next915['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next900 rejects missing next899 handoff'] = static function (TestRunner $t) use ($base899, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next900AfterCurrentCheckpoint(
        array_replace($base899, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next898']),
        [$receiptFor($base899, 'next900-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next902 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next901] = $chain();
    $receipt = $receiptFor($next901, 'next902-source-token-mismatch');
    $receipt['source_token'] = 'wp-next902-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next902AfterCurrentCheckpoint($next901, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next902', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next905 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next904] = $chain();
    $receipt = $receiptFor($next904, 'next905-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next905AfterCurrentCheckpoint($next904, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next905', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next907 rejects missing next906 base'] = static function (TestRunner $t) use ($base899, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next907AfterCurrentCheckpoint(
        array_replace($base899, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next905']),
        [$receiptFor($base899, 'next907-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next911 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next910] = $chain();
    $receipt = $receiptFor($next910, 'next911-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next911AfterCurrentCheckpoint($next910, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next911', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next915 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next914] = $chain();
    $receipt = $receiptFor($next914, 'next915-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next915AfterCurrentCheckpoint($next914, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next915', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next915-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

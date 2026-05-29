<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base931 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next931',
    'database_path' => '/srv/www/wp-content/database/wp-next932.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next932.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next932.sqlite-wal',
    'source_token' => 'wp-next932-947-current-source',
    'database_digest' => $digest('next932-947 checkpoint database image'),
    'page_cache_digest' => $digest('next932-947 checkpoint page cache image'),
    'commit_generation' => 932,
    'schema_cookie' => 1932,
    'checkpoint_frame' => 732,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next908_915_next915',
        'seal_after_ready_checkpoint_current_source_next916_923_next923',
        'seal_after_ready_checkpoint_current_source_next924_931_next931',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next915',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next931',
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

$chain = static function () use ($base931, $receiptFor): array {
    $next932 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next932AfterCurrentCheckpoint($base931, [$receiptFor($base931, 'next932-restart-salt-database-digest')]);
    $next933 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next933AfterCurrentCheckpoint($next932, [$receiptFor($next932, 'next933-reader-release-checkpoint-frame')]);
    $next934 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next934AfterCurrentCheckpoint($next933, [$receiptFor($next933, 'next934-page-cache-source-token')]);
    $next935 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next935AfterCurrentCheckpoint($next934, [$receiptFor($next934, 'next935-schema-cookie-database-header')]);
    $next936 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next936AfterCurrentCheckpoint($next935, [$receiptFor($next935, 'next936-commit-generation-wal-index')]);
    $next937 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next937AfterCurrentCheckpoint($next936, [$receiptFor($next936, 'next937-hot-journal-reader-release')]);
    $next938 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next938AfterCurrentCheckpoint($next937, [$receiptFor($next937, 'next938-wal-index-page-cache')]);
    $next939 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next939AfterCurrentCheckpoint($next938, [$receiptFor($next938, 'next939-current-source-seal')]);
    $next940 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next940AfterCurrentCheckpoint($next939, [$receiptFor($next939, 'next940-restart-salt-database-header')]);
    $next941 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next941AfterCurrentCheckpoint($next940, [$receiptFor($next940, 'next941-reader-release-source-token')]);
    $next942 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next942AfterCurrentCheckpoint($next941, [$receiptFor($next941, 'next942-page-cache-database-digest')]);
    $next943 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next943AfterCurrentCheckpoint($next942, [$receiptFor($next942, 'next943-checkpoint-frame-schema-cookie')]);
    $next944 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next944AfterCurrentCheckpoint($next943, [$receiptFor($next943, 'next944-commit-generation-checkpoint-frame')]);
    $next945 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next945AfterCurrentCheckpoint($next944, [$receiptFor($next944, 'next945-hot-journal-page-cache')]);
    $next946 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next946AfterCurrentCheckpoint($next945, [$receiptFor($next945, 'next946-wal-index-reader-release')]);
    $next947 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next947AfterCurrentCheckpoint($next946, [$receiptFor($next946, 'next947-current-source-seal')]);

    return [$next932, $next933, $next934, $next935, $next936, $next937, $next938, $next939, $next940, $next941, $next942, $next943, $next944, $next945, $next946, $next947];
};

$tests['wal hot journal savepoint checkpoint current source next932-947 chains from next931'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        932 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        933 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        934 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        935 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        936 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        937 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        938 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        939 => 'seal_after_ready_checkpoint_current_source_next932_939_complete',
        940 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        941 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        942 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        943 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        944 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        945 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        946 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        947 => 'seal_after_ready_checkpoint_current_source_next940_947_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 932];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next932 = $chainRows[0];
    $next947 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next931', $next932['base_status']);
    $t->same(['next947-current-source-seal'], $next947['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next916_923_next923', implode(',', $next947['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next924_931_next931', implode(',', $next947['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next932_939_next939', implode(',', $next947['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next931', implode(',', $next947['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next947', implode(',', $next947['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next932 rejects missing next931 handoff'] = static function (TestRunner $t) use ($base931, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next932AfterCurrentCheckpoint(
        array_replace($base931, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next930']),
        [$receiptFor($base931, 'next932-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next934 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next933] = $chain();
    $receipt = $receiptFor($next933, 'next934-source-token-mismatch');
    $receipt['source_token'] = 'wp-next934-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next934AfterCurrentCheckpoint($next933, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next934', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next939 rejects missing next938 base'] = static function (TestRunner $t) use ($base931, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next939AfterCurrentCheckpoint(
        array_replace($base931, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next937']),
        [$receiptFor($base931, 'next939-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next943 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next942] = $chain();
    $receipt = $receiptFor($next942, 'next943-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next943AfterCurrentCheckpoint($next942, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next943', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next947 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next946] = $chain();
    $receipt = $receiptFor($next946, 'next947-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next947AfterCurrentCheckpoint($next946, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next947', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next947-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

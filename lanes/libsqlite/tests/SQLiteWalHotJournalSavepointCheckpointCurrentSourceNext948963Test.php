<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base947 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next947',
    'database_path' => '/srv/www/wp-content/database/wp-next948.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next948.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next948.sqlite-wal',
    'source_token' => 'wp-next948-963-current-source',
    'database_digest' => $digest('next948-963 checkpoint database image'),
    'page_cache_digest' => $digest('next948-963 checkpoint page cache image'),
    'commit_generation' => 948,
    'schema_cookie' => 1948,
    'checkpoint_frame' => 748,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next924_931_next931',
        'seal_after_ready_checkpoint_current_source_next932_939_next939',
        'seal_after_ready_checkpoint_current_source_next940_947_next947',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next931',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next947',
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

$chain = static function () use ($base947, $receiptFor): array {
    $next948 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next948AfterCurrentCheckpoint($base947, [$receiptFor($base947, 'next948-restart-salt-database-digest')]);
    $next949 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next949AfterCurrentCheckpoint($next948, [$receiptFor($next948, 'next949-reader-release-checkpoint-frame')]);
    $next950 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next950AfterCurrentCheckpoint($next949, [$receiptFor($next949, 'next950-page-cache-source-token')]);
    $next951 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next951AfterCurrentCheckpoint($next950, [$receiptFor($next950, 'next951-schema-cookie-database-header')]);
    $next952 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next952AfterCurrentCheckpoint($next951, [$receiptFor($next951, 'next952-commit-generation-wal-index')]);
    $next953 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next953AfterCurrentCheckpoint($next952, [$receiptFor($next952, 'next953-hot-journal-reader-release')]);
    $next954 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next954AfterCurrentCheckpoint($next953, [$receiptFor($next953, 'next954-wal-index-page-cache')]);
    $next955 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next955AfterCurrentCheckpoint($next954, [$receiptFor($next954, 'next955-current-source-seal')]);
    $next956 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next956AfterCurrentCheckpoint($next955, [$receiptFor($next955, 'next956-restart-salt-database-header')]);
    $next957 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next957AfterCurrentCheckpoint($next956, [$receiptFor($next956, 'next957-reader-release-source-token')]);
    $next958 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next958AfterCurrentCheckpoint($next957, [$receiptFor($next957, 'next958-page-cache-database-digest')]);
    $next959 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next959AfterCurrentCheckpoint($next958, [$receiptFor($next958, 'next959-checkpoint-frame-schema-cookie')]);
    $next960 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next960AfterCurrentCheckpoint($next959, [$receiptFor($next959, 'next960-commit-generation-checkpoint-frame')]);
    $next961 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next961AfterCurrentCheckpoint($next960, [$receiptFor($next960, 'next961-hot-journal-page-cache')]);
    $next962 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next962AfterCurrentCheckpoint($next961, [$receiptFor($next961, 'next962-wal-index-reader-release')]);
    $next963 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next963AfterCurrentCheckpoint($next962, [$receiptFor($next962, 'next963-current-source-seal')]);

    return [$next948, $next949, $next950, $next951, $next952, $next953, $next954, $next955, $next956, $next957, $next958, $next959, $next960, $next961, $next962, $next963];
};

$tests['wal hot journal savepoint checkpoint current source next948-963 chains from next947'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        948 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        949 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        950 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        951 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        952 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        953 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        954 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        955 => 'seal_after_ready_checkpoint_current_source_next948_955_complete',
        956 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        957 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        958 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        959 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        960 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        961 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        962 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        963 => 'seal_after_ready_checkpoint_current_source_next956_963_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 948];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next948 = $chainRows[0];
    $next963 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next947', $next948['base_status']);
    $t->same(['next963-current-source-seal'], $next963['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next932_939_next939', implode(',', $next963['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next940_947_next947', implode(',', $next963['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next948_955_next955', implode(',', $next963['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next947', implode(',', $next963['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next963', implode(',', $next963['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next948 rejects missing next947 handoff'] = static function (TestRunner $t) use ($base947, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next948AfterCurrentCheckpoint(
        array_replace($base947, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next946']),
        [$receiptFor($base947, 'next948-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next950 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor, $digest): void {
    [, $next949] = $chain();
    $receipt = $receiptFor($next949, 'next950-page-cache-mismatch');
    $receipt['page_cache_digest'] = $digest('stale next950 page cache');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next950AfterCurrentCheckpoint($next949, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next950', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next955 rejects missing next954 base'] = static function (TestRunner $t) use ($base947, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next955AfterCurrentCheckpoint(
        array_replace($base947, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next953']),
        [$receiptFor($base947, 'next955-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next959 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next958] = $chain();
    $receipt = $receiptFor($next958, 'next959-schema-cookie-mismatch');
    $receipt['schema_cookie']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next959AfterCurrentCheckpoint($next958, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next959', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next963 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next962] = $chain();
    $receipt = $receiptFor($next962, 'next963-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next963AfterCurrentCheckpoint($next962, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next963', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base883 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next883',
    'database_path' => '/srv/www/wp-content/database/wp-next884.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next884.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next884.sqlite-wal',
    'source_token' => 'wp-next884-899-current-source',
    'database_digest' => $digest('next884-899 checkpoint database image'),
    'page_cache_digest' => $digest('next884-899 checkpoint page cache image'),
    'commit_generation' => 884,
    'schema_cookie' => 1884,
    'checkpoint_frame' => 684,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next852_859_next859',
        'seal_after_ready_checkpoint_current_source_next860_867_next867',
        'seal_after_ready_checkpoint_current_source_next868_875_next875',
        'seal_after_ready_checkpoint_current_source_next876_883_next883',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next867',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next883',
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

$chain = static function () use ($base883, $receiptFor): array {
    $next884 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next884AfterCurrentCheckpoint($base883, [$receiptFor($base883, 'next884-restart-salt-database-digest')]);
    $next885 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next885AfterCurrentCheckpoint($next884, [$receiptFor($next884, 'next885-reader-release-checkpoint-frame')]);
    $next886 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next886AfterCurrentCheckpoint($next885, [$receiptFor($next885, 'next886-page-cache-source-token')]);
    $next887 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next887AfterCurrentCheckpoint($next886, [$receiptFor($next886, 'next887-schema-cookie-database-header')]);
    $next888 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next888AfterCurrentCheckpoint($next887, [$receiptFor($next887, 'next888-commit-generation-wal-index')]);
    $next889 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next889AfterCurrentCheckpoint($next888, [$receiptFor($next888, 'next889-hot-journal-reader-release')]);
    $next890 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next890AfterCurrentCheckpoint($next889, [$receiptFor($next889, 'next890-wal-index-page-cache')]);
    $next891 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next891AfterCurrentCheckpoint($next890, [$receiptFor($next890, 'next891-current-source-seal')]);
    $next892 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next892AfterCurrentCheckpoint($next891, [$receiptFor($next891, 'next892-restart-salt-database-header')]);
    $next893 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next893AfterCurrentCheckpoint($next892, [$receiptFor($next892, 'next893-reader-release-source-token')]);
    $next894 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next894AfterCurrentCheckpoint($next893, [$receiptFor($next893, 'next894-page-cache-database-digest')]);
    $next895 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next895AfterCurrentCheckpoint($next894, [$receiptFor($next894, 'next895-checkpoint-frame-schema-cookie')]);
    $next896 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next896AfterCurrentCheckpoint($next895, [$receiptFor($next895, 'next896-commit-generation-checkpoint-frame')]);
    $next897 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next897AfterCurrentCheckpoint($next896, [$receiptFor($next896, 'next897-hot-journal-page-cache')]);
    $next898 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next898AfterCurrentCheckpoint($next897, [$receiptFor($next897, 'next898-wal-index-reader-release')]);
    $next899 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next899AfterCurrentCheckpoint($next898, [$receiptFor($next898, 'next899-current-source-seal')]);

    return [$next884, $next885, $next886, $next887, $next888, $next889, $next890, $next891, $next892, $next893, $next894, $next895, $next896, $next897, $next898, $next899];
};

$tests['wal hot journal savepoint checkpoint current source next884-899 receives checkpoint handoff from next883'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        884 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        885 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        886 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        887 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        888 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        889 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        890 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        891 => 'seal_after_ready_checkpoint_current_source_next884_891_complete',
        892 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        893 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        894 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        895 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        896 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        897 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        898 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        899 => 'seal_after_ready_checkpoint_current_source_next892_899_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 884];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next884 = $chainRows[0];
    $next899 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next883', $next884['base_status']);
    $t->same(['next899-current-source-seal'], $next899['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next868_875_next875', implode(',', $next899['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next876_883_next883', implode(',', $next899['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next884_891_next891', implode(',', $next899['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next883', implode(',', $next899['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next899', implode(',', $next899['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next884 rejects missing next883 handoff'] = static function (TestRunner $t) use ($base883, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next884AfterCurrentCheckpoint(
        array_replace($base883, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next882']),
        [$receiptFor($base883, 'next884-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next886 blocks page cache mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next885] = $chain();
    $receipt = $receiptFor($next885, 'next886-page-cache-mismatch');
    $receipt['page_cache_digest'] = hash('sha256', 'stale page cache');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next886AfterCurrentCheckpoint($next885, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next886', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next889 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next888] = $chain();
    $receipt = $receiptFor($next888, 'next889-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next889AfterCurrentCheckpoint($next888, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next889', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next891 rejects missing next890 base'] = static function (TestRunner $t) use ($base883, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next891AfterCurrentCheckpoint(
        array_replace($base883, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next889']),
        [$receiptFor($base883, 'next891-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next895 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next894] = $chain();
    $receipt = $receiptFor($next894, 'next895-schema-cookie-mismatch');
    $receipt['schema_cookie']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next895AfterCurrentCheckpoint($next894, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next895', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next899 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next898] = $chain();
    $receipt = $receiptFor($next898, 'next899-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next899AfterCurrentCheckpoint($next898, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next899', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next899-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

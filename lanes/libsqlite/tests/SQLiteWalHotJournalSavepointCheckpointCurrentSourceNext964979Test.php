<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base963 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next963',
    'database_path' => '/srv/www/wp-content/database/wp-next964.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next964.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next964.sqlite-wal',
    'source_token' => 'wp-next964-979-current-source',
    'database_digest' => $digest('next964-979 checkpoint database image'),
    'page_cache_digest' => $digest('next964-979 checkpoint page cache image'),
    'commit_generation' => 964,
    'schema_cookie' => 1964,
    'checkpoint_frame' => 764,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next940_947_next947',
        'seal_after_ready_checkpoint_current_source_next948_955_next955',
        'seal_after_ready_checkpoint_current_source_next956_963_next963',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next947',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next963',
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

$chain = static function () use ($base963, $receiptFor): array {
    $next964 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next964AfterCurrentCheckpoint($base963, [$receiptFor($base963, 'next964-restart-salt-source-token')]);
    $next965 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next965AfterCurrentCheckpoint($next964, [$receiptFor($next964, 'next965-reader-release-database-digest')]);
    $next966 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next966AfterCurrentCheckpoint($next965, [$receiptFor($next965, 'next966-page-cache-schema-cookie')]);
    $next967 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next967AfterCurrentCheckpoint($next966, [$receiptFor($next966, 'next967-checkpoint-frame-wal-index')]);
    $next968 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next968AfterCurrentCheckpoint($next967, [$receiptFor($next967, 'next968-commit-generation-database-header')]);
    $next969 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next969AfterCurrentCheckpoint($next968, [$receiptFor($next968, 'next969-hot-journal-source-token')]);
    $next970 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next970AfterCurrentCheckpoint($next969, [$receiptFor($next969, 'next970-reader-release-page-cache')]);
    $next971 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next971AfterCurrentCheckpoint($next970, [$receiptFor($next970, 'next971-current-source-seal')]);
    $next972 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next972AfterCurrentCheckpoint($next971, [$receiptFor($next971, 'next972-restart-salt-checkpoint-frame')]);
    $next973 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next973AfterCurrentCheckpoint($next972, [$receiptFor($next972, 'next973-reader-release-schema-cookie')]);
    $next974 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next974AfterCurrentCheckpoint($next973, [$receiptFor($next973, 'next974-page-cache-wal-index')]);
    $next975 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next975AfterCurrentCheckpoint($next974, [$receiptFor($next974, 'next975-database-digest-commit-generation')]);
    $next976 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next976AfterCurrentCheckpoint($next975, [$receiptFor($next975, 'next976-database-header-checkpoint-frame')]);
    $next977 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next977AfterCurrentCheckpoint($next976, [$receiptFor($next976, 'next977-hot-journal-reader-release')]);
    $next978 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next978AfterCurrentCheckpoint($next977, [$receiptFor($next977, 'next978-wal-index-database-digest')]);
    $next979 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next979AfterCurrentCheckpoint($next978, [$receiptFor($next978, 'next979-current-source-seal')]);

    return [$next964, $next965, $next966, $next967, $next968, $next969, $next970, $next971, $next972, $next973, $next974, $next975, $next976, $next977, $next978, $next979];
};

$tests['wal hot journal savepoint checkpoint current source next964-979 chains from next963'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        964 => 'verify_after_ready_checkpoint_restart_salt_source_token_complete',
        965 => 'verify_after_ready_checkpoint_reader_release_database_digest_complete',
        966 => 'verify_after_ready_checkpoint_page_cache_schema_cookie_complete',
        967 => 'verify_after_ready_checkpoint_checkpoint_frame_wal_index_salt_complete',
        968 => 'verify_after_ready_checkpoint_commit_generation_database_header_complete',
        969 => 'verify_after_ready_checkpoint_hot_journal_absence_source_token_complete',
        970 => 'verify_after_ready_checkpoint_reader_release_page_cache_complete',
        971 => 'seal_after_ready_checkpoint_current_source_next964_971_complete',
        972 => 'verify_after_ready_checkpoint_restart_salt_checkpoint_frame_complete',
        973 => 'verify_after_ready_checkpoint_reader_release_schema_cookie_complete',
        974 => 'verify_after_ready_checkpoint_page_cache_wal_index_salt_complete',
        975 => 'verify_after_ready_checkpoint_database_digest_commit_generation_complete',
        976 => 'verify_after_ready_checkpoint_database_header_checkpoint_frame_complete',
        977 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        978 => 'verify_after_ready_checkpoint_wal_index_salt_database_digest_complete',
        979 => 'seal_after_ready_checkpoint_current_source_next972_979_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 964];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next964 = $chainRows[0];
    $next979 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next963', $next964['base_status']);
    $t->same(['next979-current-source-seal'], $next979['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next948_955_next955', implode(',', $next979['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next956_963_next963', implode(',', $next979['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next964_971_next971', implode(',', $next979['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next963', implode(',', $next979['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next979', implode(',', $next979['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next964 rejects missing next963 handoff'] = static function (TestRunner $t) use ($base963, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next964AfterCurrentCheckpoint(
        array_replace($base963, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next962']),
        [$receiptFor($base963, 'next964-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next967 blocks wal index salt mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next966] = $chain();
    $receipt = $receiptFor($next966, 'next967-wal-index-not-synced');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next967AfterCurrentCheckpoint($next966, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next967', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next971 rejects missing next970 base'] = static function (TestRunner $t) use ($base963, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next971AfterCurrentCheckpoint(
        array_replace($base963, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next969']),
        [$receiptFor($base963, 'next971-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next975 blocks commit generation mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next974] = $chain();
    $receipt = $receiptFor($next974, 'next975-commit-generation-mismatch');
    $receipt['commit_generation']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next975AfterCurrentCheckpoint($next974, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next975', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next979 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next978] = $chain();
    $receipt = $receiptFor($next978, 'next979-duplicate-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next979AfterCurrentCheckpoint($next978, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next979', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next979-duplicate-seal'], $record['blocked_reasons']);
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base979 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next979',
    'database_path' => '/srv/www/wp-content/database/wp-next980.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next980.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next980.sqlite-wal',
    'source_token' => 'wp-next980-995-current-source',
    'database_digest' => $digest('next980-995 checkpoint database image'),
    'page_cache_digest' => $digest('next980-995 checkpoint page cache image'),
    'commit_generation' => 980,
    'schema_cookie' => 1980,
    'checkpoint_frame' => 780,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next948_955_next955',
        'seal_after_ready_checkpoint_current_source_next956_963_next963',
        'seal_after_ready_checkpoint_current_source_next964_971_next971',
        'seal_after_ready_checkpoint_current_source_next972_979_next979',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next963',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next979',
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

$chain = static function () use ($base979, $receiptFor): array {
    $next980 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next980AfterCurrentCheckpoint($base979, [$receiptFor($base979, 'next980-restart-salt-source-token')]);
    $next981 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next981AfterCurrentCheckpoint($next980, [$receiptFor($next980, 'next981-reader-release-database-digest')]);
    $next982 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next982AfterCurrentCheckpoint($next981, [$receiptFor($next981, 'next982-page-cache-schema-cookie')]);
    $next983 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next983AfterCurrentCheckpoint($next982, [$receiptFor($next982, 'next983-checkpoint-frame-wal-index')]);
    $next984 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next984AfterCurrentCheckpoint($next983, [$receiptFor($next983, 'next984-commit-generation-database-header')]);
    $next985 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next985AfterCurrentCheckpoint($next984, [$receiptFor($next984, 'next985-hot-journal-source-token')]);
    $next986 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next986AfterCurrentCheckpoint($next985, [$receiptFor($next985, 'next986-reader-release-page-cache')]);
    $next987 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next987AfterCurrentCheckpoint($next986, [$receiptFor($next986, 'next987-current-source-seal')]);
    $next988 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next988AfterCurrentCheckpoint($next987, [$receiptFor($next987, 'next988-restart-salt-checkpoint-frame')]);
    $next989 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next989AfterCurrentCheckpoint($next988, [$receiptFor($next988, 'next989-reader-release-schema-cookie')]);
    $next990 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next990AfterCurrentCheckpoint($next989, [$receiptFor($next989, 'next990-page-cache-wal-index')]);
    $next991 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next991AfterCurrentCheckpoint($next990, [$receiptFor($next990, 'next991-database-digest-commit-generation')]);
    $next992 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next992AfterCurrentCheckpoint($next991, [$receiptFor($next991, 'next992-database-header-checkpoint-frame')]);
    $next993 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next993AfterCurrentCheckpoint($next992, [$receiptFor($next992, 'next993-hot-journal-reader-release')]);
    $next994 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next994AfterCurrentCheckpoint($next993, [$receiptFor($next993, 'next994-wal-index-database-digest')]);
    $next995 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next995AfterCurrentCheckpoint($next994, [$receiptFor($next994, 'next995-current-source-seal')]);

    return [$next980, $next981, $next982, $next983, $next984, $next985, $next986, $next987, $next988, $next989, $next990, $next991, $next992, $next993, $next994, $next995];
};

$tests['wal hot journal savepoint checkpoint current source next980-995 chains from next979'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        980 => 'verify_after_ready_checkpoint_restart_salt_source_token_complete',
        981 => 'verify_after_ready_checkpoint_reader_release_database_digest_complete',
        982 => 'verify_after_ready_checkpoint_page_cache_schema_cookie_complete',
        983 => 'verify_after_ready_checkpoint_checkpoint_frame_wal_index_salt_complete',
        984 => 'verify_after_ready_checkpoint_commit_generation_database_header_complete',
        985 => 'verify_after_ready_checkpoint_hot_journal_absence_source_token_complete',
        986 => 'verify_after_ready_checkpoint_reader_release_page_cache_complete',
        987 => 'seal_after_ready_checkpoint_current_source_next980_987_complete',
        988 => 'verify_after_ready_checkpoint_restart_salt_checkpoint_frame_complete',
        989 => 'verify_after_ready_checkpoint_reader_release_schema_cookie_complete',
        990 => 'verify_after_ready_checkpoint_page_cache_wal_index_salt_complete',
        991 => 'verify_after_ready_checkpoint_database_digest_commit_generation_complete',
        992 => 'verify_after_ready_checkpoint_database_header_checkpoint_frame_complete',
        993 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        994 => 'verify_after_ready_checkpoint_wal_index_salt_database_digest_complete',
        995 => 'seal_after_ready_checkpoint_current_source_next988_995_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 980];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next980 = $chainRows[0];
    $next995 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next979', $next980['base_status']);
    $t->same(['next995-current-source-seal'], $next995['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next956_963_next963', implode(',', $next995['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next964_971_next971', implode(',', $next995['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next972_979_next979', implode(',', $next995['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next980_987_next987', implode(',', $next995['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next979', implode(',', $next995['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next995', implode(',', $next995['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next980 rejects missing next979 handoff'] = static function (TestRunner $t) use ($base979, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next980AfterCurrentCheckpoint(
        array_replace($base979, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next978']),
        [$receiptFor($base979, 'next980-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next983 blocks wal index salt mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next982] = $chain();
    $receipt = $receiptFor($next982, 'next983-wal-index-not-synced');
    $receipt['wal_index_salt_synced'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next983AfterCurrentCheckpoint($next982, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next983', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next987 rejects missing next986 base'] = static function (TestRunner $t) use ($base979, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next987AfterCurrentCheckpoint(
        array_replace($base979, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next985']),
        [$receiptFor($base979, 'next987-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next991 blocks commit generation mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next990] = $chain();
    $receipt = $receiptFor($next990, 'next991-commit-generation-mismatch');
    $receipt['commit_generation']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next991AfterCurrentCheckpoint($next990, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next991', $record['status']);
    $t->same(['checkpoint_generation_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next995 blocks duplicate receipt names'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next994] = $chain();
    $receipt = $receiptFor($next994, 'next995-duplicate-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next995AfterCurrentCheckpoint($next994, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next995', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next995-duplicate-seal'], $record['blocked_reasons']);
};

return $tests;

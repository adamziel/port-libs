<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base915 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next915',
    'database_path' => '/srv/www/wp-content/database/wp-next916.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next916.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next916.sqlite-wal',
    'source_token' => 'wp-next916-931-current-source',
    'database_digest' => $digest('next916-931 checkpoint database image'),
    'page_cache_digest' => $digest('next916-931 checkpoint page cache image'),
    'commit_generation' => 916,
    'schema_cookie' => 1916,
    'checkpoint_frame' => 716,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next900_907_next907',
        'seal_after_ready_checkpoint_current_source_next908_915_next915',
    ],
    'dependencies' => [
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next899',
        'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next915',
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

$chain = static function () use ($base915, $receiptFor): array {
    $next916 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next916AfterCurrentCheckpoint($base915, [$receiptFor($base915, 'next916-restart-salt-database-digest')]);
    $next917 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next917AfterCurrentCheckpoint($next916, [$receiptFor($next916, 'next917-reader-release-checkpoint-frame')]);
    $next918 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next918AfterCurrentCheckpoint($next917, [$receiptFor($next917, 'next918-page-cache-source-token')]);
    $next919 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next919AfterCurrentCheckpoint($next918, [$receiptFor($next918, 'next919-schema-cookie-database-header')]);
    $next920 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next920AfterCurrentCheckpoint($next919, [$receiptFor($next919, 'next920-commit-generation-wal-index')]);
    $next921 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next921AfterCurrentCheckpoint($next920, [$receiptFor($next920, 'next921-hot-journal-reader-release')]);
    $next922 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next922AfterCurrentCheckpoint($next921, [$receiptFor($next921, 'next922-wal-index-page-cache')]);
    $next923 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next923AfterCurrentCheckpoint($next922, [$receiptFor($next922, 'next923-current-source-seal')]);
    $next924 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next924AfterCurrentCheckpoint($next923, [$receiptFor($next923, 'next924-restart-salt-database-header')]);
    $next925 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next925AfterCurrentCheckpoint($next924, [$receiptFor($next924, 'next925-reader-release-source-token')]);
    $next926 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next926AfterCurrentCheckpoint($next925, [$receiptFor($next925, 'next926-page-cache-database-digest')]);
    $next927 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next927AfterCurrentCheckpoint($next926, [$receiptFor($next926, 'next927-checkpoint-frame-schema-cookie')]);
    $next928 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next928AfterCurrentCheckpoint($next927, [$receiptFor($next927, 'next928-commit-generation-checkpoint-frame')]);
    $next929 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next929AfterCurrentCheckpoint($next928, [$receiptFor($next928, 'next929-hot-journal-page-cache')]);
    $next930 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next930AfterCurrentCheckpoint($next929, [$receiptFor($next929, 'next930-wal-index-reader-release')]);
    $next931 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next931AfterCurrentCheckpoint($next930, [$receiptFor($next930, 'next931-current-source-seal')]);

    return [$next916, $next917, $next918, $next919, $next920, $next921, $next922, $next923, $next924, $next925, $next926, $next927, $next928, $next929, $next930, $next931];
};

$tests['wal hot journal savepoint checkpoint current source next916-931 chains from next915'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        916 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        917 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        918 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        919 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        920 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        921 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        922 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        923 => 'seal_after_ready_checkpoint_current_source_next916_923_complete',
        924 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        925 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        926 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        927 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        928 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        929 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        930 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        931 => 'seal_after_ready_checkpoint_current_source_next924_931_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 916];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next916 = $chainRows[0];
    $next931 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next915', $next916['base_status']);
    $t->same(['next931-current-source-seal'], $next931['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next908_915_next915', implode(',', $next931['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next916_923_next923', implode(',', $next931['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next915', implode(',', $next931['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next931', implode(',', $next931['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next916 rejects missing next915 handoff'] = static function (TestRunner $t) use ($base915, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next916AfterCurrentCheckpoint(
        array_replace($base915, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next914']),
        [$receiptFor($base915, 'next916-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next918 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next917] = $chain();
    $receipt = $receiptFor($next917, 'next918-source-token-mismatch');
    $receipt['source_token'] = 'wp-next918-stale-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next918AfterCurrentCheckpoint($next917, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next918', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next923 rejects missing next922 base'] = static function (TestRunner $t) use ($base915, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next923AfterCurrentCheckpoint(
        array_replace($base915, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next921']),
        [$receiptFor($base915, 'next923-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next927 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next926] = $chain();
    $receipt = $receiptFor($next926, 'next927-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next927AfterCurrentCheckpoint($next926, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next927', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next931 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next930] = $chain();
    $receipt = $receiptFor($next930, 'next931-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next931AfterCurrentCheckpoint($next930, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next931', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next931-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

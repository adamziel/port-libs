<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base739 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next739',
    'database_path' => '/srv/www/wp-content/database/wp-next740.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next740.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next740.sqlite-wal',
    'source_token' => 'wp-next740-755-current-source',
    'database_digest' => $digest('next740-755 checkpoint database image'),
    'page_cache_digest' => $digest('next740-755 checkpoint page cache image'),
    'commit_generation' => 740,
    'schema_cookie' => 1740,
    'checkpoint_frame' => 540,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next724_731_next731',
        'seal_after_ready_checkpoint_current_source_next732_739_next739',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next739'],
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

$chain = static function () use ($base739, $receiptFor): array {
    $next740 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next740AfterCurrentCheckpoint($base739, [$receiptFor($base739, 'next740-restart-salt-database-digest')]);
    $next741 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next741AfterCurrentCheckpoint($next740, [$receiptFor($next740, 'next741-reader-release-checkpoint-frame')]);
    $next742 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next742AfterCurrentCheckpoint($next741, [$receiptFor($next741, 'next742-page-cache-source-token')]);
    $next743 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next743AfterCurrentCheckpoint($next742, [$receiptFor($next742, 'next743-schema-cookie-database-header')]);
    $next744 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next744AfterCurrentCheckpoint($next743, [$receiptFor($next743, 'next744-commit-generation-wal-index')]);
    $next745 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next745AfterCurrentCheckpoint($next744, [$receiptFor($next744, 'next745-hot-journal-reader-release')]);
    $next746 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next746AfterCurrentCheckpoint($next745, [$receiptFor($next745, 'next746-wal-index-page-cache')]);
    $next747 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next747AfterCurrentCheckpoint($next746, [$receiptFor($next746, 'next747-current-source-seal')]);
    $next748 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next748AfterCurrentCheckpoint($next747, [$receiptFor($next747, 'next748-restart-salt-database-header')]);
    $next749 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next749AfterCurrentCheckpoint($next748, [$receiptFor($next748, 'next749-reader-release-source-token')]);
    $next750 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next750AfterCurrentCheckpoint($next749, [$receiptFor($next749, 'next750-page-cache-database-digest')]);
    $next751 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next751AfterCurrentCheckpoint($next750, [$receiptFor($next750, 'next751-checkpoint-frame-schema-cookie')]);
    $next752 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next752AfterCurrentCheckpoint($next751, [$receiptFor($next751, 'next752-commit-generation-checkpoint-frame')]);
    $next753 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next753AfterCurrentCheckpoint($next752, [$receiptFor($next752, 'next753-hot-journal-page-cache')]);
    $next754 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next754AfterCurrentCheckpoint($next753, [$receiptFor($next753, 'next754-wal-index-reader-release')]);
    $next755 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next755AfterCurrentCheckpoint($next754, [$receiptFor($next754, 'next755-current-source-seal')]);

    return [$next740, $next741, $next742, $next743, $next744, $next745, $next746, $next747, $next748, $next749, $next750, $next751, $next752, $next753, $next754, $next755];
};

$tests['wal hot journal savepoint checkpoint current source next740-755 receives checkpoint handoff from next739'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        740 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        741 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        742 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        743 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        744 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        745 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        746 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        747 => 'seal_after_ready_checkpoint_current_source_next740_747_complete',
        748 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        749 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        750 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        751 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        752 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        753 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        754 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        755 => 'seal_after_ready_checkpoint_current_source_next748_755_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 740];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next740 = $chainRows[0];
    $next755 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next739', $next740['base_status']);
    $t->same(['next755-current-source-seal'], $next755['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next732_739_next739', implode(',', $next755['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next740_747_next747', implode(',', $next755['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next739', implode(',', $next755['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next755', implode(',', $next755['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next740 rejects missing next739 handoff'] = static function (TestRunner $t) use ($base739, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next740AfterCurrentCheckpoint(
        array_replace($base739, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next738']),
        [$receiptFor($base739, 'next740-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next742 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next741] = $chain();
    $receipt = $receiptFor($next741, 'next742-page-cache-mismatch');
    $receipt['page_cache_digest'] = hash('sha256', 'different page cache');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next742AfterCurrentCheckpoint($next741, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next742', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next745 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next744] = $chain();
    $receipt = $receiptFor($next744, 'next745-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next745AfterCurrentCheckpoint($next744, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next745', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next747 rejects missing next746 base'] = static function (TestRunner $t) use ($base739, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next747AfterCurrentCheckpoint(
        array_replace($base739, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next745']),
        [$receiptFor($base739, 'next747-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next751 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next750] = $chain();
    $receipt = $receiptFor($next750, 'next751-schema-cookie-mismatch');
    $receipt['schema_cookie']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next751AfterCurrentCheckpoint($next750, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next751', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next755 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next754] = $chain();
    $receipt = $receiptFor($next754, 'next755-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next755AfterCurrentCheckpoint($next754, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next755', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next755-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

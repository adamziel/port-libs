<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base419 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next419',
    'database_path' => '/srv/www/wp-content/database/wp-next420.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next420.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next420.sqlite-wal',
    'source_token' => 'wp-next420-435-current-source',
    'database_digest' => $digest('next420-435 checkpoint database image'),
    'page_cache_digest' => $digest('next420-435 checkpoint page cache image'),
    'commit_generation' => 435,
    'schema_cookie' => 1435,
    'checkpoint_frame' => 235,
    'operation_names' => ['seal_after_ready_checkpoint_current_source_next412_419_next419'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next419'],
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

$chain = static function () use ($base419, $receiptFor): array {
    $next420 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base419, [$receiptFor($base419, 'next420-restart-salt-page-cache')], 420);
    $next421 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next420, [$receiptFor($next420, 'next421-reader-mark-page-cache')], 421);
    $next422 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next421, [$receiptFor($next421, 'next422-page-cache-digest-generation')], 422);
    $next423 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next422, [$receiptFor($next422, 'next423-schema-cookie-generation')], 423);
    $next424 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next423, [$receiptFor($next423, 'next424-commit-generation-schema-cookie')], 424);
    $next425 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next424, [$receiptFor($next424, 'next425-hot-journal-delete-schema-cookie')], 425);
    $next426 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next425, [$receiptFor($next425, 'next426-wal-index-salt-schema-cookie')], 426);
    $next427 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next426, [$receiptFor($next426, 'next427-current-source-seal')], 427);
    $next428 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next427, [$receiptFor($next427, 'next428-restart-salt-generation')], 428);
    $next429 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next428, [$receiptFor($next428, 'next429-reader-mark-generation')], 429);
    $next430 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next429, [$receiptFor($next429, 'next430-page-cache-digest-schema-cookie')], 430);
    $next431 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next430, [$receiptFor($next430, 'next431-schema-cookie-source-frame')], 431);
    $next432 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next431, [$receiptFor($next431, 'next432-commit-generation-source-digest')], 432);
    $next433 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next432, [$receiptFor($next432, 'next433-hot-journal-absence-generation')], 433);
    $next434 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next433, [$receiptFor($next433, 'next434-wal-index-salt-generation')], 434);
    $next435 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next434, [$receiptFor($next434, 'next435-current-source-seal')], 435);

    return [$next420, $next421, $next422, $next423, $next424, $next425, $next426, $next427, $next428, $next429, $next430, $next431, $next432, $next433, $next434, $next435];
};

$tests['wal hot journal savepoint checkpoint current source next420-435 chains after merged next404-419'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        420 => 'verify_after_ready_checkpoint_restart_salt_receipt_page_cache_complete',
        421 => 'verify_after_ready_checkpoint_reader_mark_release_page_cache_complete',
        422 => 'verify_after_ready_checkpoint_page_cache_digest_generation_complete',
        423 => 'verify_after_ready_checkpoint_schema_cookie_generation_complete',
        424 => 'verify_after_ready_checkpoint_commit_generation_schema_cookie_complete',
        425 => 'verify_after_ready_checkpoint_hot_journal_delete_schema_cookie_complete',
        426 => 'verify_after_ready_checkpoint_wal_index_salt_schema_cookie_complete',
        427 => 'seal_after_ready_checkpoint_current_source_next420_427_complete',
        428 => 'verify_after_ready_checkpoint_restart_salt_receipt_generation_complete',
        429 => 'verify_after_ready_checkpoint_reader_mark_release_generation_complete',
        430 => 'verify_after_ready_checkpoint_page_cache_digest_schema_cookie_complete',
        431 => 'verify_after_ready_checkpoint_schema_cookie_source_frame_complete',
        432 => 'verify_after_ready_checkpoint_commit_generation_source_digest_complete',
        433 => 'verify_after_ready_checkpoint_hot_journal_absence_generation_complete',
        434 => 'verify_after_ready_checkpoint_wal_index_salt_generation_complete',
        435 => 'seal_after_ready_checkpoint_current_source_next428_435_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 420];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next435 = $chainRows[15];
    $t->same(['next435-current-source-seal'], $next435['accepted_checkpoint_receipt_names']);
    $t->contains('verify_after_ready_checkpoint_restart_salt_receipt_page_cache_next420', implode(',', $next435['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next420_427_next427', implode(',', $next435['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next419', implode(',', $next435['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next435', implode(',', $next435['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next420 blocks page cache digest mismatch'] = static function (TestRunner $t) use ($base419, $receiptFor, $digest): void {
    $receipt = $receiptFor($base419, 'next420-stale-page-cache');
    $receipt['page_cache_digest'] = $digest('stale page cache digest next420');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($base419, [$receipt], 420);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next420', $record['status']);
    $t->same(['checkpoint_page_cache_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next423 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , $next422] = $chain();
    $receipt = $receiptFor($next422, 'next423-stale-schema-cookie');
    $receipt['schema_cookie'] = 1434;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next422, [$receipt], 423);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next423', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next427 rejects missing next426 base'] = static function (TestRunner $t) use ($base419, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage(
        array_replace($base419, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next425']),
        [$receiptFor($base419, 'next427-current-source-seal')]
    ), 427);
};

$tests['wal hot journal savepoint checkpoint current source next429 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , $next427] = $chain();
    $next428 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next427, [$receiptFor($next427, 'next428-restart-salt-generation')], 428);
    $receipt = $receiptFor($next428, 'next429-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next428, [$receipt], 429);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next429', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next432 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , $next431] = $chain();
    $receipt = $receiptFor($next431, 'next432-stale-source-token');
    $receipt['source_token'] = 'wp-next432-stale-source-token';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next431, [$receipt], 432);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next432', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next435 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next434] = $chain();
    $receipt = $receiptFor($next434, 'next435-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($next434, [$receipt, $receipt], 435);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next435', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next435-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

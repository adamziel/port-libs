<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base723 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next723',
    'database_path' => '/srv/www/wp-content/database/wp-next724.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next724.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next724.sqlite-wal',
    'source_token' => 'wp-next724-739-current-source',
    'database_digest' => $digest('next724-739 checkpoint database image'),
    'page_cache_digest' => $digest('next724-739 checkpoint page cache image'),
    'commit_generation' => 724,
    'schema_cookie' => 1724,
    'checkpoint_frame' => 524,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next708_715_next715',
        'seal_after_ready_checkpoint_current_source_next716_723_next723',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next723'],
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

$chain = static function () use ($base723, $receiptFor): array {
    $next724 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next724AfterCurrentCheckpoint($base723, [$receiptFor($base723, 'next724-restart-salt-database-digest')]);
    $next725 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next725AfterCurrentCheckpoint($next724, [$receiptFor($next724, 'next725-reader-release-checkpoint-frame')]);
    $next726 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next726AfterCurrentCheckpoint($next725, [$receiptFor($next725, 'next726-page-cache-source-token')]);
    $next727 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next727AfterCurrentCheckpoint($next726, [$receiptFor($next726, 'next727-schema-cookie-database-header')]);
    $next728 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next728AfterCurrentCheckpoint($next727, [$receiptFor($next727, 'next728-commit-generation-wal-index')]);
    $next729 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next729AfterCurrentCheckpoint($next728, [$receiptFor($next728, 'next729-hot-journal-reader-release')]);
    $next730 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next730AfterCurrentCheckpoint($next729, [$receiptFor($next729, 'next730-wal-index-page-cache')]);
    $next731 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next731AfterCurrentCheckpoint($next730, [$receiptFor($next730, 'next731-current-source-seal')]);
    $next732 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next732AfterCurrentCheckpoint($next731, [$receiptFor($next731, 'next732-restart-salt-database-header')]);
    $next733 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next733AfterCurrentCheckpoint($next732, [$receiptFor($next732, 'next733-reader-release-source-token')]);
    $next734 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next734AfterCurrentCheckpoint($next733, [$receiptFor($next733, 'next734-page-cache-database-digest')]);
    $next735 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next735AfterCurrentCheckpoint($next734, [$receiptFor($next734, 'next735-checkpoint-frame-schema-cookie')]);
    $next736 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next736AfterCurrentCheckpoint($next735, [$receiptFor($next735, 'next736-commit-generation-checkpoint-frame')]);
    $next737 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next737AfterCurrentCheckpoint($next736, [$receiptFor($next736, 'next737-hot-journal-page-cache')]);
    $next738 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next738AfterCurrentCheckpoint($next737, [$receiptFor($next737, 'next738-wal-index-reader-release')]);
    $next739 = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next739AfterCurrentCheckpoint($next738, [$receiptFor($next738, 'next739-current-source-seal')]);

    return [$next724, $next725, $next726, $next727, $next728, $next729, $next730, $next731, $next732, $next733, $next734, $next735, $next736, $next737, $next738, $next739];
};

$tests['wal hot journal savepoint checkpoint current source next724-739 receives checkpoint handoff from next723'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        724 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        725 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        726 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        727 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        728 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        729 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        730 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        731 => 'seal_after_ready_checkpoint_current_source_next724_731_complete',
        732 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        733 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        734 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        735 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        736 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        737 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        738 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        739 => 'seal_after_ready_checkpoint_current_source_next732_739_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 724];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next724 = $chainRows[0];
    $next739 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next723', $next724['base_status']);
    $t->same(['next739-current-source-seal'], $next739['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next716_723_next723', implode(',', $next739['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next724_731_next731', implode(',', $next739['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next723', implode(',', $next739['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next739', implode(',', $next739['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next724 rejects missing next723 handoff'] = static function (TestRunner $t) use ($base723, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next724AfterCurrentCheckpoint(
        array_replace($base723, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next722']),
        [$receiptFor($base723, 'next724-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next726 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next725] = $chain();
    $receipt = $receiptFor($next725, 'next726-source-token-mismatch');
    $receipt['source_token'] = 'wp-next726-different-current-source';
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next726AfterCurrentCheckpoint($next725, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next726', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next729 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next728] = $chain();
    $receipt = $receiptFor($next728, 'next729-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next729AfterCurrentCheckpoint($next728, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next729', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next731 rejects missing next730 base'] = static function (TestRunner $t) use ($base723, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next731AfterCurrentCheckpoint(
        array_replace($base723, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next729']),
        [$receiptFor($base723, 'next731-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next735 blocks checkpoint frame mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next734] = $chain();
    $receipt = $receiptFor($next734, 'next735-checkpoint-frame-mismatch');
    $receipt['checkpoint_frame']++;
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next735AfterCurrentCheckpoint($next734, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next735', $record['status']);
    $t->same(['checkpoint_frame_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next739 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next738] = $chain();
    $receipt = $receiptFor($next738, 'next739-duplicate-current-source-seal');
    $record = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next739AfterCurrentCheckpoint($next738, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next739', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next739-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

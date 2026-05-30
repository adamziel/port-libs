<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

if (!function_exists('wal_after_current_checkpoint_stage')) {
    function wal_after_current_checkpoint_stage(int $stage, array $checkpointPlan, array $checkpointReceipts): array
    {
        return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpointStage($checkpointPlan, $checkpointReceipts, $stage);
    }
}

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$base643 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next643',
    'database_path' => '/srv/www/wp-content/database/wp-next644.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next644.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next644.sqlite-wal',
    'source_token' => 'wp-next644-659-current-source',
    'database_digest' => $digest('next644-659 checkpoint database image'),
    'page_cache_digest' => $digest('next644-659 checkpoint page cache image'),
    'commit_generation' => 644,
    'schema_cookie' => 1644,
    'checkpoint_frame' => 444,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next628_635_next635',
        'seal_after_ready_checkpoint_current_source_next636_643_next643',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next643'],
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

$chain = static function () use ($base643, $receiptFor): array {
    $next644 = wal_after_current_checkpoint_stage(644, $base643, [$receiptFor($base643, 'next644-restart-salt-database-header')]);
    $next645 = wal_after_current_checkpoint_stage(645, $next644, [$receiptFor($next644, 'next645-reader-release-source-token')]);
    $next646 = wal_after_current_checkpoint_stage(646, $next645, [$receiptFor($next645, 'next646-page-cache-database-digest')]);
    $next647 = wal_after_current_checkpoint_stage(647, $next646, [$receiptFor($next646, 'next647-schema-cookie-wal-index')]);
    $next648 = wal_after_current_checkpoint_stage(648, $next647, [$receiptFor($next647, 'next648-commit-generation-checkpoint-frame')]);
    $next649 = wal_after_current_checkpoint_stage(649, $next648, [$receiptFor($next648, 'next649-hot-journal-reader-release')]);
    $next650 = wal_after_current_checkpoint_stage(650, $next649, [$receiptFor($next649, 'next650-wal-index-page-cache')]);
    $next651 = wal_after_current_checkpoint_stage(651, $next650, [$receiptFor($next650, 'next651-current-source-seal')]);
    $next652 = wal_after_current_checkpoint_stage(652, $next651, [$receiptFor($next651, 'next652-restart-salt-source-token')]);
    $next653 = wal_after_current_checkpoint_stage(653, $next652, [$receiptFor($next652, 'next653-reader-release-database-digest')]);
    $next654 = wal_after_current_checkpoint_stage(654, $next653, [$receiptFor($next653, 'next654-page-cache-schema-cookie')]);
    $next655 = wal_after_current_checkpoint_stage(655, $next654, [$receiptFor($next654, 'next655-checkpoint-frame-wal-index')]);
    $next656 = wal_after_current_checkpoint_stage(656, $next655, [$receiptFor($next655, 'next656-commit-generation-database-header')]);
    $next657 = wal_after_current_checkpoint_stage(657, $next656, [$receiptFor($next656, 'next657-hot-journal-source-token')]);
    $next658 = wal_after_current_checkpoint_stage(658, $next657, [$receiptFor($next657, 'next658-wal-index-reader-release')]);
    $next659 = wal_after_current_checkpoint_stage(659, $next658, [$receiptFor($next658, 'next659-current-source-seal')]);

    return [$next644, $next645, $next646, $next647, $next648, $next649, $next650, $next651, $next652, $next653, $next654, $next655, $next656, $next657, $next658, $next659];
};

$tests['wal hot journal savepoint checkpoint current source next644-659 chains after merged next628-643'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        644 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        645 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        646 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        647 => 'verify_after_ready_checkpoint_schema_cookie_wal_index_salt_complete',
        648 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        649 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        650 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        651 => 'seal_after_ready_checkpoint_current_source_next644_651_complete',
        652 => 'verify_after_ready_checkpoint_restart_salt_source_token_complete',
        653 => 'verify_after_ready_checkpoint_reader_mark_release_database_digest_complete',
        654 => 'verify_after_ready_checkpoint_page_cache_schema_cookie_complete',
        655 => 'verify_after_ready_checkpoint_checkpoint_frame_wal_index_salt_complete',
        656 => 'verify_after_ready_checkpoint_commit_generation_database_header_complete',
        657 => 'verify_after_ready_checkpoint_hot_journal_delete_source_token_complete',
        658 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        659 => 'seal_after_ready_checkpoint_current_source_next652_659_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 644];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next659 = $chainRows[15];
    $t->same(['next659-current-source-seal'], $next659['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next636_643_next643', implode(',', $next659['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next644_651_next651', implode(',', $next659['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next643', implode(',', $next659['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next659', implode(',', $next659['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next644 blocks wal index salt not synced'] = static function (TestRunner $t) use ($base643, $receiptFor): void {
    $receipt = $receiptFor($base643, 'next644-unsynced-wal-index');
    $receipt['wal_index_salt_synced'] = false;
    $record = wal_after_current_checkpoint_stage(644, $base643, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next644', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next646 blocks database digest mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next645] = $chain();
    $receipt = $receiptFor($next645, 'next646-stale-database-digest');
    $receipt['database_digest'] = hash('sha256', 'stale next646 database digest');
    $record = wal_after_current_checkpoint_stage(646, $next645, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next646', $record['status']);
    $t->same(['checkpoint_database_digest_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next651 rejects missing next650 base'] = static function (TestRunner $t) use ($base643, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => wal_after_current_checkpoint_stage(651,
        array_replace($base643, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next649']),
        [$receiptFor($base643, 'next651-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next653 blocks reader marks not released'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , $next652] = $chain();
    $receipt = $receiptFor($next652, 'next653-reader-mark-pinned');
    $receipt['reader_marks_released'] = false;
    $record = wal_after_current_checkpoint_stage(653, $next652, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next653', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next657 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , $next656] = $chain();
    $receipt = $receiptFor($next656, 'next657-stale-source-token');
    $receipt['source_token'] = 'stale-next657-current-source';
    $record = wal_after_current_checkpoint_stage(657, $next656, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next657', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next659 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next658] = $chain();
    $receipt = $receiptFor($next658, 'next659-duplicate-current-source-seal');
    $record = wal_after_current_checkpoint_stage(659, $next658, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next659', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next659-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

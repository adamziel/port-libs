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
$base691 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next691',
    'database_path' => '/srv/www/wp-content/database/wp-next692.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next692.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next692.sqlite-wal',
    'source_token' => 'wp-next692-707-current-source',
    'database_digest' => $digest('next692-707 checkpoint database image'),
    'page_cache_digest' => $digest('next692-707 checkpoint page cache image'),
    'commit_generation' => 692,
    'schema_cookie' => 1692,
    'checkpoint_frame' => 492,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next676_683_next683',
        'seal_after_ready_checkpoint_current_source_next684_691_next691',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next691'],
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

$chain = static function () use ($base691, $receiptFor): array {
    $next692 = wal_after_current_checkpoint_stage(692, $base691, [$receiptFor($base691, 'next692-restart-salt-source-token')]);
    $next693 = wal_after_current_checkpoint_stage(693, $next692, [$receiptFor($next692, 'next693-reader-release-database-digest')]);
    $next694 = wal_after_current_checkpoint_stage(694, $next693, [$receiptFor($next693, 'next694-page-cache-database-header')]);
    $next695 = wal_after_current_checkpoint_stage(695, $next694, [$receiptFor($next694, 'next695-checkpoint-frame-schema-cookie')]);
    $next696 = wal_after_current_checkpoint_stage(696, $next695, [$receiptFor($next695, 'next696-commit-generation-wal-index')]);
    $next697 = wal_after_current_checkpoint_stage(697, $next696, [$receiptFor($next696, 'next697-hot-journal-page-cache')]);
    $next698 = wal_after_current_checkpoint_stage(698, $next697, [$receiptFor($next697, 'next698-wal-index-reader-release')]);
    $next699 = wal_after_current_checkpoint_stage(699, $next698, [$receiptFor($next698, 'next699-current-source-seal')]);
    $next700 = wal_after_current_checkpoint_stage(700, $next699, [$receiptFor($next699, 'next700-restart-salt-database-header')]);
    $next701 = wal_after_current_checkpoint_stage(701, $next700, [$receiptFor($next700, 'next701-reader-release-source-token')]);
    $next702 = wal_after_current_checkpoint_stage(702, $next701, [$receiptFor($next701, 'next702-page-cache-database-digest')]);
    $next703 = wal_after_current_checkpoint_stage(703, $next702, [$receiptFor($next702, 'next703-schema-cookie-wal-index')]);
    $next704 = wal_after_current_checkpoint_stage(704, $next703, [$receiptFor($next703, 'next704-commit-generation-checkpoint-frame')]);
    $next705 = wal_after_current_checkpoint_stage(705, $next704, [$receiptFor($next704, 'next705-hot-journal-reader-release')]);
    $next706 = wal_after_current_checkpoint_stage(706, $next705, [$receiptFor($next705, 'next706-wal-index-page-cache')]);
    $next707 = wal_after_current_checkpoint_stage(707, $next706, [$receiptFor($next706, 'next707-current-source-seal')]);

    return [$next692, $next693, $next694, $next695, $next696, $next697, $next698, $next699, $next700, $next701, $next702, $next703, $next704, $next705, $next706, $next707];
};

$tests['wal hot journal savepoint checkpoint current source next692-707 chains directly after next691'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        692 => 'verify_after_ready_checkpoint_restart_salt_source_token_complete',
        693 => 'verify_after_ready_checkpoint_reader_mark_release_database_digest_complete',
        694 => 'verify_after_ready_checkpoint_page_cache_database_header_complete',
        695 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        696 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        697 => 'verify_after_ready_checkpoint_hot_journal_absence_page_cache_complete',
        698 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        699 => 'seal_after_ready_checkpoint_current_source_next692_699_complete',
        700 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        701 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        702 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        703 => 'verify_after_ready_checkpoint_schema_cookie_wal_index_salt_complete',
        704 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        705 => 'verify_after_ready_checkpoint_hot_journal_delete_reader_release_complete',
        706 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        707 => 'seal_after_ready_checkpoint_current_source_next700_707_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 692];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next707 = $chainRows[15];
    $t->same(['next707-current-source-seal'], $next707['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next684_691_next691', implode(',', $next707['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next692_699_next699', implode(',', $next707['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next691', implode(',', $next707['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next707', implode(',', $next707['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next692 rejects missing next691 handoff'] = static function (TestRunner $t) use ($base691, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => wal_after_current_checkpoint_stage(692,
        array_replace($base691, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next690']),
        [$receiptFor($base691, 'next692-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next694 blocks unsynced database header'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next693] = $chain();
    $receipt = $receiptFor($next693, 'next694-unsynced-database-header');
    $receipt['database_header_synced'] = false;
    $record = wal_after_current_checkpoint_stage(694, $next693, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next694', $record['status']);
    $t->same(['checkpoint_database_header_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next697 blocks visible hot journal'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next696] = $chain();
    $receipt = $receiptFor($next696, 'next697-hot-journal-visible');
    $receipt['hot_journal_visible'] = true;
    $record = wal_after_current_checkpoint_stage(697, $next696, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next697', $record['status']);
    $t->same(['checkpoint_hot_journal_visible'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next699 rejects missing next698 base'] = static function (TestRunner $t) use ($base691, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => wal_after_current_checkpoint_stage(699,
        array_replace($base691, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next697']),
        [$receiptFor($base691, 'next699-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next703 blocks wal-index salt mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next702] = $chain();
    $receipt = $receiptFor($next702, 'next703-wal-index-not-synced');
    $receipt['wal_index_salt_synced'] = false;
    $record = wal_after_current_checkpoint_stage(703, $next702, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next703', $record['status']);
    $t->same(['checkpoint_wal_index_salt_not_synced'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next707 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next706] = $chain();
    $receipt = $receiptFor($next706, 'next707-duplicate-current-source-seal');
    $record = wal_after_current_checkpoint_stage(707, $next706, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next707', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next707-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

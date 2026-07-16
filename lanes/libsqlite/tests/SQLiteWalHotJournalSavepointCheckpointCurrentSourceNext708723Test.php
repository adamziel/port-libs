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
$base707 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next707',
    'database_path' => '/srv/www/wp-content/database/wp-next708.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next708.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next708.sqlite-wal',
    'source_token' => 'wp-next708-723-current-source',
    'database_digest' => $digest('next708-723 checkpoint database image'),
    'page_cache_digest' => $digest('next708-723 checkpoint page cache image'),
    'commit_generation' => 708,
    'schema_cookie' => 1708,
    'checkpoint_frame' => 508,
    'operation_names' => [
        'seal_after_ready_checkpoint_current_source_next692_699_next699',
        'seal_after_ready_checkpoint_current_source_next700_707_next707',
    ],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next707'],
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

$chain = static function () use ($base707, $receiptFor): array {
    $next708 = wal_after_current_checkpoint_stage(708, $base707, [$receiptFor($base707, 'next708-restart-salt-database-digest')]);
    $next709 = wal_after_current_checkpoint_stage(709, $next708, [$receiptFor($next708, 'next709-reader-release-checkpoint-frame')]);
    $next710 = wal_after_current_checkpoint_stage(710, $next709, [$receiptFor($next709, 'next710-page-cache-source-token')]);
    $next711 = wal_after_current_checkpoint_stage(711, $next710, [$receiptFor($next710, 'next711-schema-cookie-database-header')]);
    $next712 = wal_after_current_checkpoint_stage(712, $next711, [$receiptFor($next711, 'next712-commit-generation-wal-index')]);
    $next713 = wal_after_current_checkpoint_stage(713, $next712, [$receiptFor($next712, 'next713-hot-journal-reader-release')]);
    $next714 = wal_after_current_checkpoint_stage(714, $next713, [$receiptFor($next713, 'next714-wal-index-page-cache')]);
    $next715 = wal_after_current_checkpoint_stage(715, $next714, [$receiptFor($next714, 'next715-current-source-seal')]);
    $next716 = wal_after_current_checkpoint_stage(716, $next715, [$receiptFor($next715, 'next716-restart-salt-database-header')]);
    $next717 = wal_after_current_checkpoint_stage(717, $next716, [$receiptFor($next716, 'next717-reader-release-source-token')]);
    $next718 = wal_after_current_checkpoint_stage(718, $next717, [$receiptFor($next717, 'next718-page-cache-database-digest')]);
    $next719 = wal_after_current_checkpoint_stage(719, $next718, [$receiptFor($next718, 'next719-checkpoint-frame-schema-cookie')]);
    $next720 = wal_after_current_checkpoint_stage(720, $next719, [$receiptFor($next719, 'next720-commit-generation-checkpoint-frame')]);
    $next721 = wal_after_current_checkpoint_stage(721, $next720, [$receiptFor($next720, 'next721-hot-journal-page-cache')]);
    $next722 = wal_after_current_checkpoint_stage(722, $next721, [$receiptFor($next721, 'next722-wal-index-reader-release')]);
    $next723 = wal_after_current_checkpoint_stage(723, $next722, [$receiptFor($next722, 'next723-current-source-seal')]);

    return [$next708, $next709, $next710, $next711, $next712, $next713, $next714, $next715, $next716, $next717, $next718, $next719, $next720, $next721, $next722, $next723];
};

$tests['wal hot journal savepoint checkpoint current source next708-723 chains directly after next707'] = static function (TestRunner $t) use ($chain): void {
    $chainRows = $chain();
    $expectedReasons = [
        708 => 'verify_after_ready_checkpoint_restart_salt_database_digest_complete',
        709 => 'verify_after_ready_checkpoint_reader_mark_release_checkpoint_frame_complete',
        710 => 'verify_after_ready_checkpoint_page_cache_source_token_complete',
        711 => 'verify_after_ready_checkpoint_schema_cookie_database_header_complete',
        712 => 'verify_after_ready_checkpoint_commit_generation_wal_index_salt_complete',
        713 => 'verify_after_ready_checkpoint_hot_journal_absence_reader_release_complete',
        714 => 'verify_after_ready_checkpoint_wal_index_salt_page_cache_complete',
        715 => 'seal_after_ready_checkpoint_current_source_next708_715_complete',
        716 => 'verify_after_ready_checkpoint_restart_salt_database_header_complete',
        717 => 'verify_after_ready_checkpoint_reader_mark_release_source_token_complete',
        718 => 'verify_after_ready_checkpoint_page_cache_database_digest_complete',
        719 => 'verify_after_ready_checkpoint_checkpoint_frame_schema_cookie_complete',
        720 => 'verify_after_ready_checkpoint_commit_generation_checkpoint_frame_complete',
        721 => 'verify_after_ready_checkpoint_hot_journal_delete_page_cache_complete',
        722 => 'verify_after_ready_checkpoint_wal_index_salt_reader_release_complete',
        723 => 'seal_after_ready_checkpoint_current_source_next716_723_complete',
    ];

    foreach ($expectedReasons as $next => $reason) {
        $record = $chainRows[$next - 708];
        $t->same("wal-hot-journal-savepoint-checkpoint-current-source-next{$next}", $record['status']);
        $t->same($reason, $record['reason']);
        $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next' . ($next - 1), $record['base_status']);
        $t->same(true, $record['after_current_checkpoint_admitted']);
    }

    $next708 = $chainRows[0];
    $next723 = $chainRows[15];
    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-next707', $next708['base_status']);
    $t->same(['next723-current-source-seal'], $next723['accepted_checkpoint_receipt_names']);
    $t->contains('seal_after_ready_checkpoint_current_source_next700_707_next707', implode(',', $next723['operation_names']));
    $t->contains('seal_after_ready_checkpoint_current_source_next708_715_next715', implode(',', $next723['operation_names']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next707', implode(',', $next723['dependencies']));
    $t->contains('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next723', implode(',', $next723['dependencies']));
};

$tests['wal hot journal savepoint checkpoint current source next708 rejects missing next707 handoff'] = static function (TestRunner $t) use ($base707, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => wal_after_current_checkpoint_stage(708,
        array_replace($base707, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next706']),
        [$receiptFor($base707, 'next708-wrong-base')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next710 blocks source token mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, $next709] = $chain();
    $receipt = $receiptFor($next709, 'next710-source-token-mismatch');
    $receipt['source_token'] = 'wp-next710-different-current-source';
    $record = wal_after_current_checkpoint_stage(710, $next709, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next710', $record['status']);
    $t->same(['checkpoint_source_token_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next713 blocks unreleased reader marks'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , $next712] = $chain();
    $receipt = $receiptFor($next712, 'next713-reader-marks-held');
    $receipt['reader_marks_released'] = false;
    $record = wal_after_current_checkpoint_stage(713, $next712, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next713', $record['status']);
    $t->same(['checkpoint_reader_marks_not_released'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next715 rejects missing next714 base'] = static function (TestRunner $t) use ($base707, $receiptFor): void {
    $t->throws(Throwable::class, static fn () => wal_after_current_checkpoint_stage(715,
        array_replace($base707, ['status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next713']),
        [$receiptFor($base707, 'next715-current-source-seal')]
    ));
};

$tests['wal hot journal savepoint checkpoint current source next719 blocks schema cookie mismatch'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , $next718] = $chain();
    $receipt = $receiptFor($next718, 'next719-schema-cookie-mismatch');
    $receipt['schema_cookie']++;
    $record = wal_after_current_checkpoint_stage(719, $next718, [$receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next719', $record['status']);
    $t->same(['checkpoint_schema_cookie_mismatch'], $record['blocked_reasons']);
};

$tests['wal hot journal savepoint checkpoint current source next723 blocks duplicate final receipts'] = static function (TestRunner $t) use ($chain, $receiptFor): void {
    [, , , , , , , , , , , , , , $next722] = $chain();
    $receipt = $receiptFor($next722, 'next723-duplicate-current-source-seal');
    $record = wal_after_current_checkpoint_stage(723, $next722, [$receipt, $receipt]);

    $t->same('wal-hot-journal-savepoint-checkpoint-current-source-blocked-next723', $record['status']);
    $t->same(['checkpoint_receipt_name_duplicate:next723-duplicate-current-source-seal'], $record['blocked_reasons']);
};

return $tests;

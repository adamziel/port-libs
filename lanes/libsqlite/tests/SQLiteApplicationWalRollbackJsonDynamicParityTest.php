<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

$scenarios = SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(16);

$tests = [
    'sqlite application wal rollback json dynamic parity exposes requested scenario count' => static function (TestRunner $t) use ($scenarios): void {
        $t->same(16, count($scenarios));
    },
];

foreach ($scenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back current json batch'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'requires rollback after malformed dynamic JSON'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['rollback_required']);
    };
    $tests[$prefix . 'uses dynamic transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_dynamic_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses dynamic savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('dynamic_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'preserves generated page size'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['page_size'], $plan['page_size']);
    };
    $tests[$prefix . 'applies two statements before failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'records one failed statement'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'names failed statement'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'marks database changed before rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['database_changed_before_rollback']);
    };
    $tests[$prefix . 'marks database restored after rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['database_restored_to_before']);
    };
    $tests[$prefix . 'counts dynamic WAL frames before rollback'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['wal_frame_count_before']);
    };
    $tests[$prefix . 'truncates WAL to dynamic savepoint offset'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_truncate_bytes'], $plan['wal_truncate_to_bytes']);
    };
    $tests[$prefix . 'has zero WAL frames after rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'discards all current batch WAL frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'reports WAL truncation'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['wal_truncated']);
    };
    $tests[$prefix . 'keeps WAL header bytes after rollback'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(substr($scenario['wal_bytes'], 0, 32), $plan['wal_bytes_after']);
    };
    $tests[$prefix . 'restores applied dynamic pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'has no missing dynamic page images'] = static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan['rollback_to_savepoint']['missing_page_numbers']);
    };
    $tests[$prefix . 'rolls WAL back to frame zero'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_rollback_to_savepoint']['rollback_to_frame']);
    };
    $tests[$prefix . 'discards applied frame indexes'] = static function (TestRunner $t) use ($plan): void {
        $t->same([1, 2], array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'discards applied page numbers'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'keeps statement-level malformed rollback isolated'] = static function (TestRunner $t) use ($plan): void {
        $t->same([3], array_column($plan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'retains tenant id in applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps JSONB mode on generated scenario'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
}

$tests['sqlite application wal rollback json dynamic parity rejects zero scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

return $tests;

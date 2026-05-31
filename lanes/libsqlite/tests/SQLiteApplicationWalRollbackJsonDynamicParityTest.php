<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

$scenarios = SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(24);
$preexistingWalScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios(24);
$tenantCollisionScenarios = SQLiteJsonImportRollbackWalPlan::dynamicTenantCollisionScenarios(24);
$insertedSettingScenarios = SQLiteJsonImportRollbackWalPlan::dynamicInsertedSettingRollbackScenarios(24);
$deferredScenarios = SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(24);
$retryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRetryAfterRollbackScenarios(18);
$preexistingRetryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalRetryScenarios(18);
$missingWalTailScenarios = SQLiteJsonImportRollbackWalPlan::dynamicMissingWalTailScenarios(18);
$partialWalTailScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios(18);

$tests = [
    'sqlite application wal rollback json dynamic parity exposes requested scenario count' => static function (TestRunner $t) use ($scenarios): void {
        $t->same(24, count($scenarios));
    },
    'sqlite application wal rollback json dynamic parity covers both page sizes' => static function (TestRunner $t) use ($scenarios): void {
        $pageSizes = array_values(array_unique(array_column($scenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity covers json text and jsonb rows' => static function (TestRunner $t) use ($scenarios): void {
        $t->same([false, true], array_values(array_unique(array_column($scenarios, 'jsonb_mode'))));
    },
    'sqlite application wal rollback json dynamic parity has unique tenant streams' => static function (TestRunner $t) use ($scenarios): void {
        $tenantIds = array_column($scenarios, 'tenant_id');
        $t->same(count($tenantIds), count(array_unique($tenantIds)));
    },
    'sqlite application wal rollback json dynamic parity deferred mode exposes requested scenario count' => static function (TestRunner $t) use ($deferredScenarios): void {
        $t->same(24, count($deferredScenarios));
    },
    'sqlite application wal rollback json dynamic parity preexisting WAL exposes requested scenario count' => static function (TestRunner $t) use ($preexistingWalScenarios): void {
        $t->same(24, count($preexistingWalScenarios));
    },
    'sqlite application wal rollback json dynamic parity preexisting WAL covers prefix frame counts' => static function (TestRunner $t) use ($preexistingWalScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($preexistingWalScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([2, 3, 4, 5], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity preexisting WAL covers json text and jsonb rows' => static function (TestRunner $t) use ($preexistingWalScenarios): void {
        $jsonModes = array_values(array_unique(array_column($preexistingWalScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity tenant collision exposes requested scenario count' => static function (TestRunner $t) use ($tenantCollisionScenarios): void {
        $t->same(24, count($tenantCollisionScenarios));
    },
    'sqlite application wal rollback json dynamic parity tenant collision covers both page sizes' => static function (TestRunner $t) use ($tenantCollisionScenarios): void {
        $pageSizes = array_values(array_unique(array_column($tenantCollisionScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity tenant collision covers json text and jsonb rows' => static function (TestRunner $t) use ($tenantCollisionScenarios): void {
        $jsonModes = array_values(array_unique(array_column($tenantCollisionScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity inserted setting exposes requested scenario count' => static function (TestRunner $t) use ($insertedSettingScenarios): void {
        $t->same(24, count($insertedSettingScenarios));
    },
    'sqlite application wal rollback json dynamic parity inserted setting covers both page sizes' => static function (TestRunner $t) use ($insertedSettingScenarios): void {
        $pageSizes = array_values(array_unique(array_column($insertedSettingScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity inserted setting covers json text and jsonb rows' => static function (TestRunner $t) use ($insertedSettingScenarios): void {
        $jsonModes = array_values(array_unique(array_column($insertedSettingScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity deferred mode covers json text and jsonb rows' => static function (TestRunner $t) use ($deferredScenarios): void {
        $t->same([false, true], array_values(array_unique(array_column($deferredScenarios, 'jsonb_mode'))));
    },
    'sqlite application wal rollback json dynamic parity retry mode exposes requested scenario count' => static function (TestRunner $t) use ($retryScenarios): void {
        $t->same(18, count($retryScenarios));
    },
    'sqlite application wal rollback json dynamic parity retry mode covers both page sizes' => static function (TestRunner $t) use ($retryScenarios): void {
        $pageSizes = array_values(array_unique(array_column($retryScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity retry mode covers json text and jsonb rows' => static function (TestRunner $t) use ($retryScenarios): void {
        $t->same([false, true], array_values(array_unique(array_column($retryScenarios, 'jsonb_mode'))));
    },
    'sqlite application wal rollback json dynamic parity preexisting retry mode exposes requested scenario count' => static function (TestRunner $t) use ($preexistingRetryScenarios): void {
        $t->same(18, count($preexistingRetryScenarios));
    },
    'sqlite application wal rollback json dynamic parity preexisting retry mode covers prefix frame counts' => static function (TestRunner $t) use ($preexistingRetryScenarios): void {
        $prefixCounts = array_values(array_unique(array_column($preexistingRetryScenarios, 'preexisting_frames')));
        sort($prefixCounts);
        $t->same([1, 2, 3, 4, 5], $prefixCounts);
    },
    'sqlite application wal rollback json dynamic parity preexisting retry mode covers json text and jsonb rows' => static function (TestRunner $t) use ($preexistingRetryScenarios): void {
        $jsonModes = array_values(array_unique(array_column($preexistingRetryScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic parity missing WAL tail exposes requested scenario count' => static function (TestRunner $t) use ($missingWalTailScenarios): void {
        $t->same(18, count($missingWalTailScenarios));
    },
    'sqlite application wal rollback json dynamic parity missing WAL tail covers one and two missing frames' => static function (TestRunner $t) use ($missingWalTailScenarios): void {
        $missingFrames = array_values(array_unique(array_column($missingWalTailScenarios, 'missing_frames')));
        sort($missingFrames);
        $t->same([1, 2], $missingFrames);
    },
    'sqlite application wal rollback json dynamic parity missing WAL tail covers both page sizes' => static function (TestRunner $t) use ($missingWalTailScenarios): void {
        $pageSizes = array_values(array_unique(array_column($missingWalTailScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity partial WAL tail exposes requested scenario count' => static function (TestRunner $t) use ($partialWalTailScenarios): void {
        $t->same(18, count($partialWalTailScenarios));
    },
    'sqlite application wal rollback json dynamic parity partial WAL tail covers both page sizes' => static function (TestRunner $t) use ($partialWalTailScenarios): void {
        $pageSizes = array_values(array_unique(array_column($partialWalTailScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic parity partial WAL tail covers varied partial byte counts' => static function (TestRunner $t) use ($partialWalTailScenarios): void {
        $partialByteCounts = array_values(array_unique(array_column($partialWalTailScenarios, 'partial_payload_bytes')));
        $t->same(true, count($partialByteCounts) > 8);
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

foreach ($preexistingWalScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity preexisting wal seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back current json batch'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'uses prefix transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_prefix_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses prefix savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('prefix_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'records dynamic preexisting wal frame count'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['wal_frame_count_before']);
    };
    $tests[$prefix . 'rolls back to preexisting frame boundary'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $plan['wal_rollback_to_savepoint']['rollback_to_frame']);
    };
    $tests[$prefix . 'truncates wal after preexisting frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_truncate_bytes'], $plan['wal_truncate_to_bytes']);
        $t->same($scenario['expected_truncate_bytes'], strlen($plan['wal_bytes_after']));
    };
    $tests[$prefix . 'preserves preexisting wal prefix bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(substr($scenario['wal_bytes'], 0, $scenario['expected_truncate_bytes']), $plan['wal_bytes_after']);
    };
    $tests[$prefix . 'keeps only preexisting frames after rollback'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'discards only current json batch frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['batch_frames'], $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'discards applied frame indexes after prefix'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $start = $scenario['preexisting_frames'] + 1;
        $t->same([$start, $start + 1], array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'does not discard preexisting wal pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $discardedPages = $plan['wal_rollback_to_savepoint']['discarded_page_numbers'];
        foreach ($scenario['pre_savepoint_wal_pages'] as $pageNumber) {
            $t->same(false, in_array($pageNumber, $discardedPages, true));
        }
    };
    $tests[$prefix . 'restores only mutated current json pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'records malformed statement after prefix writes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'keeps applied statements before failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'retains tenant id in applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps JSONB mode on generated scenario'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
}

foreach ($tenantCollisionScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity tenant collision seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back target tenant json batch'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'uses tenant collision transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_tenant_collision_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses tenant collision savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('tenant_collision_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'records shared key with target tenant setting key'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['target_tenant_id'] . ':' . $scenario['shared_key'], $plan['import_plan']['applied'][0]['setting_key']);
    };
    $tests[$prefix . 'does not apply stable tenant row with same key'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['target_tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'stable tenant row keeps original tenant id'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['stable_tenant_id'], $scenario['stable_row_after_import']['tenant_id']);
    };
    $tests[$prefix . 'stable tenant row keeps shared key name'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['shared_key'], $scenario['stable_row_after_import']['key_name']);
    };
    $tests[$prefix . 'stable tenant row keeps original page'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['stable_page'], $scenario['stable_row_after_import']['page_number']);
    };
    $tests[$prefix . 'names failed target tenant statement'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'rolls wal back to header'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'discards all current wal frames after failed tenant batch'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'restores only target tenant page'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'does not discard stable tenant page'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same(false, in_array($scenario['stable_page'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers'], true));
    };
    $tests[$prefix . 'keeps jsonb mode isolated to target row'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
}

foreach ($insertedSettingScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity inserted setting seed ' . $seed . ' ';

    $tests[$prefix . 'rolls back inserted json batch'] = static function (TestRunner $t) use ($plan): void {
        $t->same('rolled_back_current_json_batch', $plan['status']);
    };
    $tests[$prefix . 'uses inserted setting transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_inserted_setting_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses inserted setting savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('inserted_setting_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'applies base row and two inserted rows before failure'] = static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan['applied_statement_count']);
    };
    $tests[$prefix . 'records one failed malformed inserted batch statement'] = static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'names failed inserted batch statement'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $plan['failed_statements']);
    };
    $tests[$prefix . 'records inserted key names in applied rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['inserted_key_names'], array_slice(array_column($plan['import_plan']['applied'], 'key_name'), 1));
    };
    $tests[$prefix . 'marks inserted settings as inserted'] = static function (TestRunner $t) use ($plan): void {
        $t->same([false, true, true], array_column($plan['import_plan']['applied'], 'inserted_setting'));
    };
    $tests[$prefix . 'assigns deterministic inserted setting ids'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $finalRows = $plan['import_plan']['final_rows'];
        $inserted = array_values(array_filter(
            $finalRows,
            static fn (array $row): bool => in_array($row['key_name'], $scenario['inserted_key_names'], true)
        ));
        $t->same($scenario['inserted_setting_ids'], array_column($inserted, 'setting_id'));
    };
    $tests[$prefix . 'retains tenant id for inserted rows'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'restores original database bytes'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['database_bytes'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'marks database restored after inserted rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan['database_restored_to_before']);
    };
    $tests[$prefix . 'truncates wal to header'] = static function (TestRunner $t) use ($plan): void {
        $t->same(32, strlen($plan['wal_bytes_after']));
    };
    $tests[$prefix . 'has zero wal frames after rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'discards all current wal frames'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'restores base and inserted pages'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'wal rollback discards inserted page numbers'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'wal rollback discards three applied frames'] = static function (TestRunner $t) use ($plan): void {
        $t->same([1, 2, 3], array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'keeps malformed statement rollback isolated to fourth frame'] = static function (TestRunner $t) use ($plan): void {
        $t->same([4], array_column($plan['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'preserves jsonb mode on first inserted row'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $value = $plan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
}

foreach ($deferredScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity deferred seed ' . $seed . ' ';

    $tests[$prefix . 'keeps batch open after statement rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same('partial_rollback', $plan['status']);
    };
    $tests[$prefix . 'does not request batch rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan['rollback_required']);
    };
    $tests[$prefix . 'uses deferred transaction name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_deferred_json_import_' . $seed, $plan['transaction']);
    };
    $tests[$prefix . 'uses deferred savepoint name'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('deferred_json_batch_' . $seed, $plan['savepoint']);
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
    $tests[$prefix . 'keeps imported database bytes'] = static function (TestRunner $t) use ($plan): void {
        $t->same($plan['database_bytes_after_import'], $plan['restored_database_bytes']);
    };
    $tests[$prefix . 'does not restore database to before image'] = static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan['database_restored_to_before']);
    };
    $tests[$prefix . 'keeps original wal byte stream'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_bytes'], $plan['wal_bytes_after']);
    };
    $tests[$prefix . 'preserves wal frame count'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $plan['wal_frame_count_after']);
    };
    $tests[$prefix . 'does not mark wal truncated'] = static function (TestRunner $t) use ($plan): void {
        $t->same(false, $plan['wal_truncated']);
    };
    $tests[$prefix . 'does not discard existing wal frames'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'still records full savepoint rollback preview'] = static function (TestRunner $t) use ($plan, $scenario): void {
        $t->same($scenario['expected_restored_pages'], $plan['rollback_to_savepoint']['restored_page_numbers']);
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

foreach ($retryScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $failedPlan = $scenario['failed_plan'];
    $retryPlan = $scenario['retry_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity retry seed ' . $seed . ' ';

    $tests[$prefix . 'first batch rolls back current json writes'] = static function (TestRunner $t) use ($failedPlan): void {
        $t->same('rolled_back_current_json_batch', $failedPlan['status']);
    };
    $tests[$prefix . 'first batch restores original database bytes'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same($scenario['database_bytes'], $failedPlan['restored_database_bytes']);
    };
    $tests[$prefix . 'first batch truncates wal to header'] = static function (TestRunner $t) use ($failedPlan): void {
        $t->same(32, strlen($failedPlan['wal_bytes_after']));
    };
    $tests[$prefix . 'first batch discards dynamic wal frames'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same($scenario['wal_frames_before'], $failedPlan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'retry starts from restored database bytes'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['restored_database_bytes'], $retryPlan['database_bytes_before']);
    };
    $tests[$prefix . 'retry starts from rolled back wal bytes'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], $retryPlan['wal_bytes_before']);
    };
    $tests[$prefix . 'retry succeeds without rollback request'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same('ready', $retryPlan['status']);
        $t->same(false, $retryPlan['rollback_required']);
    };
    $tests[$prefix . 'retry has no failed statements'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same([], $retryPlan['failed_statements']);
        $t->same(0, $retryPlan['failed_statement_count']);
    };
    $tests[$prefix . 'retry applies all corrected statements'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same(3, $retryPlan['applied_statement_count']);
    };
    $tests[$prefix . 'retry mutates database image'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same(true, $retryPlan['database_changed_before_rollback']);
        $t->same(false, $retryPlan['database_restored_to_before']);
    };
    $tests[$prefix . 'retry keeps wal header after successful import'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], $retryPlan['wal_bytes_after']);
    };
    $tests[$prefix . 'retry records three applied page numbers'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['expected_retry_pages'], array_column($retryPlan['import_plan']['applied'], 'page_number'));
    };
    $tests[$prefix . 'retry records ordered wal frame indexes'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same([1, 2, 3], array_column($retryPlan['import_plan']['applied'], 'wal_frame_index'));
    };
    $tests[$prefix . 'retry keeps tenant isolation'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($retryPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'retry savepoint rollback preview covers corrected pages'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['expected_retry_pages'], $retryPlan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'retry wal rollback preview covers corrected frames'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same([1, 2, 3], array_column($retryPlan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
    };
    $tests[$prefix . 'retry preserves jsonb mode on catalog row'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $value = $retryPlan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'retry fixes formerly malformed payload row'] = static function (TestRunner $t) use ($retryPlan, $seed): void {
        $t->same('retry_mark_fixed_payload_' . $seed, $retryPlan['import_plan']['applied'][2]['statement']);
    };
}

foreach ($preexistingRetryScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $failedPlan = $scenario['failed_plan'];
    $retryPlan = $scenario['retry_plan'];
    $prefix = 'sqlite application wal rollback json dynamic parity preexisting retry seed ' . $seed . ' ';

    $tests[$prefix . 'first batch rolls back current json writes'] = static function (TestRunner $t) use ($failedPlan): void {
        $t->same('rolled_back_current_json_batch', $failedPlan['status']);
    };
    $tests[$prefix . 'first batch preserves preexisting wal frame count'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $failedPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'first batch truncates only after prefix'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same($scenario['expected_truncate_bytes'], $failedPlan['wal_truncate_to_bytes']);
        $t->same($scenario['expected_truncate_bytes'], strlen($failedPlan['wal_bytes_after']));
    };
    $tests[$prefix . 'first batch keeps committed prefix bytes'] = static function (TestRunner $t) use ($failedPlan, $scenario): void {
        $t->same(substr($scenario['wal_bytes'], 0, $scenario['expected_truncate_bytes']), $failedPlan['wal_bytes_after']);
    };
    $tests[$prefix . 'first batch discards only current json frames'] = static function (TestRunner $t) use ($failedPlan): void {
        $t->same(3, $failedPlan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'retry starts from restored database bytes'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['restored_database_bytes'], $retryPlan['database_bytes_before']);
    };
    $tests[$prefix . 'retry starts from preserved wal prefix'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], $retryPlan['wal_bytes_before']);
    };
    $tests[$prefix . 'retry succeeds without rollback request'] = static function (TestRunner $t) use ($retryPlan): void {
        $t->same('ready', $retryPlan['status']);
        $t->same(false, $retryPlan['rollback_required']);
    };
    $tests[$prefix . 'retry leaves prefix wal byte stream intact'] = static function (TestRunner $t) use ($failedPlan, $retryPlan): void {
        $t->same($failedPlan['wal_bytes_after'], $retryPlan['wal_bytes_after']);
    };
    $tests[$prefix . 'retry reports prefix frame count after success'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $retryPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'retry savepoint rollback preview starts after prefix'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['preexisting_frames'], $retryPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
    };
    $tests[$prefix . 'retry records corrected frame indexes after prefix'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $start = $scenario['preexisting_frames'] + 1;
        $t->same([$start, $start + 1, $start + 2], array_column($retryPlan['import_plan']['applied'], 'wal_frame_index'));
    };
    $tests[$prefix . 'retry savepoint rollback preview covers corrected pages'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['expected_retry_pages'], $retryPlan['rollback_to_savepoint']['restored_page_numbers']);
    };
    $tests[$prefix . 'retry wal rollback preview covers corrected pages'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same($scenario['expected_retry_pages'], $retryPlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'retry keeps tenant isolation'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($retryPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'retry preserves jsonb mode on catalog row'] = static function (TestRunner $t) use ($retryPlan, $scenario): void {
        $value = $retryPlan['import_plan']['applied'][1]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'retry fixes formerly malformed payload row'] = static function (TestRunner $t) use ($retryPlan, $seed): void {
        $t->same('prefix_retry_fixed_payload_success_' . $seed, $retryPlan['import_plan']['applied'][2]['statement']);
    };
}

foreach ($missingWalTailScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $prefix = 'sqlite application wal rollback json dynamic parity missing wal tail seed ' . $seed . ' ';

    $tests[$prefix . 'rejects truncated current batch wal bytes'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(
            'SQLite Application JSON import rollback WAL bytes are missing current batch frame(s): ' . implode(', ', $scenario['missing_frame_indexes']),
            $scenario['exception_message']
        );
    };
    $tests[$prefix . 'keeps committed prefix frame count in short wal'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['short_frame_count'] >= $scenario['preexisting_frames']);
    };
    $tests[$prefix . 'short wal is still frame aligned'] = static function (TestRunner $t) use ($scenario): void {
        $frameSize = 24 + $scenario['page_size'];
        $t->same(0, (strlen($scenario['short_wal_bytes']) - 32) % $frameSize);
    };
    $tests[$prefix . 'short wal frame count reflects missing tail'] = static function (TestRunner $t) use ($scenario): void {
        $t->same($scenario['expected_frame_count'] - $scenario['missing_frames'], $scenario['short_frame_count']);
    };
}

foreach ($partialWalTailScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $prefix = 'sqlite application wal rollback json dynamic parity partial wal tail seed ' . $seed . ' ';

    $tests[$prefix . 'rejects unaligned wal bytes'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(
            'SQLite Application JSON import rollback WAL bytes have a partial frame tail',
            $scenario['exception_message']
        );
    };
    $tests[$prefix . 'keeps at least one current batch frame before partial tail'] = static function (TestRunner $t) use ($scenario): void {
        $t->same((int) $scenario['preexisting_frames'] + 1, $scenario['complete_frame_count']);
    };
    $tests[$prefix . 'partial wal has frame remainder'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(
            $scenario['partial_payload_bytes'],
            (strlen($scenario['partial_wal_bytes']) - 32) % $scenario['frame_size']
        );
    };
    $tests[$prefix . 'partial wal tail is not full frame sized'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['partial_payload_bytes'] > 0);
        $t->same(true, $scenario['partial_payload_bytes'] < $scenario['frame_size']);
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

$tests['sqlite application wal rollback json dynamic parity rejects zero deferred scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero preexisting wal scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero tenant collision scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicTenantCollisionScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero inserted setting scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicInsertedSettingRollbackScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero retry scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicRetryAfterRollbackScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero preexisting retry scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalRetryScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero missing wal tail scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicMissingWalTailScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects zero partial wal tail scenarios'] = static function (TestRunner $t): void {
    try {
        SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios(0);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity explicit small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(3);
    $t->same([101, 102, 103], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([6, 7, 8], array_column($smallBatch, 'wal_frames_before'));
};

$tests['sqlite application wal rollback json dynamic parity deferred small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(3);
    $t->same([701, 702, 703], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([5, 6, 7], array_column($smallBatch, 'wal_frames_before'));
};

$tests['sqlite application wal rollback json dynamic parity preexisting wal small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios(3);
    $t->same([1201, 1202, 1203], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([3, 4, 5], array_column($smallBatch, 'preexisting_frames'));
    $t->same([6, 7, 8], array_column($smallBatch, 'wal_frames_before'));
};

$tests['sqlite application wal rollback json dynamic parity tenant collision small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicTenantCollisionScenarios(3);
    $t->same([2101, 2102, 2103], array_column($smallBatch, 'target_tenant_id'));
    $t->same([3101, 3102, 3103], array_column($smallBatch, 'stable_tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
};

$tests['sqlite application wal rollback json dynamic parity inserted setting small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicInsertedSettingRollbackScenarios(3);
    $t->same([4101, 4102, 4103], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([[5003, 5004], [10003, 10004], [15003, 15004]], array_column($smallBatch, 'inserted_setting_ids'));
};

$tests['sqlite application wal rollback json dynamic parity retry small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicRetryAfterRollbackScenarios(3);
    $t->same([901, 902, 903], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([7, 8, 9], array_column($smallBatch, 'wal_frames_before'));
};

$tests['sqlite application wal rollback json dynamic parity preexisting retry small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalRetryScenarios(3);
    $t->same([1501, 1502, 1503], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([2, 3, 4], array_column($smallBatch, 'preexisting_frames'));
};

$tests['sqlite application wal rollback json dynamic parity missing wal tail small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicMissingWalTailScenarios(3);
    $t->same([1201, 1202, 1203], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([4, 6, 6], array_column($smallBatch, 'short_frame_count'));
};

$tests['sqlite application wal rollback json dynamic parity partial wal tail small batch remains deterministic'] = static function (TestRunner $t): void {
    $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios(3);
    $t->same([1201, 1202, 1203], array_column($smallBatch, 'tenant_id'));
    $t->same([512, 1024, 512], array_column($smallBatch, 'page_size'));
    $t->same([4, 5, 6], array_column($smallBatch, 'complete_frame_count'));
};

$tests['sqlite application wal rollback json dynamic parity rejects wal header page size mismatch'] = static function (TestRunner $t) use ($scenarios): void {
    $scenario = $scenarios[0];
    $walBytes = substr_replace(
        $scenario['wal_bytes'],
        pack('N', 1024),
        8,
        4
    );

    try {
        SQLiteJsonImportRollbackWalPlan::plan(
            [],
            [],
            [
                'database_bytes' => $scenario['database_bytes'],
                'page_size' => 512,
                'wal_bytes' => $walBytes,
            ]
        );
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite Application JSON import rollback WAL page size must match the database page size', $exception->getMessage());
        return;
    }

    $t->same('rejected', 'accepted');
};

$tests['sqlite application wal rollback json dynamic parity rejects invalid wal magic'] = static function (TestRunner $t) use ($scenarios): void {
    $scenario = $scenarios[0];
    $walBytes = substr_replace(
        $scenario['wal_bytes'],
        pack('N', 0x12345678),
        0,
        4
    );

    try {
        SQLiteJsonImportRollbackWalPlan::plan(
            [],
            [],
            [
                'database_bytes' => $scenario['database_bytes'],
                'page_size' => $scenario['page_size'],
                'wal_bytes' => $walBytes,
            ]
        );
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite Application JSON import rollback WAL bytes require a valid WAL header', $exception->getMessage());
        return;
    }

    $t->same('rejected', 'accepted');
};

return $tests;

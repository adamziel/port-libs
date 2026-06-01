<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows205 = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 20, 'key_value' => 'https://one.test'],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'homepage', 'load_policy' => 'manual', 'status' => 'live', 'bytes' => 21, 'key_value' => 'https://homepage.test'],
    ['setting_id' => 3, 'tenant_id' => 1, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 12, 'key_value' => 'feed'],
    ['setting_id' => 4, 'tenant_id' => 2, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 24, 'key_value' => 'https://two.test'],
    ['setting_id' => 5, 'tenant_id' => 2, 'key_name' => 'pending_profile', 'load_policy' => 'no', 'status' => 'queued', 'bytes' => 7, 'key_value' => 'profile'],
    ['setting_id' => 6, 'tenant_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'key_value' => 'rules'],
    ['setting_id' => 7, 'tenant_id' => 3, 'key_name' => 'orphaned_cache', 'load_policy' => 'yes', 'status' => 'staged', 'bytes' => 5, 'key_value' => 'cache'],
    ['setting_id' => 8, 'tenant_id' => 4, 'key_name' => 'base_url', 'load_policy' => 'yes', 'status' => 'live', 'bytes' => 30, 'key_value' => 'https://four.test'],
    ['setting_id' => 9, 'tenant_id' => 5, 'key_name' => 'cache_old', 'load_policy' => 'no', 'status' => 'stale', 'bytes' => 3, 'key_value' => 'old'],
];

$tables205 = ['app_settings' => $rows205];
$unique205 = [['tenant_id', 'load_policy']];

$releaseReplace205 = "UPDATE OR REPLACE app_settings SET (tenant_id, load_policy, status, key_value, bytes) = (4, 'yes', 'released205', key_value || ':released205', bytes + 10) WHERE (tenant_id, key_name) IN ((3, 'orphaned_cache')) RETURNING setting_id, tenant_id, key_name, load_policy, status, key_value, bytes, (tenant_id, load_policy) IS (4, 'yes') AS tuple_is ORDER BY setting_id";
$releaseDelete205 = "DELETE FROM app_settings WHERE (tenant_id, load_policy) IN ((1, 'manual'), (5, 'no')) RETURNING setting_id, tenant_id, key_name, load_policy, status, (tenant_id, load_policy) IS DISTINCT FROM (1, 'yes') AS distinct_from_base ORDER BY setting_id";
$nextUpdate205 = "UPDATE app_settings SET (status, key_value, bytes) = ('release_followup_read', key_value || ':release_followup_read', bytes + 1) WHERE (tenant_id, load_policy) IN ((4, 'yes'), (1, 'no')) RETURNING setting_id, tenant_id, key_name, load_policy, status, key_value, bytes ORDER BY setting_id";
$nextDelete205 = "DELETE FROM app_settings WHERE (tenant_id, load_policy) IN ((4, 'yes'), (2, 'yes')) RETURNING setting_id, tenant_id, key_name, load_policy, status, bytes ORDER BY setting_id";

$releaseReplaceResult205 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($releaseReplace205, $tables205, 'setting_id', $unique205);
$releaseDeleteResult205 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($releaseDelete205, $releaseReplaceResult205()['tables'], 'setting_id', $unique205);
$nextUpdateResult205 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($nextUpdate205, $releaseDeleteResult205()['tables'], 'setting_id', $unique205);
$nextDeleteResult205 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($nextDelete205, $nextUpdateResult205()['tables'], 'setting_id', $unique205);
$plan205 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint(
    $tables205,
    [$releaseReplace205, $releaseDelete205],
    [$nextUpdate205, $nextDelete205],
    $unique205,
);
$blockedPlan205 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint(
    $tables205,
    [$releaseReplace205, $releaseDelete205],
    [$nextUpdate205],
    $unique205,
    'app_settings_rowvalue_release_release_followup_read',
    'setting_id',
    ['release_token' => 'app.rowvalue.release.followup.read', 'expected_release_token' => 'stale.release.followup.read'],
);
$cursorBlockedPlan205 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint(
    $tables205,
    [$releaseReplace205, $releaseDelete205],
    [$nextUpdate205],
    $unique205,
    'app_settings_rowvalue_release_release_followup_read',
    'setting_id',
    ['next_cursor' => 'app.rowvalue.followup.cursor', 'expected_next_cursor' => 'stale.followup.cursor'],
);

$cases205 = [
    'parser release replace row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($releaseReplace205)['where'], "(tenant_id, key_name) IN ((3, 'orphaned_cache'))"],
    'parser release delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($releaseDelete205)['where'], "(tenant_id, load_policy) IN ((1, 'manual'), (5, 'no'))"],
    'parser next update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($nextUpdate205)['where'], "(tenant_id, load_policy) IN ((4, 'yes'), (1, 'no'))"],
    'parser next delete row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($nextDelete205)['where'], "(tenant_id, load_policy) IN ((4, 'yes'), (2, 'yes'))"],

    'release replace selected ids' => [static fn (): mixed => $releaseReplaceResult205()['plan']->selectedIds, [7]],
    'release replace returns released row' => [static fn (): mixed => array_column($releaseReplaceResult205()['returning'], 'setting_id'), [7]],
    'release replace tuple is true' => [static fn (): mixed => $releaseReplaceResult205()['returning'][0]['tuple_is'], 1],
    'release replace deletes conflicting row eight' => [static fn (): mixed => array_column($releaseReplaceResult205()['deleted_conflict_rows'], 'setting_id'), [8]],
    'release replace current row seven load_policy' => [static fn (): mixed => array_column($releaseReplaceResult205()['tables']['app_settings'], 'load_policy', 'setting_id')[7], 'yes'],
    'release replace current excludes row eight' => [static fn (): mixed => in_array(8, array_column($releaseReplaceResult205()['tables']['app_settings'], 'setting_id'), true), false],

    'release delete selected ids' => [static fn (): mixed => $releaseDeleteResult205()['plan']->selectedIds, [2, 9]],
    'release delete returning ids' => [static fn (): mixed => array_column($releaseDeleteResult205()['returning'], 'setting_id'), [2, 9]],
    'release delete distinct flags' => [static fn (): mixed => array_column($releaseDeleteResult205()['returning'], 'distinct_from_base'), [1, 1]],
    'release delete current ids' => [static fn (): mixed => array_column($releaseDeleteResult205()['tables']['app_settings'], 'setting_id'), [1, 3, 4, 5, 6, 7]],

    'next update sees released row seven' => [static fn (): mixed => $nextUpdateResult205()['plan']->selectedIds, [3, 7]],
    'next update returning ids' => [static fn (): mixed => array_column($nextUpdateResult205()['returning'], 'setting_id'), [3, 7]],
    'next update row seven status' => [static fn (): mixed => array_column($nextUpdateResult205()['tables']['app_settings'], 'status', 'setting_id')[7], 'release_followup_read'],
    'next update row seven value chains release' => [static fn (): mixed => array_column($nextUpdateResult205()['returning'], 'key_value', 'setting_id')[7], 'cache:released205:release_followup_read'],
    'next update row three stale current read' => [static fn (): mixed => array_column($nextUpdateResult205()['returning'], 'key_value', 'setting_id')[3], 'feed:release_followup_read'],
    'next update does not resurrect row eight' => [static fn (): mixed => in_array(8, array_column($nextUpdateResult205()['tables']['app_settings'], 'setting_id'), true), false],

    'next delete sees next update current source' => [static fn (): mixed => $nextDeleteResult205()['plan']->selectedIds, [4, 7]],
    'next delete returning ids' => [static fn (): mixed => array_column($nextDeleteResult205()['returning'], 'setting_id'), [4, 7]],
    'next delete returns updated row seven status' => [static fn (): mixed => array_column($nextDeleteResult205()['returning'], 'status', 'setting_id')[7], 'release_followup_read'],
    'next delete final ids' => [static fn (): mixed => array_column($nextDeleteResult205()['tables']['app_settings'], 'setting_id'), [1, 3, 5, 6]],

    'plan status' => [static fn (): mixed => $plan205()['status'], 'rowvalue-update-delete-returning-release-current-source-release_followup_read'],
    'plan savepoint' => [static fn (): mixed => $plan205()['savepoint'], 'app_settings_rowvalue_release_release_followup_read'],
    'plan release admitted' => [static fn (): mixed => $plan205()['release_admitted_release_followup_read'], true],
    'plan next cursor matches' => [static fn (): mixed => $plan205()['next_cursor_matches_release_followup_read'], true],
    'plan released before next source' => [static fn (): mixed => $plan205()['savepoint_released_before_next_source_release_followup_read'], true],
    'plan next read released current source' => [static fn (): mixed => $plan205()['next_read_released_current_source_release_followup_read'], true],
    'plan savepoint image original ids' => [static fn (): mixed => array_column($plan205()['savepoint_image_tables']['app_settings'], 'setting_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'plan released current ids' => [static fn (): mixed => array_column($plan205()['released_current_source_tables']['app_settings'], 'setting_id'), [1, 3, 4, 5, 6, 7]],
    'plan next source equals released' => [static fn (): mixed => $plan205()['next_source_tables'], $plan205()['released_current_source_tables']],
    'plan current source final ids' => [static fn (): mixed => array_column($plan205()['current_source_tables']['app_settings'], 'setting_id'), [1, 3, 5, 6]],
    'plan savepoint statement phases' => [static fn (): mixed => array_column($plan205()['savepoint_statements'], 'phase'), ['savepoint-before-release-release_followup_read', 'savepoint-before-release-release_followup_read']],
    'plan next statement phases' => [static fn (): mixed => array_column($plan205()['next_statements'], 'phase'), ['next-after-release-current-source-release_followup_read', 'next-after-release-current-source-release_followup_read']],
    'plan savepoint source ids first' => [static fn (): mixed => array_column($plan205()['savepoint_statements'][0]['source_rows'], 'setting_id'), [7]],
    'plan savepoint source ids second' => [static fn (): mixed => array_column($plan205()['savepoint_statements'][1]['source_rows'], 'setting_id'), [2, 9]],
    'plan next source ids first' => [static fn (): mixed => array_column($plan205()['next_statements'][0]['source_rows'], 'setting_id'), [3, 7]],
    'plan next source row seven released value' => [static fn (): mixed => array_column($plan205()['next_statements'][0]['source_rows'], 'key_value', 'setting_id')[7], 'cache:released205'],
    'plan next source ids second' => [static fn (): mixed => array_column($plan205()['next_statements'][1]['source_rows'], 'setting_id'), [4, 7]],
    'plan next delete row seven updated status' => [static fn (): mixed => array_column($plan205()['next_statements'][1]['source_rows'], 'status', 'setting_id')[7], 'release_followup_read'],
    'plan released returning count' => [static fn (): mixed => $plan205()['released_returning_count'], 3],
    'plan next returning count' => [static fn (): mixed => $plan205()['next_returning_count'], 4],
    'plan conflict delete count' => [static fn (): mixed => $plan205()['released_conflict_delete_count'], 1],
    'plan changed tables after release' => [static fn (): mixed => $plan205()['changed_tables_after_release'], ['app_settings']],
    'plan changed tables after next' => [static fn (): mixed => $plan205()['changed_tables_after_next'], ['app_settings']],
    'plan row count' => [static fn (): mixed => $plan205()['row_counts']['app_settings'], 4],
    'plan receipt admitted' => [static fn (): mixed => $plan205()['release_receipt_release_followup_read']['admitted'], true],
    'plan dependency release' => [static fn (): mixed => in_array('sqlite-rowvalue-savepoint-release-current-source-release_followup_read', $plan205()['dependencies'], true), true],
    'plan dependency next' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-release-feeds-next-statement-release_followup_read', $plan205()['dependencies'], true), true],
    'plan non overlap names prior rollback' => [static fn (): mixed => str_contains($plan205()['non_overlap_release_followup_read'], 'next178 OR ROLLBACK'), true],
    'blocked release status' => [static fn (): mixed => $blockedPlan205()['status'], 'rowvalue-update-delete-returning-release-current-source-blocked-release_followup_read'],
    'blocked release next source original' => [static fn (): mixed => $blockedPlan205()['next_source_tables'], $blockedPlan205()['savepoint_image_tables']],
    'blocked release cursor still matches' => [static fn (): mixed => $blockedPlan205()['next_cursor_matches_release_followup_read'], true],
    'blocked cursor status' => [static fn (): mixed => $cursorBlockedPlan205()['status'], 'rowvalue-update-delete-returning-release-current-source-blocked-release_followup_read'],
    'blocked cursor release admitted' => [static fn (): mixed => $cursorBlockedPlan205()['release_admitted_release_followup_read'], true],
    'blocked cursor mismatch' => [static fn (): mixed => $cursorBlockedPlan205()['next_cursor_matches_release_followup_read'], false],

    'malformed empty savepoint statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint($tables205, [], [$nextUpdate205], $unique205), InvalidArgumentException::class],
    'malformed empty next statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint($tables205, [$releaseReplace205], [], $unique205), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint($tables205, [$releaseReplace205], [$nextUpdate205], []), InvalidArgumentException::class],
    'malformed savepoint name rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint($tables205, [$releaseReplace205], [$nextUpdate205], $unique205, 'bad-name'), InvalidArgumentException::class],
    'malformed release token rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint($tables205, [$releaseReplace205], [$nextUpdate205], $unique205, 'app_settings_rowvalue_release_release_followup_read', 'setting_id', ['release_token' => 'bad token']), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint(['app_settings' => ['bad']], [$releaseReplace205], [$nextUpdate205], $unique205), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases205 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source release_followup_read ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

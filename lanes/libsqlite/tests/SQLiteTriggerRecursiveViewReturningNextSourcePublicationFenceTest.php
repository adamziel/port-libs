<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows178 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView178 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-178-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-178-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-178',
];
$nextView178 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-178-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-178-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain-178',
];
$currentInput178 = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'load_policy_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInput178 = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'load_policy_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning178 = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan178 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNextSourcePublicationFence(
    $rows178,
    $currentInput178,
    $nextInput178,
    $currentView178,
    $nextView178,
    $returning178,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_178',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 8,
        'restart_cursor' => 'app-recursive-view-returning-restart-178',
        'snapshot_token' => 'app.recursive.view.returning.snapshot.178',
        'expected_snapshot_token' => 'app.recursive.view.returning.snapshot.178',
        'current_schema_cookie' => 178,
        'expected_current_schema_cookie' => 178,
    ],
);

$release178 = static fn (): array => $plan178();
$hold178 = static fn (): array => $plan178(['savepoint_action' => 'hold']);
$partial178 = static fn (): array => $plan178(['drained_current_pages' => 1]);
$staleToken178 = static fn (): array => $plan178(['expected_snapshot_token' => 'app.recursive.view.returning.snapshot.stale']);
$staleCookie178 = static fn (): array => $plan178(['expected_current_schema_cookie' => 179]);
$wide178 = static fn (): array => $plan178(['page_size' => 3]);

$cases178 = [
    'release status' => [static fn (): mixed => $release178()['status_next178'], 'trigger-recursive-view-returning-current-source-snapshot-released-next178'],
    'release keeps next175 status' => [static fn (): mixed => $release178()['status_next175'], 'trigger-recursive-view-returning-savepoint-released-next-source-next175'],
    'snapshot token retained' => [static fn (): mixed => $release178()['snapshot_token_next178'], 'app.recursive.view.returning.snapshot.178'],
    'expected snapshot token retained' => [static fn (): mixed => $release178()['expected_snapshot_token_next178'], 'app.recursive.view.returning.snapshot.178'],
    'schema cookie retained' => [static fn (): mixed => $release178()['current_schema_cookie_next178'], 178],
    'expected schema cookie retained' => [static fn (): mixed => $release178()['expected_current_schema_cookie_next178'], 178],
    'snapshot token matches' => [static fn (): mixed => $release178()['snapshot_token_matches_next178'], true],
    'schema cookie matches' => [static fn (): mixed => $release178()['schema_cookie_matches_next178'], true],
    'snapshot stable' => [static fn (): mixed => $release178()['current_source_snapshot_stable_next178'], true],
    'statement row count' => [static fn (): mixed => $release178()['statement_returning_row_count_next178'], 8],
    'current row count' => [static fn (): mixed => $release178()['current_returning_row_count_next178'], 4],
    'next row count' => [static fn (): mixed => $release178()['next_returning_row_count_next178'], 4],
    'queued next row count' => [static fn (): mixed => $release178()['queued_next_row_count_next178'], 0],
    'source order current then next' => [static fn (): mixed => $release178()['returning_source_order_next178'], ['current', 'next']],
    'visible names order' => [static fn (): mixed => array_column($release178()['visible_returning_rows_next178'], 'returning_key_name'), ['module_seed', 'base_url', 'module_seed_retry', 'module_seed_retry_retry', 'routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'visible phases order' => [static fn (): mixed => array_column($release178()['visible_returning_rows_next178'], 'statement_source'), ['current', 'current', 'current', 'current', 'next', 'next', 'next', 'next']],
    'visible page ordinals' => [static fn (): mixed => array_column($release178()['visible_returning_rows_next178'], 'returning_page'), [0, 0, 1, 1, 0, 0, 1, 1]],
    'visible row ordinals' => [static fn (): mixed => array_column($release178()['visible_returning_rows_next178'], 'returning_row_ordinal'), [0, 1, 2, 3, 4, 5, 6, 7]],
    'visible snapshot tokens' => [static fn (): mixed => array_values(array_unique(array_column($release178()['visible_returning_rows_next178'], 'returning_snapshot_token'))), ['app.recursive.view.returning.snapshot.178']],
    'visible schema cookies' => [static fn (): mixed => array_values(array_unique(array_column($release178()['visible_returning_rows_next178'], 'returning_schema_cookie'))), [178]],
    'first current old value preserved' => [static fn (): mixed => $release178()['current_source_returning_rows_next178'][0]['returning']['old_value'], null],
    'base_url old value preserved' => [static fn (): mixed => $release178()['current_source_returning_rows_next178'][1]['returning']['old_value'], 'https://old.test'],
    'next landing_url old value preserved' => [static fn (): mixed => $release178()['next_source_returning_rows_next178'][1]['returning']['old_value'], 'https://landing_url.test'],
    'next recursive trigger source retained' => [static fn (): mixed => $release178()['next_source_returning_rows_next178'][2]['returning']['trigger_source_alias'], 'main@trigger-cookie-178-next'],
    'release decision' => [static fn (): mixed => $release178()['returning_snapshot_plan_next178']['decision'], 'publish-current-then-next-returning'],
    'release restart not required' => [static fn (): mixed => $release178()['returning_snapshot_plan_next178']['restart_required'], false],
    'release plan visible rows' => [static fn (): mixed => $release178()['returning_snapshot_plan_next178']['visible_rows'], 8],
    'release plan current rows' => [static fn (): mixed => $release178()['returning_snapshot_plan_next178']['current_rows'], 4],
    'release plan next rows' => [static fn (): mixed => $release178()['returning_snapshot_plan_next178']['next_rows'], 4],
    'release plan queued rows' => [static fn (): mixed => $release178()['returning_snapshot_plan_next178']['queued_next_rows'], 0],
    'release boundary' => [static fn (): mixed => $release178()['yield_boundary_next178'], 'recursive-view-returning-next178-current-source-snapshot-stable-then-next'],
    'release blocked reasons empty' => [static fn (): mixed => $release178()['blocked_reasons_next178'], []],

    'hold status' => [static fn (): mixed => $hold178()['status_next178'], 'trigger-recursive-view-returning-current-source-snapshot-held-next178'],
    'hold statement rows current only' => [static fn (): mixed => $hold178()['statement_returning_row_count_next178'], 4],
    'hold next rows hidden' => [static fn (): mixed => $hold178()['next_returning_row_count_next178'], 0],
    'hold queued next rows' => [static fn (): mixed => $hold178()['queued_next_row_count_next178'], 4],
    'hold queued names' => [static fn (): mixed => array_column($hold178()['queued_next_source_rows_next178'], 'returning_key_name'), ['routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'hold source order current only' => [static fn (): mixed => $hold178()['returning_source_order_next178'], ['current']],
    'hold blocked reason' => [static fn (): mixed => $hold178()['blocked_reasons_next178'], ['savepoint-release-not-requested']],
    'hold decision' => [static fn (): mixed => $hold178()['returning_snapshot_plan_next178']['decision'], 'hold-next-source-returning'],
    'hold boundary fences' => [static fn (): mixed => $hold178()['yield_boundary_next178'], 'recursive-view-returning-next178-current-source-snapshot-fences-next'],

    'partial status held' => [static fn (): mixed => $partial178()['status_next178'], 'trigger-recursive-view-returning-current-source-snapshot-held-next178'],
    'partial current visible rows' => [static fn (): mixed => $partial178()['statement_returning_row_count_next178'], 2],
    'partial queued next rows' => [static fn (): mixed => $partial178()['queued_next_row_count_next178'], 4],
    'partial blocked reason' => [static fn (): mixed => $partial178()['blocked_reasons_next178'], ['current-returning-cursor-not-exhausted']],

    'stale token status restarts' => [static fn (): mixed => $staleToken178()['status_next178'], 'trigger-recursive-view-returning-current-source-snapshot-restart-next178'],
    'stale token mismatch false' => [static fn (): mixed => $staleToken178()['snapshot_token_matches_next178'], false],
    'stale token schema still matches' => [static fn (): mixed => $staleToken178()['schema_cookie_matches_next178'], true],
    'stale token snapshot unstable' => [static fn (): mixed => $staleToken178()['current_source_snapshot_stable_next178'], false],
    'stale token rows current only' => [static fn (): mixed => array_column($staleToken178()['visible_returning_rows_next178'], 'statement_source'), ['current', 'current', 'current', 'current']],
    'stale token queues next rows' => [static fn (): mixed => $staleToken178()['queued_next_row_count_next178'], 4],
    'stale token blocked reason' => [static fn (): mixed => $staleToken178()['blocked_reasons_next178'], ['current-source-returning-snapshot-token-mismatch']],
    'stale token decision' => [static fn (): mixed => $staleToken178()['returning_snapshot_plan_next178']['decision'], 'restart-current-source-returning-snapshot'],
    'stale token restart required' => [static fn (): mixed => $staleToken178()['returning_snapshot_plan_next178']['restart_required'], true],

    'stale cookie status restarts' => [static fn (): mixed => $staleCookie178()['status_next178'], 'trigger-recursive-view-returning-current-source-snapshot-restart-next178'],
    'stale cookie token matches' => [static fn (): mixed => $staleCookie178()['snapshot_token_matches_next178'], true],
    'stale cookie mismatch false' => [static fn (): mixed => $staleCookie178()['schema_cookie_matches_next178'], false],
    'stale cookie blocked reason' => [static fn (): mixed => $staleCookie178()['blocked_reasons_next178'], ['current-source-view-schema-cookie-mismatch']],
    'stale cookie rows current only' => [static fn (): mixed => $staleCookie178()['next_returning_row_count_next178'], 0],

    'wide current row pages' => [static fn (): mixed => array_column($wide178()['current_source_returning_rows_next178'], 'returning_page'), [0, 0, 0, 1]],
    'wide next row pages' => [static fn (): mixed => array_column($wide178()['next_source_returning_rows_next178'], 'returning_page'), [0, 0, 0, 1]],
    'wide statement row count unchanged' => [static fn (): mixed => $wide178()['statement_returning_row_count_next178'], 8],

    'dependency closure marker' => [static fn (): mixed => $release178()['dependency_closure_next178'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-savepoint-and-schema-cookie-model'],
    'dependency includes next178' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next178', $release178()['dependencies_next178'], true), true],
    'dependency includes snapshot fence' => [static fn (): mixed => in_array('sqlite-returning-current-source-snapshot-token-fence', $release178()['dependencies_next178'], true), true],
    'dependency includes schema fence' => [static fn (): mixed => in_array('sqlite-returning-view-schema-cookie-fence', $release178()['dependencies_next178'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-recursive-view-returning-current-source-next178', $release178()['dependencies_next178'], true), true],
    'non overlap note names next175' => [static fn (): mixed => str_contains($release178()['non_overlap_next178'], 'next175 savepoint fencing'), true],

    'bad snapshot token throws' => [static fn (): mixed => $plan178(['snapshot_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected snapshot token throws' => [static fn (): mixed => $plan178(['expected_snapshot_token' => 'bad token']), InvalidArgumentException::class],
    'negative schema cookie throws' => [static fn (): mixed => $plan178(['current_schema_cookie' => -1]), InvalidArgumentException::class],
    'negative expected schema cookie throws' => [static fn (): mixed => $plan178(['expected_current_schema_cookie' => -1]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases178 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next178 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

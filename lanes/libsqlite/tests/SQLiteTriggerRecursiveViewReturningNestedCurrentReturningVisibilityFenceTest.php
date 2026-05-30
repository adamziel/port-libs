<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows185 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView185 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-185-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-185-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-185',
];
$nextView185 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-185-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-185-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain-185',
];
$currentInput185 = [
    ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'autoload_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
];
$nextInput185 = [
    ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'autoload_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning185 = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan185 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNestedCurrentReturningVisibilityFence(
    $rows185,
    $currentInput185,
    $nextInput185,
    $currentView185,
    $nextView185,
    $returning185,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_185',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 18,
        'restart_cursor' => 'wp-recursive-view-returning-restart-185',
        'snapshot_token' => 'wp.recursive.view.returning.snapshot.185',
        'expected_snapshot_token' => 'wp.recursive.view.returning.snapshot.185',
        'current_schema_cookie' => 185,
        'expected_current_schema_cookie' => 185,
        'current_source_generation' => 'wp.recursive.view.current.185',
        'expected_current_source_generation' => 'wp.recursive.view.current.185',
        'trigger_source_generation' => 'wp.recursive.trigger.current.185',
        'expected_trigger_source_generation' => 'wp.recursive.trigger.current.185',
        'returning_cursor_generation' => 'wp.recursive.returning.cursor.185',
        'nested_epoch' => 'wp.recursive.view.nested.185',
        'expected_nested_epoch' => 'wp.recursive.view.nested.185',
        'required_nested_depths' => [1, 2],
        'drained_nested_depths' => [1, 2],
    ],
);

$drained185 = static fn (): array => $plan185();
$heldDepth185 = static fn (): array => $plan185(['drained_nested_depths' => [1]]);
$staleEpoch185 = static fn (): array => $plan185(['expected_nested_epoch' => 'wp.recursive.view.nested.stale']);
$notRequested185 = static fn (): array => $plan185(['outer_publish_requested' => false]);
$baseStale185 = static fn (): array => $plan185(['expected_current_source_generation' => 'wp.recursive.view.current.stale']);
$noRecursive185 = static fn (): array => $plan185(['recursive_triggers' => false, 'drained_current_pages' => 1, 'required_nested_depths' => [], 'drained_nested_depths' => []]);

$cases185 = [
    'drained status' => [static fn (): mixed => $drained185()['status_next185'], 'trigger-recursive-view-returning-current-source-nested-drained-next185'],
    'drained keeps next182 status' => [static fn (): mixed => $drained185()['status_next182'], 'trigger-recursive-view-returning-current-source-generation-released-next182'],
    'nested epoch retained' => [static fn (): mixed => $drained185()['nested_epoch_next185'], 'wp.recursive.view.nested.185'],
    'expected nested epoch retained' => [static fn (): mixed => $drained185()['expected_nested_epoch_next185'], 'wp.recursive.view.nested.185'],
    'nested epoch matches' => [static fn (): mixed => $drained185()['nested_epoch_matches_next185'], true],
    'required depths sorted' => [static fn (): mixed => $drained185()['required_nested_depths_next185'], [1, 2]],
    'drained depths sorted' => [static fn (): mixed => $drained185()['drained_nested_depths_next185'], [1, 2]],
    'missing depths empty' => [static fn (): mixed => $drained185()['missing_nested_depths_next185'], []],
    'nested depths drained' => [static fn (): mixed => $drained185()['nested_depths_drained_next185'], true],
    'outer publish requested' => [static fn (): mixed => $drained185()['outer_publish_requested_next185'], true],
    'outer publish allowed' => [static fn (): mixed => $drained185()['outer_publish_allowed_next185'], true],
    'visible row count' => [static fn (): mixed => $drained185()['visible_row_count_next185'], 8],
    'visible current row count' => [static fn (): mixed => $drained185()['visible_current_row_count_next185'], 4],
    'visible next row count' => [static fn (): mixed => $drained185()['visible_next_row_count_next185'], 4],
    'held next row count empty' => [static fn (): mixed => $drained185()['held_next_row_count_next185'], 0],
    'outer current rows' => [static fn (): mixed => array_column($drained185()['outer_current_returning_rows_next185'], 'returning_option_name'), ['plugin_seed', 'siteurl']],
    'nested current rows' => [static fn (): mixed => array_column($drained185()['nested_current_returning_rows_next185'], 'returning_option_name'), ['plugin_seed_retry', 'plugin_seed_retry_retry']],
    'nested row depths' => [static fn (): mixed => array_column($drained185()['nested_current_returning_rows_next185'], 'returning_nested_depth'), [1, 2]],
    'source order current then next' => [static fn (): mixed => $drained185()['returning_source_order_next185'], ['current', 'next']],
    'visible names' => [static fn (): mixed => array_column($drained185()['visible_returning_rows_next185'], 'returning_option_name'), ['plugin_seed', 'siteurl', 'plugin_seed_retry', 'plugin_seed_retry_retry', 'rewrite_rules', 'home', 'rewrite_rules_next_retry', 'rewrite_rules_next_retry_next_retry']],
    'all rows tagged epoch' => [static fn (): mixed => array_values(array_unique(array_column($drained185()['visible_returning_rows_next185'], 'returning_nested_epoch'))), ['wp.recursive.view.nested.185']],
    'current nested flags' => [static fn (): mixed => array_column($drained185()['visible_current_returning_rows_next185'], 'returning_nested_depth_drained'), [false, false, true, true]],
    'decision drained' => [static fn (): mixed => $drained185()['nested_depth_drain_plan_next185']['decision'], 'publish-current-nested-then-next'],
    'yield boundary drained' => [static fn (): mixed => $drained185()['yield_boundary_next185'], 'recursive-view-returning-next185-nested-current-source-drained-then-next'],
    'blocked reasons drained empty' => [static fn (): mixed => $drained185()['blocked_reasons_next185'], []],

    'held depth status' => [static fn (): mixed => $heldDepth185()['status_next185'], 'trigger-recursive-view-returning-current-source-nested-held-next185'],
    'held depth missing depth' => [static fn (): mixed => $heldDepth185()['missing_nested_depths_next185'], [2]],
    'held depth not drained' => [static fn (): mixed => $heldDepth185()['nested_depths_drained_next185'], false],
    'held depth outer publish denied' => [static fn (): mixed => $heldDepth185()['outer_publish_allowed_next185'], false],
    'held depth visible rows current only' => [static fn (): mixed => array_column($heldDepth185()['visible_returning_rows_next185'], 'statement_source'), ['current', 'current', 'current', 'current']],
    'held depth held next rows' => [static fn (): mixed => array_column($heldDepth185()['held_next_source_rows_next185'], 'returning_option_name'), ['rewrite_rules', 'home', 'rewrite_rules_next_retry', 'rewrite_rules_next_retry_next_retry']],
    'held depth row count' => [static fn (): mixed => $heldDepth185()['held_next_row_count_next185'], 4],
    'held depth reason' => [static fn (): mixed => $heldDepth185()['blocked_reasons_next185'], ['nested-recursive-returning-depths-not-drained']],
    'held depth decision' => [static fn (): mixed => $heldDepth185()['nested_depth_drain_plan_next185']['decision'], 'hold-next-until-nested-depths-drain'],
    'held depth boundary' => [static fn (): mixed => $heldDepth185()['yield_boundary_next185'], 'recursive-view-returning-next185-nested-current-source-fences-next'],

    'stale epoch status' => [static fn (): mixed => $staleEpoch185()['status_next185'], 'trigger-recursive-view-returning-current-source-nested-restart-next185'],
    'stale epoch match false' => [static fn (): mixed => $staleEpoch185()['nested_epoch_matches_next185'], false],
    'stale epoch depths still drained' => [static fn (): mixed => $staleEpoch185()['nested_depths_drained_next185'], true],
    'stale epoch publish denied' => [static fn (): mixed => $staleEpoch185()['outer_publish_allowed_next185'], false],
    'stale epoch reason' => [static fn (): mixed => $staleEpoch185()['blocked_reasons_next185'], ['nested-recursive-returning-epoch-mismatch']],
    'stale epoch decision' => [static fn (): mixed => $staleEpoch185()['nested_depth_drain_plan_next185']['decision'], 'restart-nested-recursive-returning-epoch'],

    'not requested held' => [static fn (): mixed => $notRequested185()['status_next185'], 'trigger-recursive-view-returning-current-source-nested-held-next185'],
    'not requested flag' => [static fn (): mixed => $notRequested185()['outer_publish_requested_next185'], false],
    'not requested publish denied' => [static fn (): mixed => $notRequested185()['outer_publish_allowed_next185'], false],
    'not requested reason' => [static fn (): mixed => $notRequested185()['blocked_reasons_next185'], ['outer-returning-publish-not-requested']],
    'not requested held next count' => [static fn (): mixed => $notRequested185()['held_next_row_count_next185'], 4],

    'base stale restart status' => [static fn (): mixed => $baseStale185()['status_next185'], 'trigger-recursive-view-returning-current-source-nested-held-next185'],
    'base stale keeps next182 reason' => [static fn (): mixed => $baseStale185()['blocked_reasons_next185'], ['current-view-source-generation-mismatch']],
    'base stale current only' => [static fn (): mixed => $baseStale185()['visible_next_row_count_next185'], 0],
    'base stale held next count' => [static fn (): mixed => $baseStale185()['held_next_row_count_next185'], 4],

    'non recursive status' => [static fn (): mixed => $noRecursive185()['status_next185'], 'trigger-recursive-view-returning-current-source-nested-drained-next185'],
    'non recursive nested rows empty' => [static fn (): mixed => $noRecursive185()['nested_current_row_count_next185'], 0],
    'non recursive outer rows' => [static fn (): mixed => $noRecursive185()['outer_current_row_count_next185'], 2],
    'non recursive visible names' => [static fn (): mixed => array_column($noRecursive185()['visible_returning_rows_next185'], 'returning_option_name'), ['plugin_seed', 'siteurl', 'rewrite_rules', 'home']],

    'dependency closure marker' => [static fn (): mixed => $drained185()['dependency_closure_next185'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-and-nested-depth-drain-model'],
    'dependency includes next185' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next185', $drained185()['dependencies_next185'], true), true],
    'dependency includes depth fence' => [static fn (): mixed => in_array('sqlite-returning-nested-recursive-depth-drain-fence', $drained185()['dependencies_next185'], true), true],
    'dependency includes epoch fence' => [static fn (): mixed => in_array('sqlite-returning-nested-recursive-epoch-fence', $drained185()['dependencies_next185'], true), true],
    'dependency includes application' => [static fn (): mixed => in_array('application-recursive-view-returning-current-source-next185', $drained185()['dependencies_next185'], true), true],
    'non overlap names next182' => [static fn (): mixed => str_contains($drained185()['non_overlap_next185'], 'next182 generation'), true],

    'bad nested epoch throws' => [static fn (): mixed => $plan185(['nested_epoch' => 'bad epoch']), InvalidArgumentException::class],
    'bad expected epoch throws' => [static fn (): mixed => $plan185(['expected_nested_epoch' => 'bad epoch']), InvalidArgumentException::class],
    'bad required depths shape throws' => [static fn (): mixed => $plan185(['required_nested_depths' => ['x' => 1]]), InvalidArgumentException::class],
    'bad required depth throws' => [static fn (): mixed => $plan185(['required_nested_depths' => [-1]]), InvalidArgumentException::class],
    'bad drained depth throws' => [static fn (): mixed => $plan185(['drained_nested_depths' => ['one']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases185 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next185 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

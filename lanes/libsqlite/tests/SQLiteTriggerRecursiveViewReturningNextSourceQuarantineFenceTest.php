<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows182 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView182 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-182-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-182-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain-182',
];
$nextView182 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-182-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-182-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain-182',
];
$currentInput182 = [
    ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'autoload_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
];
$nextInput182 = [
    ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'autoload_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning182 = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan182 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNextSourceQuarantineFence(
    $rows182,
    $currentInput182,
    $nextInput182,
    $currentView182,
    $nextView182,
    $returning182,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_182',
        'max_depth' => 2,
        'page_size' => 2,
        'drained_current_pages' => 2,
        'savepoint_action' => 'release',
        'current_source_epoch' => 12,
        'restart_cursor' => 'wp-recursive-view-returning-restart-182',
        'snapshot_token' => 'wp.recursive.view.returning.snapshot.182',
        'expected_snapshot_token' => 'wp.recursive.view.returning.snapshot.182',
        'current_schema_cookie' => 182,
        'expected_current_schema_cookie' => 182,
        'current_source_generation' => 'wp.recursive.view.current.182',
        'expected_current_source_generation' => 'wp.recursive.view.current.182',
        'trigger_source_generation' => 'wp.recursive.trigger.current.182',
        'expected_trigger_source_generation' => 'wp.recursive.trigger.current.182',
        'returning_cursor_generation' => 'wp.recursive.returning.cursor.182',
    ],
);

$release182 = static fn (): array => $plan182();
$hold182 = static fn (): array => $plan182(['savepoint_action' => 'hold']);
$staleView182 = static fn (): array => $plan182(['expected_current_source_generation' => 'wp.recursive.view.current.stale']);
$staleTrigger182 = static fn (): array => $plan182(['expected_trigger_source_generation' => 'wp.recursive.trigger.current.stale']);
$staleBoth182 = static fn (): array => $plan182([
    'expected_current_source_generation' => 'wp.recursive.view.current.stale',
    'expected_trigger_source_generation' => 'wp.recursive.trigger.current.stale',
]);
$staleSnapshot182 = static fn (): array => $plan182(['expected_snapshot_token' => 'wp.recursive.view.returning.snapshot.stale']);
$partial182 = static fn (): array => $plan182(['drained_current_pages' => 1]);

$cases182 = [
    'release status' => [static fn (): mixed => $release182()['status_next182'], 'trigger-recursive-view-returning-current-source-generation-released-next182'],
    'release keeps next178 status' => [static fn (): mixed => $release182()['status_next178'], 'trigger-recursive-view-returning-current-source-snapshot-released-next178'],
    'current generation retained' => [static fn (): mixed => $release182()['current_source_generation_next182'], 'wp.recursive.view.current.182'],
    'expected current generation retained' => [static fn (): mixed => $release182()['expected_current_source_generation_next182'], 'wp.recursive.view.current.182'],
    'trigger generation retained' => [static fn (): mixed => $release182()['trigger_source_generation_next182'], 'wp.recursive.trigger.current.182'],
    'expected trigger generation retained' => [static fn (): mixed => $release182()['expected_trigger_source_generation_next182'], 'wp.recursive.trigger.current.182'],
    'cursor generation retained' => [static fn (): mixed => $release182()['returning_cursor_generation_next182'], 'wp.recursive.returning.cursor.182'],
    'current generation matches' => [static fn (): mixed => $release182()['current_source_generation_matches_next182'], true],
    'trigger generation matches' => [static fn (): mixed => $release182()['trigger_source_generation_matches_next182'], true],
    'generation stable' => [static fn (): mixed => $release182()['current_source_generation_stable_next182'], true],
    'next publish allowed' => [static fn (): mixed => $release182()['next_source_publish_allowed_next182'], true],
    'statement row count' => [static fn (): mixed => $release182()['statement_returning_row_count_next182'], 8],
    'current row count' => [static fn (): mixed => $release182()['current_returning_row_count_next182'], 4],
    'next row count' => [static fn (): mixed => $release182()['next_returning_row_count_next182'], 4],
    'quarantined row count' => [static fn (): mixed => $release182()['quarantined_next_row_count_next182'], 0],
    'source order current then next' => [static fn (): mixed => $release182()['returning_source_order_next182'], ['current', 'next']],
    'visible names order' => [static fn (): mixed => array_column($release182()['visible_returning_rows_next182'], 'returning_option_name'), ['plugin_seed', 'siteurl', 'plugin_seed_retry', 'plugin_seed_retry_retry', 'rewrite_rules', 'home', 'rewrite_rules_next_retry', 'rewrite_rules_next_retry_next_retry']],
    'visible phases order' => [static fn (): mixed => array_column($release182()['visible_returning_rows_next182'], 'statement_source'), ['current', 'current', 'current', 'current', 'next', 'next', 'next', 'next']],
    'visible generation ordinals' => [static fn (): mixed => array_column($release182()['visible_returning_rows_next182'], 'returning_generation_ordinal'), [0, 1, 2, 3, 4, 5, 6, 7]],
    'visible view generations' => [static fn (): mixed => array_values(array_unique(array_column($release182()['visible_returning_rows_next182'], 'returning_current_source_generation'))), ['wp.recursive.view.current.182']],
    'visible trigger generations' => [static fn (): mixed => array_values(array_unique(array_column($release182()['visible_returning_rows_next182'], 'returning_trigger_source_generation'))), ['wp.recursive.trigger.current.182']],
    'visible cursor generations' => [static fn (): mixed => array_values(array_unique(array_column($release182()['visible_returning_rows_next182'], 'returning_cursor_generation'))), ['wp.recursive.returning.cursor.182']],
    'release decision' => [static fn (): mixed => $release182()['returning_generation_plan_next182']['decision'], 'publish-current-then-next-generation'],
    'release restart not required' => [static fn (): mixed => $release182()['returning_generation_plan_next182']['restart_required'], false],
    'release plan visible rows' => [static fn (): mixed => $release182()['returning_generation_plan_next182']['visible_rows'], 8],
    'release plan current rows' => [static fn (): mixed => $release182()['returning_generation_plan_next182']['current_rows'], 4],
    'release plan next rows' => [static fn (): mixed => $release182()['returning_generation_plan_next182']['next_rows'], 4],
    'release plan quarantined rows' => [static fn (): mixed => $release182()['returning_generation_plan_next182']['quarantined_next_rows'], 0],
    'release boundary' => [static fn (): mixed => $release182()['yield_boundary_next182'], 'recursive-view-returning-next182-current-generation-stable-then-next'],
    'release blocked reasons empty' => [static fn (): mixed => $release182()['blocked_reasons_next182'], []],

    'hold status' => [static fn (): mixed => $hold182()['status_next182'], 'trigger-recursive-view-returning-current-source-generation-held-next182'],
    'hold publish denied' => [static fn (): mixed => $hold182()['next_source_publish_allowed_next182'], false],
    'hold statement rows current only' => [static fn (): mixed => $hold182()['statement_returning_row_count_next182'], 4],
    'hold next rows hidden' => [static fn (): mixed => $hold182()['next_returning_row_count_next182'], 0],
    'hold quarantined next rows' => [static fn (): mixed => $hold182()['quarantined_next_row_count_next182'], 4],
    'hold quarantined names' => [static fn (): mixed => array_column($hold182()['quarantined_next_source_rows_next182'], 'returning_option_name'), ['rewrite_rules', 'home', 'rewrite_rules_next_retry', 'rewrite_rules_next_retry_next_retry']],
    'hold source order current only' => [static fn (): mixed => $hold182()['returning_source_order_next182'], ['current']],
    'hold blocked reason' => [static fn (): mixed => $hold182()['blocked_reasons_next182'], ['savepoint-release-not-requested']],
    'hold decision' => [static fn (): mixed => $hold182()['returning_generation_plan_next182']['decision'], 'hold-next-source-generation'],
    'hold boundary fences' => [static fn (): mixed => $hold182()['yield_boundary_next182'], 'recursive-view-returning-next182-current-generation-fences-next'],

    'stale view status restarts' => [static fn (): mixed => $staleView182()['status_next182'], 'trigger-recursive-view-returning-current-source-generation-restart-next182'],
    'stale view current match false' => [static fn (): mixed => $staleView182()['current_source_generation_matches_next182'], false],
    'stale view trigger still matches' => [static fn (): mixed => $staleView182()['trigger_source_generation_matches_next182'], true],
    'stale view generation unstable' => [static fn (): mixed => $staleView182()['current_source_generation_stable_next182'], false],
    'stale view rows current only' => [static fn (): mixed => array_column($staleView182()['visible_returning_rows_next182'], 'statement_source'), ['current', 'current', 'current', 'current']],
    'stale view quarantines next' => [static fn (): mixed => $staleView182()['quarantined_next_row_count_next182'], 4],
    'stale view blocked reason' => [static fn (): mixed => $staleView182()['blocked_reasons_next182'], ['current-view-source-generation-mismatch']],
    'stale view decision' => [static fn (): mixed => $staleView182()['returning_generation_plan_next182']['decision'], 'restart-current-source-generation'],

    'stale trigger status restarts' => [static fn (): mixed => $staleTrigger182()['status_next182'], 'trigger-recursive-view-returning-current-source-generation-restart-next182'],
    'stale trigger current still matches' => [static fn (): mixed => $staleTrigger182()['current_source_generation_matches_next182'], true],
    'stale trigger match false' => [static fn (): mixed => $staleTrigger182()['trigger_source_generation_matches_next182'], false],
    'stale trigger rows current only' => [static fn (): mixed => $staleTrigger182()['next_returning_row_count_next182'], 0],
    'stale trigger quarantines next' => [static fn (): mixed => array_column($staleTrigger182()['quarantined_next_source_rows_next182'], 'statement_source'), ['next', 'next', 'next', 'next']],
    'stale trigger blocked reason' => [static fn (): mixed => $staleTrigger182()['blocked_reasons_next182'], ['current-trigger-source-generation-mismatch']],

    'stale both reasons' => [static fn (): mixed => $staleBoth182()['blocked_reasons_next182'], ['current-view-source-generation-mismatch', 'current-trigger-source-generation-mismatch']],
    'stale both restart required' => [static fn (): mixed => $staleBoth182()['returning_generation_plan_next182']['restart_required'], true],
    'stale both quarantined row ordinals' => [static fn (): mixed => array_column($staleBoth182()['quarantined_next_source_rows_next182'], 'returning_row_ordinal'), [4, 5, 6, 7]],

    'stale snapshot status restarts' => [static fn (): mixed => $staleSnapshot182()['status_next182'], 'trigger-recursive-view-returning-current-source-generation-restart-next182'],
    'stale snapshot keeps next178 reason' => [static fn (): mixed => $staleSnapshot182()['blocked_reasons_next182'], ['current-source-returning-snapshot-token-mismatch']],
    'stale snapshot generations still match' => [static fn (): mixed => $staleSnapshot182()['current_source_generation_matches_next182'] && $staleSnapshot182()['trigger_source_generation_matches_next182'], true],
    'stale snapshot quarantines next' => [static fn (): mixed => $staleSnapshot182()['quarantined_next_row_count_next182'], 4],

    'partial status held' => [static fn (): mixed => $partial182()['status_next182'], 'trigger-recursive-view-returning-current-source-generation-held-next182'],
    'partial statement rows' => [static fn (): mixed => $partial182()['statement_returning_row_count_next182'], 2],
    'partial quarantined next rows' => [static fn (): mixed => $partial182()['quarantined_next_row_count_next182'], 4],
    'partial blocked reason' => [static fn (): mixed => $partial182()['blocked_reasons_next182'], ['current-returning-cursor-not-exhausted']],

    'dependency closure marker' => [static fn (): mixed => $release182()['dependency_closure_next182'], 'no-new-support-component-reuses-native-recursive-view-returning-current-source-generation-and-trigger-cookie-model'],
    'dependency includes next182' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next182', $release182()['dependencies_next182'], true), true],
    'dependency includes view generation' => [static fn (): mixed => in_array('sqlite-returning-current-view-source-generation-fence', $release182()['dependencies_next182'], true), true],
    'dependency includes trigger generation' => [static fn (): mixed => in_array('sqlite-returning-current-trigger-source-generation-fence', $release182()['dependencies_next182'], true), true],
    'dependency includes wordpress' => [static fn (): mixed => in_array('wordpress-recursive-view-returning-current-source-next182', $release182()['dependencies_next182'], true), true],
    'non overlap note names next178' => [static fn (): mixed => str_contains($release182()['non_overlap_next182'], 'next178 snapshot/schema-cookie'), true],

    'bad current generation throws' => [static fn (): mixed => $plan182(['current_source_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad expected current generation throws' => [static fn (): mixed => $plan182(['expected_current_source_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad trigger generation throws' => [static fn (): mixed => $plan182(['trigger_source_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad expected trigger generation throws' => [static fn (): mixed => $plan182(['expected_trigger_source_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad cursor generation throws' => [static fn (): mixed => $plan182(['returning_cursor_generation' => 'bad generation']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases182 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next182 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

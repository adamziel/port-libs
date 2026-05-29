<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows211 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView211 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-211-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-211-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-211',
];
$nextView211 = $currentView211;
$nextView211['source'] = 'main@view-cookie-211-next';
$nextView211['trigger_source'] = 'main@trigger-cookie-211-next';
$postResetView211 = $currentView211;
$postResetView211['source'] = 'main@view-cookie-211-post-reset';
$postResetView211['trigger_source'] = 'main@trigger-cookie-211-post-reset';
$followingView211 = $currentView211;
$followingView211['source'] = 'main@view-cookie-211-following';
$followingView211['trigger_source'] = 'main@trigger-cookie-211-following';
$currentInput211 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput211 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput211 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$followingInput211 = [
    ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
];
$returning211 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan211 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::currentSourceSealFence(
    $rows211,
    $currentInput211,
    $nextInput211,
    $currentView211,
    $nextView211,
    $returning211,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_211',
        'cursor_name' => 'wp_recursive_view_returning_cursor_211',
        'admit_next_source' => true,
        'rollback_token' => 'wp.rollback.current.211',
        'reset_generation' => 'wp-current-reset-211',
        'post_reset_current_source_token' => 'wp.current.source.postreset.211',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.211',
        'post_reset_view' => $postResetView211,
        'post_reset_input' => $postResetInput211,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.211',
        'next_cursor' => 'wp.returning.next.cursor.211',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.211',
        'following_current_source_token' => 'wp.current.source.following.211',
        'following_cursor' => 'wp.returning.following.cursor.211',
        'following_current_view' => $followingView211,
        'following_current_input' => $followingInput211,
        'following_generation' => 'wp-following-current-211',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.211',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.211',
        'recursive_child_generation' => 'wp-recursive-child-current-211',
        'current_generation_next203' => 'wp.current.recursive.returning.generation.211',
        'expected_current_generation_next203' => 'wp.current.recursive.returning.generation.211',
        'current_handoff_cursor_next203' => 'wp.returning.current.handoff.cursor.211',
        'current_generation_commit_marker_next203' => 'wp.current.recursive.returning.commit.211',
        'auto_ack_current_generation_receipts_next203' => true,
        'current_source_drain_token_next209' => 'wp.current.source.drain.211',
        'current_view_cookie_next209' => 'main@view-cookie-211-current',
        'current_trigger_cookie_next209' => 'main@trigger-cookie-211-current',
        'auto_ack_current_source_watermarks_next209' => true,
    ],
);

$released211 = static fn (): array => $plan211();
$seal211 = static fn (): string => $released211()['current_source_seal_next211'];
$sourceHeld211 = static fn (): array => $plan211(['expected_current_source_seal_next211' => '0123456789abcdef0123456789abcdef']);
$rowCountHeld211 = static fn (): array => $plan211(['expected_current_source_row_count_next211' => 3]);
$baseHeld211 = static fn (): array => $plan211(['auto_ack_current_source_watermarks_next209' => false]);
$tampered211 = static fn (): array => $plan211([
    'tamper_current_returning_sources_next211' => [1 => 'main@trigger-cookie-211-next-stale'],
    'expected_current_source_seal_next211' => $seal211(),
]);
$custom211 = static fn (): array => $plan211(['expected_current_source_seal_next211' => $seal211(), 'expected_current_source_row_count_next211' => 2]);

$cases211 = [
    'released status' => [static fn (): mixed => $released211()['status_next211'], 'trigger-recursive-view-returning-current-source-next211-source-sealed'],
    'source held status' => [static fn (): mixed => $sourceHeld211()['status_next211'], 'trigger-recursive-view-returning-current-source-next211-source-seal-held'],
    'tampered status' => [static fn (): mixed => $tampered211()['status_next211'], 'trigger-recursive-view-returning-current-source-next211-source-seal-held'],
    'row count held status' => [static fn (): mixed => $rowCountHeld211()['status_next211'], 'trigger-recursive-view-returning-current-source-next211-row-count-held'],
    'base held status' => [static fn (): mixed => $baseHeld211()['status_next211'], 'trigger-recursive-view-returning-current-source-next211-base-held'],
    'savepoint retained' => [static fn (): mixed => $released211()['savepoint'], 'wp_recursive_view_211'],
    'base next209 released' => [static fn (): mixed => $released211()['base']['status_next209'], 'trigger-recursive-view-returning-current-source-next209-drain-released'],
    'base next209 held' => [static fn (): mixed => $baseHeld211()['base']['status_next209'], 'trigger-recursive-view-returning-current-source-next209-drain-held'],
    'base visible released' => [static fn (): mixed => $released211()['base_next_source_visible_next211'], true],
    'base visible held' => [static fn (): mixed => $baseHeld211()['base_next_source_visible_next211'], false],
    'seal is hex' => [static fn (): mixed => preg_match('/^[a-f0-9]{32}$/', $released211()['current_source_seal_next211']), 1],
    'expected seal defaults actual' => [static fn (): mixed => $released211()['expected_current_source_seal_next211'], $seal211()],
    'explicit seal accepted' => [static fn (): mixed => $custom211()['current_source_seal_matches_next211'], true],
    'seal mismatch detected' => [static fn (): mixed => $sourceHeld211()['current_source_seal_matches_next211'], false],
    'tampered seal mismatch detected' => [static fn (): mixed => $tampered211()['current_source_seal_matches_next211'], false],
    'current row count' => [static fn (): mixed => $released211()['current_source_row_count_next211'], 2],
    'expected current row count' => [static fn (): mixed => $released211()['expected_current_source_row_count_next211'], 2],
    'row count matches released' => [static fn (): mixed => $released211()['current_source_row_count_matches_next211'], true],
    'row count mismatch detected' => [static fn (): mixed => $rowCountHeld211()['current_source_row_count_matches_next211'], false],
    'watermarks unique' => [static fn (): mixed => $released211()['current_source_watermarks_unique_next211'], true],
    'next visible released' => [static fn (): mixed => $released211()['next_source_visible_after_current_source_seal_next211'], true],
    'next held source' => [static fn (): mixed => $sourceHeld211()['next_source_visible_after_current_source_seal_next211'], false],
    'next held row count' => [static fn (): mixed => $rowCountHeld211()['next_source_visible_after_current_source_seal_next211'], false],
    'next held base' => [static fn (): mixed => $baseHeld211()['next_source_visible_after_current_source_seal_next211'], false],
    'current rows count' => [static fn (): mixed => count($released211()['current_source_rows_next211']), 2],
    'attempted next rows count' => [static fn (): mixed => count($released211()['attempted_next_source_rows_next211']), 2],
    'visible released count' => [static fn (): mixed => count($released211()['visible_returning_rows_next211']), 4],
    'visible source held current only' => [static fn (): mixed => count($sourceHeld211()['visible_returning_rows_next211']), 2],
    'held released count' => [static fn (): mixed => count($released211()['held_next_source_rows_next211']), 0],
    'held source count' => [static fn (): mixed => count($sourceHeld211()['held_next_source_rows_next211']), 2],
    'held base count' => [static fn (): mixed => count($baseHeld211()['held_next_source_rows_next211']), 2],
    'visible names released' => [static fn (): mixed => array_column($released211()['visible_returning_payloads_next211'], 'name'), ['blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held names source' => [static fn (): mixed => array_column($sourceHeld211()['held_next_returning_payloads_next211'], 'name'), ['home', 'next_plugin']],
    'current phase stamped' => [static fn (): mixed => array_values(array_unique(array_column($released211()['current_source_rows_next211'], 'source_seal_phase_next211'))), ['current']],
    'next phase stamped' => [static fn (): mixed => array_values(array_unique(array_column($released211()['attempted_next_source_rows_next211'], 'source_seal_phase_next211'))), ['next']],
    'current visible while source held' => [static fn (): mixed => array_values(array_unique(array_column($sourceHeld211()['current_source_rows_next211'], 'visible_after_current_source_seal_next211'))), [true]],
    'next visible released unique' => [static fn (): mixed => array_values(array_unique(array_column($released211()['attempted_next_source_rows_next211'], 'visible_after_current_source_seal_next211'))), [true]],
    'next held source unique' => [static fn (): mixed => array_values(array_unique(array_column($sourceHeld211()['attempted_next_source_rows_next211'], 'visible_after_current_source_seal_next211'))), [false]],
    'current seal stamped' => [static fn (): mixed => array_values(array_unique(array_column($released211()['current_source_rows_next211'], 'current_source_seal_next211'))), [$seal211()]],
    'next seal stamped' => [static fn (): mixed => array_values(array_unique(array_column($released211()['attempted_next_source_rows_next211'], 'current_source_seal_next211'))), [$seal211()]],
    'expected seal stamped' => [static fn (): mixed => array_values(array_unique(array_column($released211()['current_source_rows_next211'], 'expected_current_source_seal_next211'))), [$seal211()]],
    'expected count stamped' => [static fn (): mixed => array_values(array_unique(array_column($released211()['attempted_next_source_rows_next211'], 'expected_current_source_row_count_next211'))), [2]],
    'blocked source reason' => [static fn (): mixed => $sourceHeld211()['blocked_reasons_next211'], ['current-source-returning-seal-mismatch']],
    'blocked tampered reason' => [static fn (): mixed => $tampered211()['blocked_reasons_next211'], ['current-source-returning-seal-mismatch']],
    'blocked row count reason' => [static fn (): mixed => $rowCountHeld211()['blocked_reasons_next211'], ['current-source-returning-row-count-mismatch']],
    'blocked base reasons' => [static fn (): mixed => $baseHeld211()['blocked_reasons_next211'], ['current-source-watermark-missing']],
    'released reasons empty' => [static fn (): mixed => $released211()['blocked_reasons_next211'], []],
    'held next reason tagged' => [static fn (): mixed => $sourceHeld211()['attempted_next_source_rows_next211'][0]['held_by_current_source_seal_reasons_next211'], ['current-source-returning-seal-mismatch']],
    'released next reason empty' => [static fn (): mixed => $released211()['attempted_next_source_rows_next211'][0]['held_by_current_source_seal_reasons_next211'], []],
    'tampered source applied' => [static fn (): mixed => $tampered211()['current_source_rows_next211'][1]['returning']['trigger_source_alias'], 'main@trigger-cookie-211-next-stale'],
    'untampered source retained' => [static fn (): mixed => $released211()['current_source_rows_next211'][1]['returning']['trigger_source_alias'], 'main@trigger-cookie-211-current'],
    'plan base visible' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['base_next_source_visible'], true],
    'plan current rows' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['current_rows'], 2],
    'plan expected current rows' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['expected_current_rows'], 2],
    'plan row count matches' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['row_count_matches'], true],
    'plan seal echoed' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['source_seal'], $seal211()],
    'plan expected seal echoed' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['expected_source_seal'], $seal211()],
    'plan seal matches' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['source_seal_matches'], true],
    'plan watermarks unique' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['watermarks_unique'], true],
    'plan next visible' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['next_source_visible'], true],
    'plan decision released' => [static fn (): mixed => $released211()['current_source_seal_plan_next211']['decision'], 'publish-next-source-after-current-returning-source-seal'],
    'plan decision held' => [static fn (): mixed => $sourceHeld211()['current_source_seal_plan_next211']['decision'], 'hold-next-source-until-current-returning-source-seal'],
    'yield boundary released' => [static fn (): mixed => $released211()['yield_boundary_next211'], 'recursive-view-returning-next211-current-source-sealed-then-next'],
    'yield boundary held' => [static fn (): mixed => $sourceHeld211()['yield_boundary_next211'], 'recursive-view-returning-next211-current-source-seal-fences-next'],
    'dependency closure marker' => [static fn (): mixed => $released211()['dependency_closure_next211'], 'no new support component needed; reuses recursive view RETURNING generation, current-source drain watermarks, and adds a bounded current-source RETURNING source seal'],
    'dependency includes next211' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next211', $released211()['dependencies_next211'], true), true],
    'dependency includes seal' => [static fn (): mixed => in_array('sqlite-returning-current-source-seal', $released211()['dependencies_next211'], true), true],
    'dependency includes next209' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next209', $released211()['dependencies_next211'], true), true],
    'dependency includes wordpress' => [static fn (): mixed => in_array('wordpress-recursive-view-returning-current-source-next211', $released211()['dependencies_next211'], true), true],
    'non overlap mentions next209' => [static fn (): mixed => str_contains($released211()['non_overlap_next211'], 'next209 drain-watermark'), true],
    'bad expected seal rejected' => [static fn (): mixed => $plan211(['expected_current_source_seal_next211' => 'bad']), InvalidArgumentException::class],
    'bad expected count rejected' => [static fn (): mixed => $plan211(['expected_current_source_row_count_next211' => -1]), InvalidArgumentException::class],
    'bad source override type rejected' => [static fn (): mixed => $plan211(['tamper_current_returning_sources_next211' => 'bad']), InvalidArgumentException::class],
    'bad source override index rejected' => [static fn (): mixed => $plan211(['tamper_current_returning_sources_next211' => [5 => 'main@trigger-cookie-211-stale']]), InvalidArgumentException::class],
    'bad source override value rejected' => [static fn (): mixed => $plan211(['tamper_current_returning_sources_next211' => [1 => 'bad source']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases211 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next211 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

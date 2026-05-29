<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows196 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView196 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-196-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-196-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-196',
];
$nextView196 = $currentView196;
$nextView196['source'] = 'main@view-cookie-196-next';
$nextView196['trigger_source'] = 'main@trigger-cookie-196-next';
$postResetView196 = $currentView196;
$postResetView196['source'] = 'main@view-cookie-196-post-reset';
$postResetView196['trigger_source'] = 'main@trigger-cookie-196-post-reset';
$followingView196 = $currentView196;
$followingView196['source'] = 'main@view-cookie-196-following';
$followingView196['trigger_source'] = 'main@trigger-cookie-196-following';
$currentInput196 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput196 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput196 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$followingInput196 = [
    ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
];
$returning196 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan196 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext196(
    $rows196,
    $currentInput196,
    $nextInput196,
    $currentView196,
    $nextView196,
    $returning196,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_196',
        'cursor_name' => 'wp_recursive_view_returning_cursor_196',
        'current_generation' => 'wp-current-returning-196',
        'next_generation' => 'wp-next-returning-196',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.196',
        'drain_ack_token' => 'wp.returning.drain.196',
        'rollback_token' => 'wp.rollback.current.196',
        'reset_generation' => 'wp-current-reset-196',
        'post_reset_current_source_token' => 'wp.current.source.postreset.196',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.196',
        'post_reset_view' => $postResetView196,
        'post_reset_input' => $postResetInput196,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.196',
        'next_cursor' => 'wp.returning.next.cursor.196',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.196',
        'following_current_source_token' => 'wp.current.source.following.196',
        'following_cursor' => 'wp.returning.following.cursor.196',
        'following_current_view' => $followingView196,
        'following_current_input' => $followingInput196,
        'following_generation' => 'wp-following-current-196',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.196',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.196',
        'recursive_child_generation' => 'wp-recursive-child-current-196',
    ],
);

$admitted196 = static fn (): array => $plan196();
$partial196 = static fn (): array => $plan196(['recursive_child_acknowledged_ordinals' => [0]]);
$none196 = static fn (): array => $plan196(['recursive_child_acknowledged_ordinals' => []]);
$tokenHeld196 = static fn (): array => $plan196(['expected_recursive_child_source_token' => 'wp.current.source.recursive.expected.196']);
$followingHeld196 = static fn (): array => $plan196(['next_acknowledged_ordinals' => [0]]);
$custom196 = static fn (): array => $plan196([
    'recursive_child_suffix' => '_grandchild',
    'recursive_child_source_token' => 'wp.current.source.recursive.custom.196',
    'expected_recursive_child_source_token' => 'wp.current.source.recursive.custom.196',
    'recursive_child_cursor' => 'wp.returning.recursive.custom.cursor.196',
]);

$cases196 = [
    'admitted status' => [static fn (): mixed => $admitted196()['status_next196'], 'trigger-recursive-view-returning-current-source-next196-next-source-visible'],
    'partial status' => [static fn (): mixed => $partial196()['status_next196'], 'trigger-recursive-view-returning-current-source-next196-awaiting-recursive-child-acks'],
    'none status' => [static fn (): mixed => $none196()['status_next196'], 'trigger-recursive-view-returning-current-source-next196-awaiting-recursive-child-acks'],
    'token held status' => [static fn (): mixed => $tokenHeld196()['status_next196'], 'trigger-recursive-view-returning-current-source-next196-child-token-held'],
    'following held status' => [static fn (): mixed => $followingHeld196()['status_next196'], 'trigger-recursive-view-returning-current-source-next196-following-current-held'],
    'savepoint retained' => [static fn (): mixed => $admitted196()['savepoint'], 'wp_recursive_view_196'],
    'base next192 admitted' => [static fn (): mixed => $admitted196()['base']['status_next192'], 'trigger-recursive-view-returning-current-source-next192-following-current-visible'],
    'following source visible' => [static fn (): mixed => $admitted196()['following_current_source_visible_next196'], true],
    'following source held' => [static fn (): mixed => $followingHeld196()['following_current_source_visible_next196'], false],
    'recursive column retained' => [static fn (): mixed => $admitted196()['recursive_child_column_next196'], 'spawn_child'],
    'recursive suffix retained' => [static fn (): mixed => $admitted196()['recursive_child_suffix_next196'], '_child'],
    'custom suffix retained' => [static fn (): mixed => $custom196()['recursive_child_suffix_next196'], '_grandchild'],
    'following token retained' => [static fn (): mixed => $admitted196()['following_current_source_token_next196'], 'wp.current.source.following.196'],
    'child token retained' => [static fn (): mixed => $admitted196()['recursive_child_source_token_next196'], 'wp.current.source.recursive.child.196'],
    'expected child token retained' => [static fn (): mixed => $admitted196()['expected_recursive_child_source_token_next196'], 'wp.current.source.recursive.child.196'],
    'child token matches' => [static fn (): mixed => $admitted196()['recursive_child_source_token_matches_next196'], true],
    'child token mismatch' => [static fn (): mixed => $tokenHeld196()['recursive_child_source_token_matches_next196'], false],
    'child cursor retained' => [static fn (): mixed => $admitted196()['recursive_child_cursor_next196'], 'wp.returning.recursive.child.cursor.196'],
    'custom child cursor retained' => [static fn (): mixed => $custom196()['recursive_child_cursor_next196'], 'wp.returning.recursive.custom.cursor.196'],
    'child generation retained' => [static fn (): mixed => $admitted196()['recursive_child_generation_next196'], 'wp-recursive-child-current-196'],
    'child row count' => [static fn (): mixed => $admitted196()['recursive_child_row_count_next196'], 2],
    'following held has no children' => [static fn (): mixed => $followingHeld196()['recursive_child_rows_next196'], []],
    'child required ordinals' => [static fn (): mixed => $admitted196()['recursive_child_required_ordinals_next196'], [0, 1]],
    'child acknowledged ordinals' => [static fn (): mixed => $admitted196()['recursive_child_acknowledged_ordinals_next196'], [0, 1]],
    'duplicate child ack coalesced' => [static fn (): mixed => $plan196(['recursive_child_acknowledged_ordinals' => [0, 0, 1]])['recursive_child_acknowledged_ordinals_next196'], [0, 1]],
    'partial child ack retained' => [static fn (): mixed => $partial196()['recursive_child_acknowledged_ordinals_next196'], [0]],
    'child rows acknowledged' => [static fn (): mixed => $admitted196()['recursive_child_rows_acknowledged_next196'], true],
    'partial child rows not acknowledged' => [static fn (): mixed => $partial196()['recursive_child_rows_acknowledged_next196'], false],
    'next source publish allowed' => [static fn (): mixed => $admitted196()['next_source_publish_allowed_next196'], true],
    'partial next source not allowed' => [static fn (): mixed => $partial196()['next_source_publish_allowed_next196'], false],
    'token held next source not allowed' => [static fn (): mixed => $tokenHeld196()['next_source_publish_allowed_next196'], false],
    'child payload names' => [static fn (): mixed => array_column($admitted196()['recursive_child_payloads_next196'], 'name'), ['blogdescription_child', 'template_child']],
    'child payload values' => [static fn (): mixed => array_column($admitted196()['recursive_child_payloads_next196'], 'value'), ['after-next_child', 'twentytwentyfive_child']],
    'child payload old values null' => [static fn (): mixed => array_column($admitted196()['recursive_child_payloads_next196'], 'old_value'), [null, null]],
    'child payload events' => [static fn (): mixed => array_values(array_unique(array_column($admitted196()['recursive_child_payloads_next196'], 'event_name'))), ['recursive-child-current']],
    'child payload depths' => [static fn (): mixed => array_column($admitted196()['recursive_child_payloads_next196'], 'depth_value'), [1, 1]],
    'child payload ordinals' => [static fn (): mixed => array_column($admitted196()['recursive_child_payloads_next196'], 'ordinal_value'), [0, 1]],
    'child payload trigger source' => [static fn (): mixed => array_values(array_unique(array_column($admitted196()['recursive_child_payloads_next196'], 'trigger_source_alias'))), ['main@trigger-cookie-196-current']],
    'following payload spawn flags' => [static fn (): mixed => array_column($admitted196()['base']['following_current_payloads_next192'], 'spawn_child'), [true, false, true]],
    'child statement sources' => [static fn (): mixed => array_column($admitted196()['recursive_child_rows_next196'], 'statement_source'), ['recursive-child-current', 'recursive-child-current']],
    'child parent ordinals' => [static fn (): mixed => array_column($admitted196()['recursive_child_rows_next196'], 'parent_returning_row_ordinal'), [0, 2]],
    'child option names stamped' => [static fn (): mixed => array_column($admitted196()['recursive_child_rows_next196'], 'returning_option_name'), ['blogdescription_child', 'template_child']],
    'child parent tokens stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted196()['recursive_child_rows_next196'], 'parent_following_current_source_token_next196'))), ['wp.current.source.following.196']],
    'child source tokens stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted196()['recursive_child_rows_next196'], 'recursive_child_source_token_next196'))), ['wp.current.source.recursive.child.196']],
    'child cursor stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted196()['recursive_child_rows_next196'], 'recursive_child_cursor_next196'))), ['wp.returning.recursive.child.cursor.196']],
    'child generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted196()['recursive_child_rows_next196'], 'recursive_child_generation_next196'))), ['wp-recursive-child-current-196']],
    'child depth stamped' => [static fn (): mixed => array_values(array_unique(array_column($admitted196()['recursive_child_rows_next196'], 'recursive_depth_next196'))), [1]],
    'child signatures stable' => [static fn (): mixed => count(array_unique(array_column($admitted196()['recursive_child_rows_next196'], 'source_signature_next196'))), 1],
    'custom child payload names' => [static fn (): mixed => array_column($custom196()['recursive_child_payloads_next196'], 'name'), ['blogdescription_grandchild', 'template_grandchild']],
    'partial blocked reason' => [static fn (): mixed => $partial196()['blocked_reasons_next196'], ['recursive-child-returning-rows-not-acknowledged']],
    'none blocked reason' => [static fn (): mixed => $none196()['blocked_reasons_next196'], ['recursive-child-returning-rows-not-acknowledged']],
    'token blocked reason' => [static fn (): mixed => $tokenHeld196()['blocked_reasons_next196'], ['recursive-child-source-token-mismatch']],
    'following held blocked reasons' => [static fn (): mixed => $followingHeld196()['blocked_reasons_next196'], ['next-source-returning-rows-not-acknowledged', 'recursive-child-returning-rows-not-acknowledged']],
    'admitted reasons empty' => [static fn (): mixed => $admitted196()['blocked_reasons_next196'], []],
    'plan following rows visible' => [static fn (): mixed => $admitted196()['current_source_next_plan_next196']['following_rows_visible'], 3],
    'plan child rows required' => [static fn (): mixed => $admitted196()['current_source_next_plan_next196']['recursive_child_rows_required'], 2],
    'plan child rows acknowledged' => [static fn (): mixed => $admitted196()['current_source_next_plan_next196']['recursive_child_rows_acknowledged'], 2],
    'partial plan child rows acknowledged' => [static fn (): mixed => $partial196()['current_source_next_plan_next196']['recursive_child_rows_acknowledged'], 1],
    'plan child token matches' => [static fn (): mixed => $admitted196()['current_source_next_plan_next196']['child_source_token_matches'], true],
    'plan next source publish allowed' => [static fn (): mixed => $admitted196()['current_source_next_plan_next196']['next_source_publish_allowed'], true],
    'plan decision admitted' => [static fn (): mixed => $admitted196()['current_source_next_plan_next196']['decision'], 'publish-next-after-recursive-child-current-returning-drain'],
    'plan decision partial' => [static fn (): mixed => $partial196()['current_source_next_plan_next196']['decision'], 'hold-next-until-recursive-child-returning-acks'],
    'plan decision token held' => [static fn (): mixed => $tokenHeld196()['current_source_next_plan_next196']['decision'], 'hold-next-recursive-child-source-token'],
    'plan decision following held' => [static fn (): mixed => $followingHeld196()['current_source_next_plan_next196']['decision'], 'hold-next-until-following-current-visible'],
    'plan resume after child ordinal' => [static fn (): mixed => $admitted196()['current_source_next_plan_next196']['resume_after_recursive_child_ordinal'], 1],
    'yield boundary admitted' => [static fn (): mixed => $admitted196()['yield_boundary_next196'], 'recursive-view-returning-next196-following-current-child-returning-drained-next-source'],
    'yield boundary fenced' => [static fn (): mixed => $partial196()['yield_boundary_next196'], 'recursive-view-returning-next196-following-current-child-returning-fences-next-source'],
    'dependency closure marker' => [static fn (): mixed => $admitted196()['dependency_closure_next196'], 'no new support component needed; reuses next192 following-current admission and adds recursive child RETURNING drain fencing before the next source'],
    'dependency includes next196' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next196', $admitted196()['dependencies_next196'], true), true],
    'dependency includes child fence' => [static fn (): mixed => in_array('sqlite-returning-recursive-child-current-source-fence', $admitted196()['dependencies_next196'], true), true],
    'dependency includes next192' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next192', $admitted196()['dependencies_next196'], true), true],
    'non overlap mentions next192' => [static fn (): mixed => str_contains($admitted196()['non_overlap_next196'], 'next192 cursor-close'), true],
    'bad child column rejected' => [static fn (): mixed => $plan196(['recursive_child_column' => 'bad column']), InvalidArgumentException::class],
    'bad child suffix rejected' => [static fn (): mixed => $plan196(['recursive_child_suffix' => 'bad suffix']), InvalidArgumentException::class],
    'bad child token rejected' => [static fn (): mixed => $plan196(['recursive_child_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected child token rejected' => [static fn (): mixed => $plan196(['expected_recursive_child_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad child cursor rejected' => [static fn (): mixed => $plan196(['recursive_child_cursor' => 'bad cursor']), InvalidArgumentException::class],
    'bad child generation rejected' => [static fn (): mixed => $plan196(['recursive_child_generation' => 'bad generation']), InvalidArgumentException::class],
    'bad child ack list rejected' => [static fn (): mixed => $plan196(['recursive_child_acknowledged_ordinals' => 'bad-list']), InvalidArgumentException::class],
    'bad child ack ordinal rejected' => [static fn (): mixed => $plan196(['recursive_child_acknowledged_ordinals' => [-1]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases196 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next196 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows202 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView202 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-202-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-202-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'spawn_child' => 'spawn_child'],
    'audit_label' => 'current-recursive-view-trigger-202',
];
$nextView202 = $currentView202;
$nextView202['source'] = 'main@view-cookie-202-next';
$nextView202['trigger_source'] = 'main@trigger-cookie-202-next';
$postResetView202 = $currentView202;
$postResetView202['source'] = 'main@view-cookie-202-post-reset';
$postResetView202['trigger_source'] = 'main@trigger-cookie-202-post-reset';
$followingView202 = $currentView202;
$followingView202['source'] = 'main@view-cookie-202-following';
$followingView202['trigger_source'] = 'main@trigger-cookie-202-following';
$currentInput202 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput202 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$postResetInput202 = [
    ['import_id' => 30, 'name' => 'siteurl', 'value' => 'https://fresh.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 31, 'name' => 'rewrite_rules', 'value' => 'fresh-rules', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$followingInput202 = [
    ['import_id' => 40, 'name' => 'blogdescription', 'value' => 'after-next', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 41, 'name' => 'stylesheet', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => false],
    ['import_id' => 42, 'name' => 'template', 'value' => 'twentytwentyfive', 'autoload_flag' => 'yes', 'spawn_child' => true],
];
$returning202 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'spawn_child', 'as' => 'spawn_child'],
];

$plan202 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentGenerationDepthFence(
    $rows202,
    $currentInput202,
    $nextInput202,
    $currentView202,
    $nextView202,
    $returning202,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_202',
        'cursor_name' => 'wp_recursive_view_returning_cursor_202',
        'current_generation' => 'wp-current-returning-202',
        'next_generation' => 'wp-next-returning-202',
        'page_size' => 3,
        'admit_next_source' => true,
        'current_source_token' => 'wp.current.source.202',
        'drain_ack_token' => 'wp.returning.drain.202',
        'rollback_token' => 'wp.rollback.current.202',
        'reset_generation' => 'wp-current-reset-202',
        'post_reset_current_source_token' => 'wp.current.source.postreset.202',
        'post_reset_cursor' => 'wp.returning.postreset.cursor.202',
        'post_reset_view' => $postResetView202,
        'post_reset_input' => $postResetInput202,
        'fresh_acknowledged_ordinals' => [0, 1],
        'next_source_token' => 'wp.next.source.202',
        'next_cursor' => 'wp.returning.next.cursor.202',
        'next_acknowledged_ordinals' => [0, 1],
        'close_next_cursor' => 'wp.returning.next.cursor.202',
        'following_current_source_token' => 'wp.current.source.following.202',
        'following_cursor' => 'wp.returning.following.cursor.202',
        'following_current_view' => $followingView202,
        'following_current_input' => $followingInput202,
        'following_generation' => 'wp-following-current-202',
        'recursive_child_acknowledged_ordinals' => [0, 1],
        'recursive_child_source_token' => 'wp.current.source.recursive.child.202',
        'recursive_child_cursor' => 'wp.returning.recursive.child.cursor.202',
        'recursive_child_generation' => 'wp-recursive-child-current-202',
        'current_view_generation_generationDepthFence' => 'wp.current.recursive.view.202',
        'expected_current_view_generation_generationDepthFence' => 'wp.current.recursive.view.202',
        'next_view_generation_generationDepthFence' => 'wp.next.recursive.view.202',
        'returning_resume_barrier_generationDepthFence' => 'wp.returning.resume.barrier.202',
        'required_current_depths_generationDepthFence' => [0, 1],
        'acknowledged_current_depths_generationDepthFence' => [0, 1],
    ],
);

$released202 = static fn (): array => $plan202();
$depthHeld202 = static fn (): array => $plan202(['acknowledged_current_depths_generationDepthFence' => [0]]);
$generationHeld202 = static fn (): array => $plan202(['expected_current_view_generation_generationDepthFence' => 'wp.current.recursive.view.stale.202']);
$baseHeld202 = static fn (): array => $plan202(['recursive_child_acknowledged_ordinals' => [0]]);
$custom202 = static fn (): array => $plan202([
    'current_view_generation_generationDepthFence' => 'wp.current.recursive.view.custom.202',
    'expected_current_view_generation_generationDepthFence' => 'wp.current.recursive.view.custom.202',
    'next_view_generation_generationDepthFence' => 'wp.next.recursive.view.custom.202',
    'returning_resume_barrier_generationDepthFence' => 'wp.returning.resume.custom.202',
    'acknowledged_current_depths_generationDepthFence' => [1, 0, 0],
]);

$cases202 = [
    'released status' => [static fn (): mixed => $released202()['status_generationDepthFence'], 'trigger-recursive-view-returning-current-source-generation-depth-fence-next-source-visible'],
    'depth held status' => [static fn (): mixed => $depthHeld202()['status_generationDepthFence'], 'trigger-recursive-view-returning-current-source-generation-depth-fence-depth-held'],
    'generation held status' => [static fn (): mixed => $generationHeld202()['status_generationDepthFence'], 'trigger-recursive-view-returning-current-source-generation-depth-fence-generation-held'],
    'base held status' => [static fn (): mixed => $baseHeld202()['status_generationDepthFence'], 'trigger-recursive-view-returning-current-source-generation-depth-fence-base-held'],
    'base following child drain admitted' => [static fn (): mixed => $released202()['base_generationDepthFence']['status_next196'], 'trigger-recursive-view-returning-following-child-drain-next-source-visible'],
    'base following child drain held' => [static fn (): mixed => $baseHeld202()['base_generationDepthFence']['status_next196'], 'trigger-recursive-view-returning-following-child-drain-awaiting-recursive-child-acks'],
    'savepoint retained' => [static fn (): mixed => $released202()['savepoint_generationDepthFence'], 'wp_recursive_view_202'],
    'current generation retained' => [static fn (): mixed => $released202()['current_view_generation_generationDepthFence'], 'wp.current.recursive.view.202'],
    'expected generation retained' => [static fn (): mixed => $released202()['expected_current_view_generation_generationDepthFence'], 'wp.current.recursive.view.202'],
    'custom current generation retained' => [static fn (): mixed => $custom202()['current_view_generation_generationDepthFence'], 'wp.current.recursive.view.custom.202'],
    'generation matches released' => [static fn (): mixed => $released202()['current_view_generation_matches_generationDepthFence'], true],
    'generation mismatch detected' => [static fn (): mixed => $generationHeld202()['current_view_generation_matches_generationDepthFence'], false],
    'next generation retained' => [static fn (): mixed => $released202()['next_view_generation_generationDepthFence'], 'wp.next.recursive.view.202'],
    'custom next generation retained' => [static fn (): mixed => $custom202()['next_view_generation_generationDepthFence'], 'wp.next.recursive.view.custom.202'],
    'barrier retained' => [static fn (): mixed => $released202()['returning_resume_barrier_generationDepthFence'], 'wp.returning.resume.barrier.202'],
    'custom barrier retained' => [static fn (): mixed => $custom202()['returning_resume_barrier_generationDepthFence'], 'wp.returning.resume.custom.202'],
    'required depths retained' => [static fn (): mixed => $released202()['required_current_depths_generationDepthFence'], [0, 1]],
    'ack depths retained' => [static fn (): mixed => $released202()['acknowledged_current_depths_generationDepthFence'], [0, 1]],
    'ack depths normalized' => [static fn (): mixed => $custom202()['acknowledged_current_depths_generationDepthFence'], [0, 1]],
    'depths acknowledged released' => [static fn (): mixed => $released202()['current_depths_acknowledged_generationDepthFence'], true],
    'depths missing held' => [static fn (): mixed => $depthHeld202()['current_depths_acknowledged_generationDepthFence'], false],
    'base publish allowed' => [static fn (): mixed => $released202()['base_next_source_publish_allowed_generationDepthFence'], true],
    'base publish blocked' => [static fn (): mixed => $baseHeld202()['base_next_source_publish_allowed_generationDepthFence'], false],
    'next publish allowed' => [static fn (): mixed => $released202()['next_source_publish_allowed_generationDepthFence'], true],
    'next publish blocked by depth' => [static fn (): mixed => $depthHeld202()['next_source_publish_allowed_generationDepthFence'], false],
    'next publish blocked by generation' => [static fn (): mixed => $generationHeld202()['next_source_publish_allowed_generationDepthFence'], false],
    'next publish blocked by base' => [static fn (): mixed => $baseHeld202()['next_source_publish_allowed_generationDepthFence'], false],
    'current row count includes following and children' => [static fn (): mixed => $released202()['current_generation_row_count_generationDepthFence'], 5],
    'attempted next row count' => [static fn (): mixed => $released202()['attempted_next_generation_row_count_generationDepthFence'], 2],
    'visible released count' => [static fn (): mixed => $released202()['visible_row_count_generationDepthFence'], 7],
    'held released count' => [static fn (): mixed => $released202()['held_next_row_count_generationDepthFence'], 0],
    'visible depth held current only' => [static fn (): mixed => $depthHeld202()['visible_row_count_generationDepthFence'], 5],
    'held depth count' => [static fn (): mixed => $depthHeld202()['held_next_row_count_generationDepthFence'], 2],
    'visible generation held current only' => [static fn (): mixed => $generationHeld202()['visible_row_count_generationDepthFence'], 5],
    'held generation count' => [static fn (): mixed => $generationHeld202()['held_next_row_count_generationDepthFence'], 2],
    'current phases tagged' => [static fn (): mixed => array_values(array_unique(array_column($released202()['current_generation_rows_generationDepthFence'], 'generation_phase_generationDepthFence'))), ['current']],
    'next phases tagged' => [static fn (): mixed => array_values(array_unique(array_column($released202()['attempted_next_generation_rows_generationDepthFence'], 'generation_phase_generationDepthFence'))), ['next']],
    'current rows visible while held' => [static fn (): mixed => array_values(array_unique(array_column($depthHeld202()['current_generation_rows_generationDepthFence'], 'visible_after_current_generation_generationDepthFence'))), [true]],
    'next rows visible released' => [static fn (): mixed => array_values(array_unique(array_column($released202()['attempted_next_generation_rows_generationDepthFence'], 'visible_after_current_generation_generationDepthFence'))), [true]],
    'next rows held by depth' => [static fn (): mixed => array_values(array_unique(array_column($depthHeld202()['attempted_next_generation_rows_generationDepthFence'], 'visible_after_current_generation_generationDepthFence'))), [false]],
    'current generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released202()['current_generation_rows_generationDepthFence'], 'current_view_generation_generationDepthFence'))), ['wp.current.recursive.view.202']],
    'next generation stamped' => [static fn (): mixed => array_values(array_unique(array_column($released202()['attempted_next_generation_rows_generationDepthFence'], 'next_view_generation_generationDepthFence'))), ['wp.next.recursive.view.202']],
    'barrier stamped on current rows' => [static fn (): mixed => array_values(array_unique(array_column($released202()['current_generation_rows_generationDepthFence'], 'returning_resume_barrier_generationDepthFence'))), ['wp.returning.resume.barrier.202']],
    'barrier stamped on next rows' => [static fn (): mixed => array_values(array_unique(array_column($released202()['attempted_next_generation_rows_generationDepthFence'], 'returning_resume_barrier_generationDepthFence'))), ['wp.returning.resume.barrier.202']],
    'current row ordinals stamped' => [static fn (): mixed => array_column($released202()['current_generation_rows_generationDepthFence'], 'generation_row_ordinal_generationDepthFence'), [0, 1, 2, 3, 4]],
    'next row ordinals stamped' => [static fn (): mixed => array_column($released202()['attempted_next_generation_rows_generationDepthFence'], 'generation_row_ordinal_generationDepthFence'), [0, 1]],
    'visible payload names released' => [static fn (): mixed => array_column($released202()['visible_returning_payloads_generationDepthFence'], 'name'), ['blogdescription', 'stylesheet', 'template', 'blogdescription_child', 'template_child', 'home', 'next_plugin']],
    'held payload names depth' => [static fn (): mixed => array_column($depthHeld202()['held_next_returning_payloads_generationDepthFence'], 'name'), ['home', 'next_plugin']],
    'blocked empty released' => [static fn (): mixed => $released202()['blocked_reasons_generationDepthFence'], []],
    'blocked depth reason' => [static fn (): mixed => $depthHeld202()['blocked_reasons_generationDepthFence'], ['current-recursive-depths-not-acknowledged']],
    'blocked generation reason' => [static fn (): mixed => $generationHeld202()['blocked_reasons_generationDepthFence'], ['current-view-generation-mismatch']],
    'blocked base reason' => [static fn (): mixed => $baseHeld202()['blocked_reasons_generationDepthFence'], ['recursive-child-returning-rows-not-acknowledged']],
    'plan base allowed' => [static fn (): mixed => $released202()['current_source_next_plan_generationDepthFence']['base_next_source_publish_allowed'], true],
    'plan generation matches' => [static fn (): mixed => $released202()['current_source_next_plan_generationDepthFence']['current_view_generation_matches'], true],
    'plan required depths' => [static fn (): mixed => $released202()['current_source_next_plan_generationDepthFence']['required_current_depths'], [0, 1]],
    'plan acknowledged depths' => [static fn (): mixed => $released202()['current_source_next_plan_generationDepthFence']['acknowledged_current_depths'], [0, 1]],
    'plan depth ack' => [static fn (): mixed => $released202()['current_source_next_plan_generationDepthFence']['current_depths_acknowledged'], true],
    'plan next allowed' => [static fn (): mixed => $released202()['current_source_next_plan_generationDepthFence']['next_source_publish_allowed'], true],
    'plan decision released' => [static fn (): mixed => $released202()['current_source_next_plan_generationDepthFence']['decision'], 'publish-next-after-current-generation-depth-acks'],
    'plan decision depth' => [static fn (): mixed => $depthHeld202()['current_source_next_plan_generationDepthFence']['decision'], 'hold-next-current-recursive-depths'],
    'plan decision generation' => [static fn (): mixed => $generationHeld202()['current_source_next_plan_generationDepthFence']['decision'], 'hold-next-current-view-generation'],
    'plan decision base' => [static fn (): mixed => $baseHeld202()['current_source_next_plan_generationDepthFence']['decision'], 'hold-next-until-following-child-drain'],
    'yield boundary released' => [static fn (): mixed => $released202()['yield_boundary_generationDepthFence'], 'recursive-view-returning-generation-depth-fence-current-generation-depths-then-next'],
    'yield boundary held' => [static fn (): mixed => $depthHeld202()['yield_boundary_generationDepthFence'], 'recursive-view-returning-generation-depth-fence-current-generation-depths-fence-next'],
    'dependency closure marker' => [static fn (): mixed => $released202()['dependency_closure_generationDepthFence'], 'no new support component needed; reuses following child drain and adds current view generation/depth acknowledgement fencing'],
    'dependencies include generation-depth-fence' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-generation-depth-fence', $released202()['dependencies_generationDepthFence'], true), true],
    'dependencies include generation fence' => [static fn (): mixed => in_array('sqlite-returning-current-view-generation-depth-fence', $released202()['dependencies_generationDepthFence'], true), true],
    'dependencies include following child drain' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-following-child-drain', $released202()['dependencies_generationDepthFence'], true), true],
    'non overlap names following child drain' => [static fn (): mixed => str_contains($released202()['non_overlap_generationDepthFence'], 'next196 child-ordinal drains'), true],
    'bad current generation rejected' => [static fn (): mixed => $plan202(['current_view_generation_generationDepthFence' => 'bad token']), InvalidArgumentException::class],
    'bad expected generation rejected' => [static fn (): mixed => $plan202(['expected_current_view_generation_generationDepthFence' => 'bad token']), InvalidArgumentException::class],
    'bad next generation rejected' => [static fn (): mixed => $plan202(['next_view_generation_generationDepthFence' => 'bad token']), InvalidArgumentException::class],
    'bad barrier rejected' => [static fn (): mixed => $plan202(['returning_resume_barrier_generationDepthFence' => 'bad token']), InvalidArgumentException::class],
    'bad required depths list rejected' => [static fn (): mixed => $plan202(['required_current_depths_generationDepthFence' => 'bad-list']), InvalidArgumentException::class],
    'bad required depth rejected' => [static fn (): mixed => $plan202(['required_current_depths_generationDepthFence' => [-1]]), InvalidArgumentException::class],
    'bad ack depths list rejected' => [static fn (): mixed => $plan202(['acknowledged_current_depths_generationDepthFence' => 'bad-list']), InvalidArgumentException::class],
    'bad ack depth rejected' => [static fn (): mixed => $plan202(['acknowledged_current_depths_generationDepthFence' => [-1]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases202 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source generation-depth-fence ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

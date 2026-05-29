<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows200 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView200 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-200-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-200-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-200',
];
$nextView200 = $currentView200;
$nextView200['source'] = 'main@view-cookie-200-next';
$nextView200['trigger_source'] = 'main@trigger-cookie-200-next';
$nextView200['audit_label'] = 'next-recursive-view-trigger-200';
$currentInput200 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput200 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning200 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$run200 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentHighwaterGate(
    $rows200,
    $currentInput200,
    $nextInput200,
    $currentView200,
    $nextView200,
    $returning200,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_200',
        'cursor_name' => 'wp_recursive_view_returning_cursor_200',
        'current_generation' => 'wp-current-returning-200',
        'next_generation' => 'wp-next-returning-200',
        'checkpoint_name' => 'wp_recursive_view_checkpoint_200',
        'page_size' => 3,
    ],
);

$exposed200 = static fn (): array => $run200();
$shortDrain200 = static fn (): array => $run200(['expected_current_drain_count_next200' => 5]);
$staleHighWater200 = static fn (): array => $run200(['expected_current_highwater_token_next200' => 'wp_recursive_view_returning_cursor_200:wp-current-returning-200:4']);
$staleGeneration200 = static fn (): array => $run200(['expected_current_generation_epoch_next200' => 'epoch200:stale']);
$doneHeld200 = static fn (): array => $run200(['current_result_code' => 'SQLITE_ROW']);
$nonRecursive200 = static fn (): array => $run200(['recursive_triggers' => false]);

$cases200 = [
    'exposed status' => [static fn (): mixed => $exposed200()['status_next200'], 'trigger-recursive-view-returning-current-source-next200-next-exposed'],
    'short drain status' => [static fn (): mixed => $shortDrain200()['status_next200'], 'trigger-recursive-view-returning-current-source-next200-drain-count-held'],
    'stale highwater status' => [static fn (): mixed => $staleHighWater200()['status_next200'], 'trigger-recursive-view-returning-current-source-next200-highwater-held'],
    'stale generation status' => [static fn (): mixed => $staleGeneration200()['status_next200'], 'trigger-recursive-view-returning-current-source-next200-generation-held'],
    'done gate held status' => [static fn (): mixed => $doneHeld200()['status_next200'], 'trigger-recursive-view-returning-current-source-next200-done-gate-held'],
    'base next194 retained' => [static fn (): mixed => $exposed200()['base']['status'], 'trigger-recursive-view-returning-current-source-next194-next-exposed'],
    'base done held retained' => [static fn (): mixed => $doneHeld200()['base']['status'], 'trigger-recursive-view-returning-current-source-next194-current-not-done'],
    'current drain count' => [static fn (): mixed => $exposed200()['current_drain_count_next200'], 6],
    'expected current drain count' => [static fn (): mixed => $exposed200()['expected_current_drain_count_next200'], 6],
    'drain count matches exposed' => [static fn (): mixed => $exposed200()['current_drain_count_matches_next200'], true],
    'drain count mismatch' => [static fn (): mixed => $shortDrain200()['current_drain_count_matches_next200'], false],
    'highwater token exposed' => [static fn (): mixed => $exposed200()['current_highwater_token_next200'], 'wp_recursive_view_returning_cursor_200:wp-current-returning-200:5'],
    'expected highwater token exposed' => [static fn (): mixed => $exposed200()['expected_current_highwater_token_next200'], 'wp_recursive_view_returning_cursor_200:wp-current-returning-200:5'],
    'highwater matches exposed' => [static fn (): mixed => $exposed200()['current_highwater_token_matches_next200'], true],
    'highwater mismatch' => [static fn (): mixed => $staleHighWater200()['current_highwater_token_matches_next200'], false],
    'generation matches exposed' => [static fn (): mixed => $exposed200()['current_generation_epoch_matches_next200'], true],
    'generation mismatch' => [static fn (): mixed => $staleGeneration200()['current_generation_epoch_matches_next200'], false],
    'expected generation equals actual' => [static fn (): mixed => $exposed200()['expected_current_generation_epoch_next200'], $exposed200()['current_generation_epoch_next200']],
    'base exposed before highwater' => [static fn (): mixed => $exposed200()['base_next_exposed_before_highwater_next200'], true],
    'base held before highwater when row pending' => [static fn (): mixed => $doneHeld200()['base_next_exposed_before_highwater_next200'], false],
    'next exposed after highwater' => [static fn (): mixed => $exposed200()['next_source_exposed_after_current_highwater_next200'], true],
    'next held short drain' => [static fn (): mixed => $shortDrain200()['next_source_exposed_after_current_highwater_next200'], false],
    'next held stale highwater' => [static fn (): mixed => $staleHighWater200()['next_source_exposed_after_current_highwater_next200'], false],
    'next held stale generation' => [static fn (): mixed => $staleGeneration200()['next_source_exposed_after_current_highwater_next200'], false],
    'current rows count' => [static fn (): mixed => count($exposed200()['current_source_rows_next200']), 6],
    'attempted next rows count' => [static fn (): mixed => count($exposed200()['attempted_next_source_rows_next200']), 4],
    'visible exposed rows' => [static fn (): mixed => count($exposed200()['visible_rows_next200']), 10],
    'held exposed empty' => [static fn (): mixed => $exposed200()['held_rows_next200'], []],
    'visible short drain current only' => [static fn (): mixed => count($shortDrain200()['visible_rows_next200']), 6],
    'visible stale highwater current only' => [static fn (): mixed => count($staleHighWater200()['visible_rows_next200']), 6],
    'visible stale generation current only' => [static fn (): mixed => count($staleGeneration200()['visible_rows_next200']), 6],
    'held short drain count' => [static fn (): mixed => count($shortDrain200()['held_rows_next200']), 4],
    'held highwater count' => [static fn (): mixed => count($staleHighWater200()['held_rows_next200']), 4],
    'held generation count' => [static fn (): mixed => count($staleGeneration200()['held_rows_next200']), 4],
    'visible names exposed' => [static fn (): mixed => array_column($exposed200()['visible_returning_rows_next200'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child', 'home', 'next_plugin', 'home:child', 'home:child:child']],
    'visible names held' => [static fn (): mixed => array_column($shortDrain200()['visible_returning_rows_next200'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child']],
    'held names short drain' => [static fn (): mixed => array_column($shortDrain200()['held_returning_rows_next200'], 'name'), ['home', 'next_plugin', 'home:child', 'home:child:child']],
    'current visible unique' => [static fn (): mixed => array_values(array_unique(array_column($exposed200()['current_source_rows_next200'], 'visible_after_current_highwater_next200'))), [true]],
    'next visible exposed unique' => [static fn (): mixed => array_values(array_unique(array_column($exposed200()['attempted_next_source_rows_next200'], 'visible_after_current_highwater_next200'))), [true]],
    'next visible held unique' => [static fn (): mixed => array_values(array_unique(array_column($shortDrain200()['attempted_next_source_rows_next200'], 'visible_after_current_highwater_next200'))), [false]],
    'short drain block reason' => [static fn (): mixed => $shortDrain200()['block_reasons_next200'], ['current-source-drain-count-mismatch']],
    'stale highwater block reason' => [static fn (): mixed => $staleHighWater200()['block_reasons_next200'], ['current-source-highwater-token-mismatch']],
    'stale generation block reason' => [static fn (): mixed => $staleGeneration200()['block_reasons_next200'], ['current-source-generation-epoch-mismatch']],
    'done held block reason' => [static fn (): mixed => $doneHeld200()['block_reasons_next200'], ['current-source-not-done']],
    'exposed block reasons empty' => [static fn (): mixed => $exposed200()['block_reasons_next200'], []],
    'held short drain reason tagged' => [static fn (): mixed => $shortDrain200()['attempted_next_source_rows_next200'][0]['held_by_current_highwater_reasons_next200'], ['current-source-drain-count-mismatch']],
    'held highwater reason tagged' => [static fn (): mixed => $staleHighWater200()['attempted_next_source_rows_next200'][0]['held_by_current_highwater_reasons_next200'], ['current-source-highwater-token-mismatch']],
    'held generation reason tagged' => [static fn (): mixed => $staleGeneration200()['attempted_next_source_rows_next200'][0]['held_by_current_highwater_reasons_next200'], ['current-source-generation-epoch-mismatch']],
    'exposed next reason tagged empty' => [static fn (): mixed => $exposed200()['attempted_next_source_rows_next200'][0]['held_by_current_highwater_reasons_next200'], []],
    'plan current rows' => [static fn (): mixed => $exposed200()['current_highwater_plan_next200']['current_rows'], 6],
    'plan next rows' => [static fn (): mixed => $exposed200()['current_highwater_plan_next200']['attempted_next_rows'], 4],
    'plan visible rows' => [static fn (): mixed => $exposed200()['current_highwater_plan_next200']['visible_rows'], 10],
    'plan held rows exposed' => [static fn (): mixed => $exposed200()['current_highwater_plan_next200']['held_next_rows'], 0],
    'plan held rows short drain' => [static fn (): mixed => $shortDrain200()['current_highwater_plan_next200']['held_next_rows'], 4],
    'plan highwater token' => [static fn (): mixed => $exposed200()['current_highwater_plan_next200']['current_highwater_token'], 'wp_recursive_view_returning_cursor_200:wp-current-returning-200:5'],
    'plan decision exposed' => [static fn (): mixed => $exposed200()['current_highwater_plan_next200']['decision'], 'admit-next-source-after-current-highwater'],
    'plan decision held' => [static fn (): mixed => $shortDrain200()['current_highwater_plan_next200']['decision'], 'hold-next-source-until-current-highwater'],
    'plan blocked token exposed' => [static fn (): mixed => $exposed200()['current_highwater_plan_next200']['blocked_at_resume_token'], null],
    'plan blocked token held' => [static fn (): mixed => $shortDrain200()['current_highwater_plan_next200']['blocked_at_resume_token'], 'wp_recursive_view_returning_cursor_200:wp-next-returning-200:6'],
    'counts current rows' => [static fn (): mixed => $exposed200()['counts_next200']['current_rows'], 6],
    'counts next rows' => [static fn (): mixed => $exposed200()['counts_next200']['attempted_next_rows'], 4],
    'counts visible exposed' => [static fn (): mixed => $exposed200()['counts_next200']['visible_rows'], 10],
    'counts held short drain' => [static fn (): mixed => $shortDrain200()['counts_next200']['held_rows'], 4],
    'counts block reasons short drain' => [static fn (): mixed => $shortDrain200()['counts_next200']['block_reasons'], 1],
    'yield boundary exposed' => [static fn (): mixed => $exposed200()['yield_boundary_next200'], 'recursive-view-returning-current-source-next200-current-highwater-next-exposed'],
    'yield boundary held' => [static fn (): mixed => $shortDrain200()['yield_boundary_next200'], 'recursive-view-returning-current-source-next200-current-highwater-held'],
    'dependency next200' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next200', $exposed200()['dependencies_next200'], true), true],
    'dependency highwater' => [static fn (): mixed => in_array('sqlite-returning-current-source-highwater-gate', $exposed200()['dependencies_next200'], true), true],
    'dependency next194 retained' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next194', $exposed200()['dependencies_next200'], true), true],
    'dependency closure note' => [static fn (): mixed => $exposed200()['dependency_closure_next200'], 'no new support component needed; reuses recursive view trigger RETURNING resume rows and adds current-source drain high-water gating'],
    'non overlap mentions next194' => [static fn (): mixed => str_contains($exposed200()['non_overlap_next200'], 'next194'), true],
    'non recursive drain count' => [static fn (): mixed => $nonRecursive200()['current_drain_count_next200'], 2],
    'non recursive highwater token' => [static fn (): mixed => $nonRecursive200()['current_highwater_token_next200'], 'wp_recursive_view_returning_cursor_200:wp-current-returning-200:1'],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursive200()['visible_returning_rows_next200'], 'name'), ['siteurl', 'current_plugin', 'home', 'next_plugin']],
    'explicit expected drain accepted' => [static fn (): mixed => $run200(['expected_current_drain_count_next200' => 6])['current_drain_count_matches_next200'], true],
    'explicit expected highwater accepted' => [static fn (): mixed => $run200(['expected_current_highwater_token_next200' => $exposed200()['current_highwater_token_next200']])['current_highwater_token_matches_next200'], true],
    'explicit expected generation accepted' => [static fn (): mixed => $run200(['expected_current_generation_epoch_next200' => $exposed200()['current_generation_epoch_next200']])['current_generation_epoch_matches_next200'], true],
    'bad drain rejected' => [static fn (): mixed => $run200(['expected_current_drain_count_next200' => -1]), InvalidArgumentException::class],
    'bad highwater rejected' => [static fn (): mixed => $run200(['expected_current_highwater_token_next200' => 'bad token']), InvalidArgumentException::class],
    'bad generation rejected' => [static fn (): mixed => $run200(['expected_current_generation_epoch_next200' => 'bad token']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases200 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next200 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

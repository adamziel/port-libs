<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows183 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView183 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-183-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-183-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-183',
];
$nextView183 = $currentView183;
$nextView183['source'] = 'main@view-cookie-183-next';
$nextView183['trigger_source'] = 'main@trigger-cookie-183-next';
$nextView183['columns'] = ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child', 'import_source'];
$nextView183['mapping']['import_source'] = 'source';
$nextView183['audit_label'] = 'next-recursive-view-trigger-183';
$currentInput183 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput183 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true, 'import_source' => 'next-cookie'],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false, 'import_source' => 'next-cookie'],
];
$returning183 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$run183 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceResetGenerationFence(
    $rows183,
    $currentInput183,
    $nextInput183,
    $currentView183,
    $nextView183,
    $returning183,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_183',
        'cursor_name' => 'app_recursive_view_returning_cursor_183',
        'current_generation' => 'app-current-returning-183',
        'next_generation' => 'app-next-returning-183',
        'page_size' => 3,
        'current_source_token' => 'app.current.source.183',
        'drain_ack_token' => 'app.returning.drain.183',
    ],
);

$rolled183 = static fn (): array => $run183(['admit_next_source' => true]);
$committed183 = static fn (): array => $run183(['admit_next_source' => true, 'rollback_current_source' => false, 'commit_current_source' => true]);
$tokenHeld183 = static fn (): array => $run183(['admit_next_source' => true, 'expected_rollback_token' => 'app.rollback.expected.183']);
$currentHeld183 = static fn (): array => $run183(['rollback_current_source' => false]);
$baseHeld183 = static fn (): array => $run183(['rollback_current_source' => false, 'commit_current_source' => true]);

$cases183 = [
    'rolled status' => [static fn (): mixed => $rolled183()['status_next183'], 'trigger-recursive-view-returning-current-source-next183-rolled-back'],
    'committed status' => [static fn (): mixed => $committed183()['status_next183'], 'trigger-recursive-view-returning-current-source-next183-committed-next-visible'],
    'token held status' => [static fn (): mixed => $tokenHeld183()['status_next183'], 'trigger-recursive-view-returning-current-source-next183-rollback-token-held'],
    'current held status' => [static fn (): mixed => $currentHeld183()['status_next183'], 'trigger-recursive-view-returning-current-source-next183-current-held'],
    'base held status' => [static fn (): mixed => $baseHeld183()['status_next183'], 'trigger-recursive-view-returning-current-source-next183-next-held'],
    'savepoint retained' => [static fn (): mixed => $rolled183()['savepoint'], 'app_recursive_view_183'],
    'cursor retained' => [static fn (): mixed => $rolled183()['cursor'], 'app_recursive_view_returning_cursor_183'],
    'rollback token retained' => [static fn (): mixed => $rolled183()['rollback_token_next183'], 'app.rollback.current.183'],
    'rollback token matches default' => [static fn (): mixed => $rolled183()['rollback_token_matches_next183'], true],
    'rollback token mismatch recorded' => [static fn (): mixed => $tokenHeld183()['rollback_token_matches_next183'], false],
    'rollback requested default' => [static fn (): mixed => $rolled183()['rollback_requested_next183'], true],
    'commit flag default false' => [static fn (): mixed => $rolled183()['commit_current_source_next183'], false],
    'commit flag true recorded' => [static fn (): mixed => $committed183()['commit_current_source_next183'], true],
    'rollback applied default' => [static fn (): mixed => $rolled183()['current_source_rollback_applied_next183'], true],
    'rollback not applied on commit' => [static fn (): mixed => $committed183()['current_source_rollback_applied_next183'], false],
    'reset generation retained' => [static fn (): mixed => $rolled183()['reset_generation_next183'], 'app-current-reset-183'],
    'next admitted before reset' => [static fn (): mixed => $rolled183()['next_source_admitted_before_reset_next183'], true],
    'next hidden after rollback reset' => [static fn (): mixed => $rolled183()['next_source_visible_after_reset_next183'], false],
    'next visible after commit' => [static fn (): mixed => $committed183()['next_source_visible_after_reset_next183'], true],
    'current rows after reset count' => [static fn (): mixed => count($rolled183()['current_rows_after_reset_next183']), 6],
    'attempted next after reset count' => [static fn (): mixed => count($rolled183()['attempted_next_rows_after_reset_next183']), 4],
    'rolled visible rows empty' => [static fn (): mixed => $rolled183()['visible_rows_after_reset_next183'], []],
    'rolled invalidated current count' => [static fn (): mixed => count($rolled183()['invalidated_current_rows_next183']), 6],
    'rolled blocked next count' => [static fn (): mixed => count($rolled183()['blocked_next_rows_next183']), 4],
    'committed visible count' => [static fn (): mixed => count($committed183()['visible_rows_after_reset_next183']), 10],
    'committed invalidated empty' => [static fn (): mixed => $committed183()['invalidated_current_rows_next183'], []],
    'committed blocked next empty' => [static fn (): mixed => $committed183()['blocked_next_rows_next183'], []],
    'token held visible current only' => [static fn (): mixed => array_column($tokenHeld183()['visible_returning_rows_next183'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'token held blocks next' => [static fn (): mixed => count($tokenHeld183()['blocked_next_rows_next183']), 4],
    'current held visible current only' => [static fn (): mixed => array_column($currentHeld183()['visible_returning_rows_next183'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'base held visible current only' => [static fn (): mixed => array_column($baseHeld183()['visible_returning_rows_next183'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'rolled invalidated names' => [static fn (): mixed => array_column($rolled183()['invalidated_returning_rows_next183'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'rolled blocked next names' => [static fn (): mixed => array_column($rolled183()['blocked_next_returning_rows_next183'], 'name'), ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'committed visible names' => [static fn (): mixed => array_column($committed183()['visible_returning_rows_next183'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child', 'landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'rolled current rows invisible unique' => [static fn (): mixed => array_values(array_unique(array_column($rolled183()['current_rows_after_reset_next183'], 'visible_after_current_source_reset_next183'))), [false]],
    'rolled next rows invisible unique' => [static fn (): mixed => array_values(array_unique(array_column($rolled183()['attempted_next_rows_after_reset_next183'], 'visible_after_current_source_reset_next183'))), [false]],
    'committed current rows visible unique' => [static fn (): mixed => array_values(array_unique(array_column($committed183()['current_rows_after_reset_next183'], 'visible_after_current_source_reset_next183'))), [true]],
    'committed next rows visible unique' => [static fn (): mixed => array_values(array_unique(array_column($committed183()['attempted_next_rows_after_reset_next183'], 'visible_after_current_source_reset_next183'))), [true]],
    'rolled current block reason' => [static fn (): mixed => $rolled183()['current_rows_after_reset_next183'][0]['reset_block_reasons_next183'], ['current-source-rollback-token-applied']],
    'rolled next block reason' => [static fn (): mixed => $rolled183()['attempted_next_rows_after_reset_next183'][0]['reset_block_reasons_next183'], ['current-source-rolled-back-before-next-source']],
    'token held next block reason' => [static fn (): mixed => $tokenHeld183()['attempted_next_rows_after_reset_next183'][0]['reset_block_reasons_next183'], ['rollback-token-mismatch']],
    'base held next block reason' => [static fn (): mixed => $baseHeld183()['attempted_next_rows_after_reset_next183'][0]['reset_block_reasons_next183'], ['changed-next-source-awaits-reprepare']],
    'committed current block reason empty' => [static fn (): mixed => $committed183()['current_rows_after_reset_next183'][0]['reset_block_reasons_next183'], []],
    'committed next block reason empty' => [static fn (): mixed => $committed183()['attempted_next_rows_after_reset_next183'][0]['reset_block_reasons_next183'], []],
    'reset barrier current before count' => [static fn (): mixed => $rolled183()['reset_barrier_next183']['current_rows_before_reset'], 6],
    'reset barrier next before count' => [static fn (): mixed => $rolled183()['reset_barrier_next183']['attempted_next_rows_before_reset'], 4],
    'reset barrier visible after rollback' => [static fn (): mixed => $rolled183()['reset_barrier_next183']['visible_rows_after_reset'], 0],
    'reset barrier invalidated current' => [static fn (): mixed => $rolled183()['reset_barrier_next183']['invalidated_current_rows'], 6],
    'reset barrier blocked next' => [static fn (): mixed => $rolled183()['reset_barrier_next183']['blocked_next_rows'], 4],
    'reset barrier token matches' => [static fn (): mixed => $rolled183()['reset_barrier_next183']['rollback_token_matches'], true],
    'reset barrier generation' => [static fn (): mixed => $rolled183()['reset_barrier_next183']['current_source_reset_generation'], 'app-current-reset-183'],
    'reset barrier yielded invalidated' => [static fn (): mixed => $rolled183()['reset_barrier_next183']['yielded_returning_invalidated_by_rollback'], true],
    'reset barrier next requires commit' => [static fn (): mixed => $rolled183()['reset_barrier_next183']['next_source_requires_current_source_commit'], true],
    'yield boundary rolled' => [static fn (): mixed => $rolled183()['yield_boundary_next183'], 'recursive-view-returning-next183-yield-then-current-source-rollback'],
    'yield boundary committed' => [static fn (): mixed => $committed183()['yield_boundary_next183'], 'recursive-view-returning-next183-current-source-committed-next-visible'],
    'yield boundary held' => [static fn (): mixed => $currentHeld183()['yield_boundary_next183'], 'recursive-view-returning-next183-current-source-held'],
    'dependency closure' => [static fn (): mixed => $rolled183()['dependency_closure_next183'], 'no new support component needed; reuses recursive view trigger RETURNING current-source snapshots and adds reset-barrier visibility modeling'],
    'dependencies include next183' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next183', $rolled183()['dependencies_next183'], true), true],
    'dependencies include reset invalidation' => [static fn (): mixed => in_array('sqlite-returning-current-source-reset-invalidates-yielded-rows', $rolled183()['dependencies_next183'], true), true],
    'dependencies include next180 base' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next180', $rolled183()['dependencies_next183'], true), true],
    'non overlap mentions next180' => [static fn (): mixed => str_contains($rolled183()['non_overlap_next183'], 'next180'), true],
    'custom reset generation accepted' => [static fn (): mixed => $run183(['reset_generation' => 'app-reset-custom-183'])['reset_generation_next183'], 'app-reset-custom-183'],
    'bad rollback token rejected' => [static fn (): mixed => $run183(['rollback_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected rollback token rejected' => [static fn (): mixed => $run183(['expected_rollback_token' => 'bad token']), InvalidArgumentException::class],
    'bad reset generation rejected' => [static fn (): mixed => $run183(['reset_generation' => 'bad generation']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases183 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next183 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

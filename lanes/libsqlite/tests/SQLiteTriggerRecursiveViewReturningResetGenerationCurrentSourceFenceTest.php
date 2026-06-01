<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows180 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentView180 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-180-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-180-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-180',
];
$nextView180 = $currentView180;
$nextView180['source'] = 'main@view-cookie-180-next';
$nextView180['trigger_source'] = 'main@trigger-cookie-180-next';
$nextView180['columns'] = ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child', 'import_source'];
$nextView180['mapping']['import_source'] = 'source';
$nextView180['audit_label'] = 'next-recursive-view-trigger-180';
$currentInput180 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput180 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true, 'import_source' => 'next-cookie'],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false, 'import_source' => 'next-cookie'],
];
$returning180 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan180 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeResetGenerationCurrentSourceFence(
    $rows180,
    $currentInput180,
    $nextInput180,
    $currentView180,
    $nextView180,
    $returning180,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_180',
        'cursor_name' => 'app_recursive_view_returning_cursor_180',
        'current_generation' => 'app-current-returning-180',
        'next_generation' => 'app-next-returning-180',
        'page_size' => 3,
    ],
);

$held180 = static fn (): array => $plan180();
$admitted180 = static fn (): array => $plan180(['admit_next_source' => true]);
$drainHeld180 = static fn (): array => $plan180(['admit_next_source' => true, 'expected_drain_ack_token' => 'app.returning.drain.180.expected']);
$sourceHeld180 = static fn (): array => $plan180(['admit_next_source' => true, 'expected_current_source_token' => 'app.current.source.180.expected']);
$reprepareHeld180 = static fn (): array => $plan180(['admit_next_source' => true, 'expected_reprepare_token' => 'app.reprepare.180.expected']);
$noChangeView180 = $currentView180;
$sameSource180 = static fn (): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeResetGenerationCurrentSourceFence(
    $rows180,
    $currentInput180,
    $nextInput180,
    $currentView180,
    $noChangeView180,
    $returning180,
    [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_180',
        'admit_next_source' => true,
        'cursor_name' => 'app_recursive_view_returning_cursor_180',
    ],
);

$cases180 = [
    'held status' => [static fn (): mixed => $held180()['status_next180'], 'trigger-recursive-view-returning-current-source-next180-current-source-held'],
    'admitted status' => [static fn (): mixed => $admitted180()['status_next180'], 'trigger-recursive-view-returning-current-source-next180-next-admitted'],
    'drain ack status' => [static fn (): mixed => $drainHeld180()['status_next180'], 'trigger-recursive-view-returning-current-source-next180-drain-ack-held'],
    'source token status' => [static fn (): mixed => $sourceHeld180()['status_next180'], 'trigger-recursive-view-returning-current-source-next180-current-source-token-held'],
    'reprepare status' => [static fn (): mixed => $reprepareHeld180()['status_next180'], 'trigger-recursive-view-returning-current-source-next180-reprepare-held'],
    'savepoint retained' => [static fn (): mixed => $held180()['savepoint'], 'app_recursive_view_180'],
    'cursor retained' => [static fn (): mixed => $held180()['cursor'], 'app_recursive_view_returning_cursor_180'],
    'current token retained' => [static fn (): mixed => $held180()['current_source_token_next180'], 'app.current.source.180'],
    'drain ack retained' => [static fn (): mixed => $held180()['drain_ack_token_next180'], 'app.returning.drain.180'],
    'current token matches default' => [static fn (): mixed => $held180()['current_source_token_matches_next180'], true],
    'drain ack matches default' => [static fn (): mixed => $held180()['drain_ack_token_matches_next180'], true],
    'source token mismatch recorded' => [static fn (): mixed => $sourceHeld180()['current_source_token_matches_next180'], false],
    'drain ack mismatch recorded' => [static fn (): mixed => $drainHeld180()['drain_ack_token_matches_next180'], false],
    'source changed recorded' => [static fn (): mixed => $held180()['source_changed_next180'], true],
    'same view source not changed' => [static fn (): mixed => $sameSource180()['source_changed_next180'], false],
    'next not admitted while base held' => [static fn (): mixed => $held180()['next_source_admitted_next180'], false],
    'next admitted with base admission' => [static fn (): mixed => $admitted180()['next_source_admitted_next180'], true],
    'current frame phase' => [static fn (): mixed => $held180()['current_source_frame_next180']['phase'], 'current'],
    'next frame phase' => [static fn (): mixed => $held180()['next_source_frame_next180']['phase'], 'next'],
    'current frame source' => [static fn (): mixed => $held180()['current_source_frame_next180']['source'], 'main@view-cookie-180-current'],
    'next frame source' => [static fn (): mixed => $held180()['next_source_frame_next180']['source'], 'main@view-cookie-180-next'],
    'current frame columns' => [static fn (): mixed => $held180()['current_source_frame_next180']['columns'], ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child']],
    'next frame extra column retained' => [static fn (): mixed => $held180()['next_source_frame_next180']['columns'], ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child', 'import_source']],
    'current mapping retained' => [static fn (): mixed => $held180()['current_source_frame_next180']['mapping']['name'], 'key_name'],
    'next mapping retained' => [static fn (): mixed => $held180()['next_source_frame_next180']['mapping']['import_source'], 'source'],
    'returning aliases retained' => [static fn (): mixed => $held180()['current_source_frame_next180']['returning_aliases'], ['name', 'old_value', 'event_name', 'depth_value', 'trigger_source_alias']],
    'frame signatures differ' => [static fn (): mixed => $held180()['current_source_frame_next180']['source_signature'] !== $held180()['next_source_frame_next180']['source_signature'], true],
    'same view signatures match' => [static fn (): mixed => $sameSource180()['current_source_frame_next180']['source_signature'] === $sameSource180()['next_source_frame_next180']['source_signature'], true],
    'current rows count' => [static fn (): mixed => count($held180()['current_source_rows_next180']), 6],
    'next rows count' => [static fn (): mixed => count($held180()['attempted_next_source_rows_next180']), 4],
    'held visible count current only' => [static fn (): mixed => count($held180()['visible_rows_next180']), 6],
    'held next count' => [static fn (): mixed => count($held180()['held_rows_next180']), 4],
    'admitted visible count' => [static fn (): mixed => count($admitted180()['visible_rows_next180']), 10],
    'admitted held empty' => [static fn (): mixed => $admitted180()['held_rows_next180'], []],
    'visible returning names held' => [static fn (): mixed => array_column($held180()['visible_returning_rows_next180'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'held returning names' => [static fn (): mixed => array_column($held180()['held_returning_rows_next180'], 'name'), ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'admitted visible returning names' => [static fn (): mixed => array_column($admitted180()['visible_returning_rows_next180'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child', 'landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'current rows visible after snapshot' => [static fn (): mixed => array_values(array_unique(array_column($held180()['current_source_rows_next180'], 'visible_after_source_snapshot'))), [true]],
    'held next rows invisible after snapshot' => [static fn (): mixed => array_values(array_unique(array_column($held180()['attempted_next_source_rows_next180'], 'visible_after_source_snapshot'))), [false]],
    'admitted next rows visible after snapshot' => [static fn (): mixed => array_values(array_unique(array_column($admitted180()['attempted_next_source_rows_next180'], 'visible_after_source_snapshot'))), [true]],
    'current row signature pinned' => [static fn (): mixed => array_values(array_unique(array_column($held180()['current_source_rows_next180'], 'source_signature_next180'))), [$held180()['current_source_frame_next180']['source_signature']]],
    'held next row signature pinned to next' => [static fn (): mixed => array_values(array_unique(array_column($held180()['attempted_next_source_rows_next180'], 'source_signature_next180'))), [$held180()['next_source_frame_next180']['source_signature']]],
    'current row frame phase' => [static fn (): mixed => array_values(array_unique(array_column($held180()['current_source_rows_next180'], 'source_frame_phase_next180'))), ['current']],
    'next row frame phase' => [static fn (): mixed => array_values(array_unique(array_column($held180()['attempted_next_source_rows_next180'], 'source_frame_phase_next180'))), ['next']],
    'held block reason changed source' => [static fn (): mixed => $held180()['block_reasons_next180'], ['changed-next-source-awaits-reprepare']],
    'drain block reason' => [static fn (): mixed => $drainHeld180()['block_reasons_next180'], ['current-returning-drain-ack-mismatch']],
    'source block reason' => [static fn (): mixed => $sourceHeld180()['block_reasons_next180'], ['current-source-token-mismatch']],
    'reprepare block reasons include token' => [static fn (): mixed => in_array('reprepare-token-mismatch', $reprepareHeld180()['block_reasons_next180'], true), true],
    'admitted block reasons empty' => [static fn (): mixed => $admitted180()['block_reasons_next180'], []],
    'held row block reasons copied' => [static fn (): mixed => $held180()['held_rows_next180'][0]['held_by_source_snapshot_reasons'], ['changed-next-source-awaits-reprepare']],
    'admitted next row block reasons empty' => [static fn (): mixed => $admitted180()['attempted_next_source_rows_next180'][0]['held_by_source_snapshot_reasons'], []],
    'snapshot current signature retained' => [static fn (): mixed => $held180()['source_snapshot_next180']['current_signature'], $held180()['current_source_frame_next180']['source_signature']],
    'snapshot next signature retained' => [static fn (): mixed => $held180()['source_snapshot_next180']['next_signature'], $held180()['next_source_frame_next180']['source_signature']],
    'snapshot current rows visible count' => [static fn (): mixed => $held180()['source_snapshot_next180']['current_rows_visible'], 6],
    'snapshot attempted next rows count' => [static fn (): mixed => $held180()['source_snapshot_next180']['attempted_next_rows'], 4],
    'snapshot held rows count' => [static fn (): mixed => $held180()['source_snapshot_next180']['held_next_rows'], 4],
    'snapshot next visible held zero' => [static fn (): mixed => $held180()['source_snapshot_next180']['next_rows_visible'], 0],
    'snapshot next visible admitted count' => [static fn (): mixed => $admitted180()['source_snapshot_next180']['next_rows_visible'], 4],
    'snapshot current frozen' => [static fn (): mixed => $held180()['source_snapshot_next180']['current_source_frozen_until_reset'], true],
    'snapshot next requires reprepare' => [static fn (): mixed => $held180()['source_snapshot_next180']['next_source_requires_reprepare'], true],
    'yield boundary held' => [static fn (): mixed => $held180()['yield_boundary_next180'], 'recursive-view-returning-next180-current-source-snapshot-held'],
    'yield boundary admitted' => [static fn (): mixed => $admitted180()['yield_boundary_next180'], 'recursive-view-returning-next180-source-snapshot-next-admitted'],
    'dependency closure marker' => [static fn (): mixed => $held180()['dependency_closure_next180'], 'no new support component needed; reuses recursive view trigger RETURNING current-source cursor and source snapshot modeling'],
    'dependency includes next180' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next180', $held180()['dependencies_next180'], true), true],
    'dependency includes source snapshot' => [static fn (): mixed => in_array('sqlite-returning-current-source-snapshot-admission', $held180()['dependencies_next180'], true), true],
    'dependency includes next177 base' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next177', $held180()['dependencies_next180'], true), true],
    'non overlap mentions next177' => [static fn (): mixed => str_contains($held180()['non_overlap_next180'], 'next177'), true],
    'bad current source token rejected' => [static fn (): mixed => $plan180(['current_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected current source token rejected' => [static fn (): mixed => $plan180(['expected_current_source_token' => 'bad token']), InvalidArgumentException::class],
    'bad drain ack rejected' => [static fn (): mixed => $plan180(['drain_ack_token' => 'bad token']), InvalidArgumentException::class],
    'bad expected drain ack rejected' => [static fn (): mixed => $plan180(['expected_drain_ack_token' => 'bad token']), InvalidArgumentException::class],
    'bad next column rejected' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeResetGenerationCurrentSourceFence($rows180, $currentInput180, $nextInput180, $currentView180, array_replace($nextView180, ['columns' => ['bad column']]), $returning180), InvalidArgumentException::class],
    'bad next mapping rejected' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeResetGenerationCurrentSourceFence($rows180, $currentInput180, $nextInput180, $currentView180, array_replace($nextView180, ['mapping' => []]), $returning180), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases180 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next180 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

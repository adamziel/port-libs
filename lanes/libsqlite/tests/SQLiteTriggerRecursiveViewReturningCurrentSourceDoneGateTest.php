<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rowsdone_gate = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes'],
];
$currentViewdone_gate = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-done_gate-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-done_gate-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger-done_gate',
];
$nextViewdone_gate = $currentViewdone_gate;
$nextViewdone_gate['source'] = 'main@view-cookie-done_gate-next';
$nextViewdone_gate['trigger_source'] = 'main@trigger-cookie-done_gate-next';
$nextViewdone_gate['audit_label'] = 'next-recursive-view-trigger-done_gate';
$currentInputdone_gate = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInputdone_gate = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false],
];
$returningdone_gate = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$rundone_gate = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceDoneGate(
    $rowsdone_gate,
    $currentInputdone_gate,
    $nextInputdone_gate,
    $currentViewdone_gate,
    $nextViewdone_gate,
    $returningdone_gate,
    $options + [
        'key' => 'key_name',
        'savepoint' => 'app_recursive_view_done_gate',
        'cursor_name' => 'app_recursive_view_returning_cursor_done_gate',
        'current_generation' => 'app-current-returning-done_gate',
        'next_generation' => 'app-next-returning-done_gate',
        'checkpoint_name' => 'app_recursive_view_checkpoint_done_gate',
        'page_size' => 3,
    ],
);

$exposeddone_gate = static fn (): array => $rundone_gate();
$rowHelddone_gate = static fn (): array => $rundone_gate(['current_result_code' => 'SQLITE_ROW']);
$busyHelddone_gate = static fn (): array => $rundone_gate(['current_result_code' => 'SQLITE_BUSY']);
$cookieHelddone_gate = static fn (): array => $rundone_gate(['current_source_cookie' => 'cookiedone_gate:stale']);
$epochHelddone_gate = static fn (): array => $rundone_gate(['current_step_epoch' => 'epochdone_gate:stale']);
$resumeHelddone_gate = static fn (): array => $rundone_gate(['resume_source_token' => 'app.returning.current.source.resume.done_gate:stale']);
$nonRecursivedone_gate = static fn (): array => $rundone_gate(['recursive_triggers' => false]);

$casesdone_gate = [
    'exposed status' => [static fn (): mixed => $exposeddone_gate()['status'], 'trigger-recursive-view-returning-current-source-done-gate-next-exposed'],
    'row held status' => [static fn (): mixed => $rowHelddone_gate()['status'], 'trigger-recursive-view-returning-current-source-done-gate-current-not-done'],
    'busy held status' => [static fn (): mixed => $busyHelddone_gate()['status'], 'trigger-recursive-view-returning-current-source-done-gate-current-not-done'],
    'cookie held status' => [static fn (): mixed => $cookieHelddone_gate()['status'], 'trigger-recursive-view-returning-current-source-done-gate-source-cookie-held'],
    'epoch held status' => [static fn (): mixed => $epochHelddone_gate()['status'], 'trigger-recursive-view-returning-current-source-done-gate-step-epoch-held'],
    'resume held status' => [static fn (): mixed => $resumeHelddone_gate()['status'], 'trigger-recursive-view-returning-current-source-done-gate-resume-source-held'],
    'base next190 retained' => [static fn (): mixed => $exposeddone_gate()['base']['status'], 'trigger-recursive-view-returning-current-source-next190-next-exposed'],
    'base resume held retained' => [static fn (): mixed => $resumeHelddone_gate()['base']['status'], 'trigger-recursive-view-returning-current-source-next190-resume-token-held'],
    'result code exposed' => [static fn (): mixed => $exposeddone_gate()['current_result_code_done_gate'], 'SQLITE_DONE'],
    'expected result code exposed' => [static fn (): mixed => $exposeddone_gate()['expected_current_result_code_done_gate'], 'SQLITE_DONE'],
    'result code matches exposed' => [static fn (): mixed => $exposeddone_gate()['current_result_code_matches_done_gate'], true],
    'result code mismatch row' => [static fn (): mixed => $rowHelddone_gate()['current_result_code_matches_done_gate'], false],
    'result code mismatch busy' => [static fn (): mixed => $busyHelddone_gate()['current_result_code_matches_done_gate'], false],
    'cookie matches exposed' => [static fn (): mixed => $exposeddone_gate()['current_source_cookie_matches_done_gate'], true],
    'cookie mismatch recorded' => [static fn (): mixed => $cookieHelddone_gate()['current_source_cookie_matches_done_gate'], false],
    'epoch matches exposed' => [static fn (): mixed => $exposeddone_gate()['current_step_epoch_matches_done_gate'], true],
    'epoch mismatch recorded' => [static fn (): mixed => $epochHelddone_gate()['current_step_epoch_matches_done_gate'], false],
    'expected cookie equals actual' => [static fn (): mixed => $exposeddone_gate()['expected_current_source_cookie_done_gate'], $exposeddone_gate()['current_source_cookie_done_gate']],
    'expected epoch equals actual' => [static fn (): mixed => $exposeddone_gate()['expected_current_step_epoch_done_gate'], $exposeddone_gate()['current_step_epoch_done_gate']],
    'base next exposed before done' => [static fn (): mixed => $exposeddone_gate()['base_next_exposed_before_current_done_done_gate'], true],
    'base next held before done' => [static fn (): mixed => $resumeHelddone_gate()['base_next_exposed_before_current_done_done_gate'], false],
    'next exposed after done' => [static fn (): mixed => $exposeddone_gate()['next_source_exposed_after_current_done_done_gate'], true],
    'next held while row' => [static fn (): mixed => $rowHelddone_gate()['next_source_exposed_after_current_done_done_gate'], false],
    'next held stale cookie' => [static fn (): mixed => $cookieHelddone_gate()['next_source_exposed_after_current_done_done_gate'], false],
    'current row count' => [static fn (): mixed => count($exposeddone_gate()['current_source_rows']), 6],
    'attempted next row count' => [static fn (): mixed => count($exposeddone_gate()['attempted_next_source_rows']), 4],
    'visible exposed rows' => [static fn (): mixed => count($exposeddone_gate()['visible_rows']), 10],
    'held rows exposed empty' => [static fn (): mixed => $exposeddone_gate()['held_rows'], []],
    'visible row-held current only' => [static fn (): mixed => count($rowHelddone_gate()['visible_rows']), 6],
    'visible cookie-held current only' => [static fn (): mixed => count($cookieHelddone_gate()['visible_rows']), 6],
    'visible epoch-held current only' => [static fn (): mixed => count($epochHelddone_gate()['visible_rows']), 6],
    'held rows row result count' => [static fn (): mixed => count($rowHelddone_gate()['held_rows']), 4],
    'held rows busy result count' => [static fn (): mixed => count($busyHelddone_gate()['held_rows']), 4],
    'held rows cookie count' => [static fn (): mixed => count($cookieHelddone_gate()['held_rows']), 4],
    'held rows epoch count' => [static fn (): mixed => count($epochHelddone_gate()['held_rows']), 4],
    'visible names exposed' => [static fn (): mixed => array_column($exposeddone_gate()['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child', 'landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'visible names row held' => [static fn (): mixed => array_column($rowHelddone_gate()['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'held names row result' => [static fn (): mixed => array_column($rowHelddone_gate()['held_returning_rows'], 'name'), ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'held names cookie result' => [static fn (): mixed => array_column($cookieHelddone_gate()['held_returning_rows'], 'name'), ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'current visible unique' => [static fn (): mixed => array_values(array_unique(array_column($exposeddone_gate()['current_source_rows'], 'visible_after_current_done_done_gate'))), [true]],
    'next visible exposed unique' => [static fn (): mixed => array_values(array_unique(array_column($exposeddone_gate()['attempted_next_source_rows'], 'visible_after_current_done_done_gate'))), [true]],
    'next visible row held unique' => [static fn (): mixed => array_values(array_unique(array_column($rowHelddone_gate()['attempted_next_source_rows'], 'visible_after_current_done_done_gate'))), [false]],
    'row held block reasons' => [static fn (): mixed => $rowHelddone_gate()['block_reasons_done_gate'], ['current-source-not-done']],
    'busy held block reasons' => [static fn (): mixed => $busyHelddone_gate()['block_reasons_done_gate'], ['current-source-not-done']],
    'cookie held block reasons' => [static fn (): mixed => $cookieHelddone_gate()['block_reasons_done_gate'], ['current-source-cookie-mismatch']],
    'epoch held block reasons' => [static fn (): mixed => $epochHelddone_gate()['block_reasons_done_gate'], ['current-step-epoch-mismatch']],
    'resume held block reasons' => [static fn (): mixed => $resumeHelddone_gate()['block_reasons_done_gate'], ['current-source-resume-token-mismatch']],
    'exposed block reasons empty' => [static fn (): mixed => $exposeddone_gate()['block_reasons_done_gate'], []],
    'held row reason tagged' => [static fn (): mixed => $rowHelddone_gate()['attempted_next_source_rows'][0]['held_by_current_done_reasons_done_gate'], ['current-source-not-done']],
    'held cookie reason tagged' => [static fn (): mixed => $cookieHelddone_gate()['attempted_next_source_rows'][0]['held_by_current_done_reasons_done_gate'], ['current-source-cookie-mismatch']],
    'exposed next reason tagged empty' => [static fn (): mixed => $exposeddone_gate()['attempted_next_source_rows'][0]['held_by_current_done_reasons_done_gate'], []],
    'plan current rows' => [static fn (): mixed => $exposeddone_gate()['current_done_plan_done_gate']['current_rows'], 6],
    'plan next rows' => [static fn (): mixed => $exposeddone_gate()['current_done_plan_done_gate']['attempted_next_rows'], 4],
    'plan visible rows' => [static fn (): mixed => $exposeddone_gate()['current_done_plan_done_gate']['visible_rows'], 10],
    'plan held rows exposed' => [static fn (): mixed => $exposeddone_gate()['current_done_plan_done_gate']['held_next_rows'], 0],
    'plan held rows row' => [static fn (): mixed => $rowHelddone_gate()['current_done_plan_done_gate']['held_next_rows'], 4],
    'plan result code' => [static fn (): mixed => $rowHelddone_gate()['current_done_plan_done_gate']['current_result_code'], 'SQLITE_ROW'],
    'plan decision exposed' => [static fn (): mixed => $exposeddone_gate()['current_done_plan_done_gate']['decision'], 'admit-next-source-after-current-done'],
    'plan decision held' => [static fn (): mixed => $rowHelddone_gate()['current_done_plan_done_gate']['decision'], 'hold-next-source-until-current-done'],
    'plan blocked token exposed' => [static fn (): mixed => $exposeddone_gate()['current_done_plan_done_gate']['blocked_at_resume_token'], null],
    'plan blocked token row' => [static fn (): mixed => $rowHelddone_gate()['current_done_plan_done_gate']['blocked_at_resume_token'], 'app_recursive_view_returning_cursor_done_gate:app-next-returning-done_gate:6'],
    'counts current rows' => [static fn (): mixed => $exposeddone_gate()['counts_done_gate']['current_rows'], 6],
    'counts next rows' => [static fn (): mixed => $exposeddone_gate()['counts_done_gate']['attempted_next_rows'], 4],
    'counts visible exposed' => [static fn (): mixed => $exposeddone_gate()['counts_done_gate']['visible_rows'], 10],
    'counts held row' => [static fn (): mixed => $rowHelddone_gate()['counts_done_gate']['held_rows'], 4],
    'counts block reasons row' => [static fn (): mixed => $rowHelddone_gate()['counts_done_gate']['block_reasons'], 1],
    'yield boundary exposed' => [static fn (): mixed => $exposeddone_gate()['yield_boundary_done_gate'], 'recursive-view-returning-current-source-done-gate-current-done-next-exposed'],
    'yield boundary held' => [static fn (): mixed => $rowHelddone_gate()['yield_boundary_done_gate'], 'recursive-view-returning-current-source-done-gate-current-done-held'],
    'dependency done-gate' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-done-gate', $exposeddone_gate()['dependencies_done_gate'], true), true],
    'dependency done gate' => [static fn (): mixed => in_array('sqlite-returning-current-source-done-gate', $exposeddone_gate()['dependencies_done_gate'], true), true],
    'dependency next190 retained' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next190', $exposeddone_gate()['dependencies_done_gate'], true), true],
    'dependency closure note' => [static fn (): mixed => $exposeddone_gate()['dependency_closure_done_gate'], 'no new support component needed; reuses recursive view trigger RETURNING resume rows and adds current-source SQLITE_DONE/source-cookie gating'],
    'non overlap mentions next190' => [static fn (): mixed => str_contains($exposeddone_gate()['non_overlap_done_gate'], 'next190'), true],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursivedone_gate()['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'landing_url', 'next_module']],
    'non recursive current row count' => [static fn (): mixed => count($nonRecursivedone_gate()['current_source_rows']), 2],
    'non recursive next row count' => [static fn (): mixed => count($nonRecursivedone_gate()['attempted_next_source_rows']), 2],
    'explicit expected cookie accepted' => [static fn (): mixed => $rundone_gate(['expected_current_source_cookie' => $exposeddone_gate()['current_source_cookie_done_gate']])['current_source_cookie_matches_done_gate'], true],
    'explicit expected epoch accepted' => [static fn (): mixed => $rundone_gate(['expected_current_step_epoch' => $exposeddone_gate()['current_step_epoch_done_gate']])['current_step_epoch_matches_done_gate'], true],
    'explicit expected done accepted' => [static fn (): mixed => $rundone_gate(['expected_current_result_code' => 'SQLITE_DONE'])['current_result_code_matches_done_gate'], true],
    'expected row does not admit' => [static fn (): mixed => $rundone_gate(['expected_current_result_code' => 'SQLITE_ROW', 'current_result_code' => 'SQLITE_ROW'])['next_source_exposed_after_current_done_done_gate'], false],
    'bad result code rejected' => [static fn (): mixed => $rundone_gate(['current_result_code' => 'SQLITE_LOCKED']), InvalidArgumentException::class],
    'bad expected result code rejected' => [static fn (): mixed => $rundone_gate(['expected_current_result_code' => 'SQLITE_LOCKED']), InvalidArgumentException::class],
    'bad cookie rejected' => [static fn (): mixed => $rundone_gate(['current_source_cookie' => 'bad token']), InvalidArgumentException::class],
    'bad expected cookie rejected' => [static fn (): mixed => $rundone_gate(['expected_current_source_cookie' => 'bad token']), InvalidArgumentException::class],
    'bad epoch rejected' => [static fn (): mixed => $rundone_gate(['current_step_epoch' => 'bad token']), InvalidArgumentException::class],
    'bad expected epoch rejected' => [static fn (): mixed => $rundone_gate(['expected_current_step_epoch' => 'bad token']), InvalidArgumentException::class],
];

$tests = [];
foreach ($casesdone_gate as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source done-gate ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

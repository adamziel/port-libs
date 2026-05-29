<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows194 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
];
$currentView194 = [
    'name' => 'wp_recursive_option_import',
    'source' => 'main@view-cookie-194-current',
    'trigger' => 'wp_recursive_option_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-194-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'audit_label' => 'current-recursive-view-trigger-194',
];
$nextView194 = $currentView194;
$nextView194['source'] = 'main@view-cookie-194-next';
$nextView194['trigger_source'] = 'main@trigger-cookie-194-next';
$nextView194['audit_label'] = 'next-recursive-view-trigger-194';
$currentInput194 = [
    ['import_id' => 10, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_plugin', 'value' => 'enabled', 'autoload_flag' => 'no', 'spawn_child' => true],
];
$nextInput194 = [
    ['import_id' => 20, 'name' => 'home', 'value' => 'https://next.test', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'next_plugin', 'value' => 'active', 'autoload_flag' => 'no', 'spawn_child' => false],
];
$returning194 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$run194 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceDoneGate(
    $rows194,
    $currentInput194,
    $nextInput194,
    $currentView194,
    $nextView194,
    $returning194,
    $options + [
        'key' => 'option_name',
        'savepoint' => 'wp_recursive_view_194',
        'cursor_name' => 'wp_recursive_view_returning_cursor_194',
        'current_generation' => 'wp-current-returning-194',
        'next_generation' => 'wp-next-returning-194',
        'checkpoint_name' => 'wp_recursive_view_checkpoint_194',
        'page_size' => 3,
    ],
);

$exposed194 = static fn (): array => $run194();
$rowHeld194 = static fn (): array => $run194(['current_result_code' => 'SQLITE_ROW']);
$busyHeld194 = static fn (): array => $run194(['current_result_code' => 'SQLITE_BUSY']);
$cookieHeld194 = static fn (): array => $run194(['current_source_cookie' => 'cookie194:stale']);
$epochHeld194 = static fn (): array => $run194(['current_step_epoch' => 'epoch194:stale']);
$resumeHeld194 = static fn (): array => $run194(['resume_source_token' => 'wp.returning.current.source.resume.194:stale']);
$nonRecursive194 = static fn (): array => $run194(['recursive_triggers' => false]);

$cases194 = [
    'exposed status' => [static fn (): mixed => $exposed194()['status'], 'trigger-recursive-view-returning-current-source-next194-next-exposed'],
    'row held status' => [static fn (): mixed => $rowHeld194()['status'], 'trigger-recursive-view-returning-current-source-next194-current-not-done'],
    'busy held status' => [static fn (): mixed => $busyHeld194()['status'], 'trigger-recursive-view-returning-current-source-next194-current-not-done'],
    'cookie held status' => [static fn (): mixed => $cookieHeld194()['status'], 'trigger-recursive-view-returning-current-source-next194-source-cookie-held'],
    'epoch held status' => [static fn (): mixed => $epochHeld194()['status'], 'trigger-recursive-view-returning-current-source-next194-step-epoch-held'],
    'resume held status' => [static fn (): mixed => $resumeHeld194()['status'], 'trigger-recursive-view-returning-current-source-next194-resume-source-held'],
    'base next190 retained' => [static fn (): mixed => $exposed194()['base']['status'], 'trigger-recursive-view-returning-current-source-next190-next-exposed'],
    'base resume held retained' => [static fn (): mixed => $resumeHeld194()['base']['status'], 'trigger-recursive-view-returning-current-source-next190-resume-token-held'],
    'result code exposed' => [static fn (): mixed => $exposed194()['current_result_code_next194'], 'SQLITE_DONE'],
    'expected result code exposed' => [static fn (): mixed => $exposed194()['expected_current_result_code_next194'], 'SQLITE_DONE'],
    'result code matches exposed' => [static fn (): mixed => $exposed194()['current_result_code_matches_next194'], true],
    'result code mismatch row' => [static fn (): mixed => $rowHeld194()['current_result_code_matches_next194'], false],
    'result code mismatch busy' => [static fn (): mixed => $busyHeld194()['current_result_code_matches_next194'], false],
    'cookie matches exposed' => [static fn (): mixed => $exposed194()['current_source_cookie_matches_next194'], true],
    'cookie mismatch recorded' => [static fn (): mixed => $cookieHeld194()['current_source_cookie_matches_next194'], false],
    'epoch matches exposed' => [static fn (): mixed => $exposed194()['current_step_epoch_matches_next194'], true],
    'epoch mismatch recorded' => [static fn (): mixed => $epochHeld194()['current_step_epoch_matches_next194'], false],
    'expected cookie equals actual' => [static fn (): mixed => $exposed194()['expected_current_source_cookie_next194'], $exposed194()['current_source_cookie_next194']],
    'expected epoch equals actual' => [static fn (): mixed => $exposed194()['expected_current_step_epoch_next194'], $exposed194()['current_step_epoch_next194']],
    'base next exposed before done' => [static fn (): mixed => $exposed194()['base_next_exposed_before_current_done_next194'], true],
    'base next held before done' => [static fn (): mixed => $resumeHeld194()['base_next_exposed_before_current_done_next194'], false],
    'next exposed after done' => [static fn (): mixed => $exposed194()['next_source_exposed_after_current_done_next194'], true],
    'next held while row' => [static fn (): mixed => $rowHeld194()['next_source_exposed_after_current_done_next194'], false],
    'next held stale cookie' => [static fn (): mixed => $cookieHeld194()['next_source_exposed_after_current_done_next194'], false],
    'current row count' => [static fn (): mixed => count($exposed194()['current_source_rows']), 6],
    'attempted next row count' => [static fn (): mixed => count($exposed194()['attempted_next_source_rows']), 4],
    'visible exposed rows' => [static fn (): mixed => count($exposed194()['visible_rows']), 10],
    'held rows exposed empty' => [static fn (): mixed => $exposed194()['held_rows'], []],
    'visible row-held current only' => [static fn (): mixed => count($rowHeld194()['visible_rows']), 6],
    'visible cookie-held current only' => [static fn (): mixed => count($cookieHeld194()['visible_rows']), 6],
    'visible epoch-held current only' => [static fn (): mixed => count($epochHeld194()['visible_rows']), 6],
    'held rows row result count' => [static fn (): mixed => count($rowHeld194()['held_rows']), 4],
    'held rows busy result count' => [static fn (): mixed => count($busyHeld194()['held_rows']), 4],
    'held rows cookie count' => [static fn (): mixed => count($cookieHeld194()['held_rows']), 4],
    'held rows epoch count' => [static fn (): mixed => count($epochHeld194()['held_rows']), 4],
    'visible names exposed' => [static fn (): mixed => array_column($exposed194()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child', 'home', 'next_plugin', 'home:child', 'home:child:child']],
    'visible names row held' => [static fn (): mixed => array_column($rowHeld194()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'siteurl:child', 'current_plugin:child', 'siteurl:child:child', 'current_plugin:child:child']],
    'held names row result' => [static fn (): mixed => array_column($rowHeld194()['held_returning_rows'], 'name'), ['home', 'next_plugin', 'home:child', 'home:child:child']],
    'held names cookie result' => [static fn (): mixed => array_column($cookieHeld194()['held_returning_rows'], 'name'), ['home', 'next_plugin', 'home:child', 'home:child:child']],
    'current visible unique' => [static fn (): mixed => array_values(array_unique(array_column($exposed194()['current_source_rows'], 'visible_after_current_done_next194'))), [true]],
    'next visible exposed unique' => [static fn (): mixed => array_values(array_unique(array_column($exposed194()['attempted_next_source_rows'], 'visible_after_current_done_next194'))), [true]],
    'next visible row held unique' => [static fn (): mixed => array_values(array_unique(array_column($rowHeld194()['attempted_next_source_rows'], 'visible_after_current_done_next194'))), [false]],
    'row held block reasons' => [static fn (): mixed => $rowHeld194()['block_reasons_next194'], ['current-source-not-done']],
    'busy held block reasons' => [static fn (): mixed => $busyHeld194()['block_reasons_next194'], ['current-source-not-done']],
    'cookie held block reasons' => [static fn (): mixed => $cookieHeld194()['block_reasons_next194'], ['current-source-cookie-mismatch']],
    'epoch held block reasons' => [static fn (): mixed => $epochHeld194()['block_reasons_next194'], ['current-step-epoch-mismatch']],
    'resume held block reasons' => [static fn (): mixed => $resumeHeld194()['block_reasons_next194'], ['current-source-resume-token-mismatch']],
    'exposed block reasons empty' => [static fn (): mixed => $exposed194()['block_reasons_next194'], []],
    'held row reason tagged' => [static fn (): mixed => $rowHeld194()['attempted_next_source_rows'][0]['held_by_current_done_reasons_next194'], ['current-source-not-done']],
    'held cookie reason tagged' => [static fn (): mixed => $cookieHeld194()['attempted_next_source_rows'][0]['held_by_current_done_reasons_next194'], ['current-source-cookie-mismatch']],
    'exposed next reason tagged empty' => [static fn (): mixed => $exposed194()['attempted_next_source_rows'][0]['held_by_current_done_reasons_next194'], []],
    'plan current rows' => [static fn (): mixed => $exposed194()['current_done_plan_next194']['current_rows'], 6],
    'plan next rows' => [static fn (): mixed => $exposed194()['current_done_plan_next194']['attempted_next_rows'], 4],
    'plan visible rows' => [static fn (): mixed => $exposed194()['current_done_plan_next194']['visible_rows'], 10],
    'plan held rows exposed' => [static fn (): mixed => $exposed194()['current_done_plan_next194']['held_next_rows'], 0],
    'plan held rows row' => [static fn (): mixed => $rowHeld194()['current_done_plan_next194']['held_next_rows'], 4],
    'plan result code' => [static fn (): mixed => $rowHeld194()['current_done_plan_next194']['current_result_code'], 'SQLITE_ROW'],
    'plan decision exposed' => [static fn (): mixed => $exposed194()['current_done_plan_next194']['decision'], 'admit-next-source-after-current-done'],
    'plan decision held' => [static fn (): mixed => $rowHeld194()['current_done_plan_next194']['decision'], 'hold-next-source-until-current-done'],
    'plan blocked token exposed' => [static fn (): mixed => $exposed194()['current_done_plan_next194']['blocked_at_resume_token'], null],
    'plan blocked token row' => [static fn (): mixed => $rowHeld194()['current_done_plan_next194']['blocked_at_resume_token'], 'wp_recursive_view_returning_cursor_194:wp-next-returning-194:6'],
    'counts current rows' => [static fn (): mixed => $exposed194()['counts_next194']['current_rows'], 6],
    'counts next rows' => [static fn (): mixed => $exposed194()['counts_next194']['attempted_next_rows'], 4],
    'counts visible exposed' => [static fn (): mixed => $exposed194()['counts_next194']['visible_rows'], 10],
    'counts held row' => [static fn (): mixed => $rowHeld194()['counts_next194']['held_rows'], 4],
    'counts block reasons row' => [static fn (): mixed => $rowHeld194()['counts_next194']['block_reasons'], 1],
    'yield boundary exposed' => [static fn (): mixed => $exposed194()['yield_boundary_next194'], 'recursive-view-returning-current-source-next194-current-done-next-exposed'],
    'yield boundary held' => [static fn (): mixed => $rowHeld194()['yield_boundary_next194'], 'recursive-view-returning-current-source-next194-current-done-held'],
    'dependency next194' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next194', $exposed194()['dependencies_next194'], true), true],
    'dependency done gate' => [static fn (): mixed => in_array('sqlite-returning-current-source-done-gate', $exposed194()['dependencies_next194'], true), true],
    'dependency next190 retained' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next190', $exposed194()['dependencies_next194'], true), true],
    'dependency closure note' => [static fn (): mixed => $exposed194()['dependency_closure_next194'], 'no new support component needed; reuses recursive view trigger RETURNING resume rows and adds current-source SQLITE_DONE/source-cookie gating'],
    'non overlap mentions next190' => [static fn (): mixed => str_contains($exposed194()['non_overlap_next194'], 'next190'), true],
    'non recursive visible names' => [static fn (): mixed => array_column($nonRecursive194()['visible_returning_rows'], 'name'), ['siteurl', 'current_plugin', 'home', 'next_plugin']],
    'non recursive current row count' => [static fn (): mixed => count($nonRecursive194()['current_source_rows']), 2],
    'non recursive next row count' => [static fn (): mixed => count($nonRecursive194()['attempted_next_source_rows']), 2],
    'explicit expected cookie accepted' => [static fn (): mixed => $run194(['expected_current_source_cookie' => $exposed194()['current_source_cookie_next194']])['current_source_cookie_matches_next194'], true],
    'explicit expected epoch accepted' => [static fn (): mixed => $run194(['expected_current_step_epoch' => $exposed194()['current_step_epoch_next194']])['current_step_epoch_matches_next194'], true],
    'explicit expected done accepted' => [static fn (): mixed => $run194(['expected_current_result_code' => 'SQLITE_DONE'])['current_result_code_matches_next194'], true],
    'expected row does not admit' => [static fn (): mixed => $run194(['expected_current_result_code' => 'SQLITE_ROW', 'current_result_code' => 'SQLITE_ROW'])['next_source_exposed_after_current_done_next194'], false],
    'bad result code rejected' => [static fn (): mixed => $run194(['current_result_code' => 'SQLITE_LOCKED']), InvalidArgumentException::class],
    'bad expected result code rejected' => [static fn (): mixed => $run194(['expected_current_result_code' => 'SQLITE_LOCKED']), InvalidArgumentException::class],
    'bad cookie rejected' => [static fn (): mixed => $run194(['current_source_cookie' => 'bad token']), InvalidArgumentException::class],
    'bad expected cookie rejected' => [static fn (): mixed => $run194(['expected_current_source_cookie' => 'bad token']), InvalidArgumentException::class],
    'bad epoch rejected' => [static fn (): mixed => $run194(['current_step_epoch' => 'bad token']), InvalidArgumentException::class],
    'bad expected epoch rejected' => [static fn (): mixed => $run194(['expected_current_step_epoch' => 'bad token']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases194 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next194 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

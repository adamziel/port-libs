<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows167 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView167 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-167-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-167-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-drain',
];
$nextView167 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-167-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-167-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-drain',
];
$currentInput167 = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'load_policy_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInput167 = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'load_policy_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning167 = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan167 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeReturningDrainPageCursor(
    $rows167,
    $currentInput167,
    $nextInput167,
    $currentView167,
    $nextView167,
    $returning167,
    $options + ['key' => 'key_name', 'savepoint' => 'app_recursive_view_167', 'max_depth' => 2, 'page_size' => 2],
);

$pinned167 = static fn (): array => $plan167();
$admitted167 = static fn (): array => $plan167(['admit_next_source' => true]);
$wide167 = static fn (): array => $plan167(['page_size' => 3]);
$ignore167 = static fn (): array => $plan167(['admit_next_source' => true, 'conflict_action' => 'ignore']);

$cases167 = [
    'pinned base status remains current source' => [static fn (): mixed => $pinned167()['status'], 'trigger-recursive-view-returning-current-source-pinned-next164'],
    'pinned next167 status' => [static fn (): mixed => $pinned167()['status_next167'], 'trigger-recursive-view-returning-current-source-drain-fenced-next167'],
    'pinned savepoint' => [static fn (): mixed => $pinned167()['savepoint'], 'app_recursive_view_167'],
    'pinned drain cursor' => [static fn (): mixed => $pinned167()['drain_cursor'], 'current-returning-drain-167'],
    'pinned page size' => [static fn (): mixed => $pinned167()['page_size'], 2],
    'pinned current drain complete' => [static fn (): mixed => $pinned167()['current_drain_complete'], true],
    'pinned next not visible' => [static fn (): mixed => $pinned167()['next_source_visible_after_current_drain'], false],
    'pinned attempted next blocked' => [static fn (): mixed => $pinned167()['attempted_next_source_blocked_by_current_drain'], true],
    'pinned boundary' => [static fn (): mixed => $pinned167()['yield_boundary_next167'], 'recursive-view-returning-next167-next-source-blocked-until-current-pages-drain'],
    'pinned visible signature is current' => [static fn (): mixed => $pinned167()['source_signatures']['visible'], $pinned167()['source_signatures']['current']],
    'pinned current signature differs next' => [static fn (): mixed => $pinned167()['source_signatures']['current'] === $pinned167()['source_signatures']['next'], false],
    'pinned current pages count' => [static fn (): mixed => count($pinned167()['current_returning_pages']), 2],
    'pinned current page zero names' => [static fn (): mixed => $pinned167()['current_returning_pages'][0]['names'], ['module_seed', 'base_url']],
    'pinned current page one names' => [static fn (): mixed => $pinned167()['current_returning_pages'][1]['names'], ['module_seed_retry', 'module_seed_retry_retry']],
    'pinned current page zero cursor' => [static fn (): mixed => $pinned167()['current_returning_pages'][0]['cursor'], 'current-returning-drain-167:current:0'],
    'pinned current page one cursor' => [static fn (): mixed => $pinned167()['current_returning_pages'][1]['cursor'], 'current-returning-drain-167:current:1'],
    'pinned current page last flags' => [static fn (): mixed => array_column($pinned167()['current_returning_pages'], 'last'), [false, true]],
    'pinned current page drained flags' => [static fn (): mixed => array_column($pinned167()['current_returning_pages'], 'drained'), [true, true]],
    'pinned current page counts' => [static fn (): mixed => array_column($pinned167()['current_returning_pages'], 'count'), [2, 2]],
    'pinned current page phase' => [static fn (): mixed => array_column($pinned167()['current_returning_pages'], 'phase'), ['current', 'current']],
    'pinned current page sources' => [static fn (): mixed => $pinned167()['current_returning_pages'][0]['sources'], ['main@view-cookie-167-current']],
    'pinned current page trigger source' => [static fn (): mixed => $pinned167()['current_returning_pages'][0]['trigger_sources'], ['main@trigger-cookie-167-current']],
    'pinned visible pages are current only' => [static fn (): mixed => array_column($pinned167()['visible_returning_pages'], 'phase'), ['current', 'current']],
    'pinned blocked pages are attempted next' => [static fn (): mixed => array_column($pinned167()['blocked_next_source_pages'], 'phase'), ['attempted-next', 'attempted-next']],
    'pinned blocked page names zero' => [static fn (): mixed => $pinned167()['blocked_next_source_pages'][0]['names'], ['routing_rules', 'landing_url']],
    'pinned blocked page names one' => [static fn (): mixed => $pinned167()['blocked_next_source_pages'][1]['names'], ['routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'pinned next returning rows hidden' => [static fn (): mixed => $pinned167()['next_returning_rows'], []],
    'pinned attempted next pages count' => [static fn (): mixed => count($pinned167()['attempted_next_returning_pages']), 2],
    'pinned attempted next changes retained' => [static fn (): mixed => $pinned167()['attempted_next_changes'], 4],
    'pinned current changes retained' => [static fn (): mixed => $pinned167()['current_changes'], 4],
    'pinned statement rows include attempted only' => [static fn (): mixed => $pinned167()['attempted_statement_rows'], 10],
    'pinned dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next167', $pinned167()['dependencies_next167'], true), true],

    'admitted next167 status' => [static fn (): mixed => $admitted167()['status_next167'], 'trigger-recursive-view-returning-next-source-admitted-after-current-drain-next167'],
    'admitted base status' => [static fn (): mixed => $admitted167()['status'], 'trigger-recursive-view-returning-next-source-admitted-next164'],
    'admitted next visible' => [static fn (): mixed => $admitted167()['next_source_visible_after_current_drain'], true],
    'admitted attempted not blocked' => [static fn (): mixed => $admitted167()['attempted_next_source_blocked_by_current_drain'], false],
    'admitted boundary' => [static fn (): mixed => $admitted167()['yield_boundary_next167'], 'recursive-view-returning-next167-next-source-visible-after-current-pages-drained'],
    'admitted visible signature is next' => [static fn (): mixed => $admitted167()['source_signatures']['visible'], $admitted167()['source_signatures']['next']],
    'admitted visible pages include current then next' => [static fn (): mixed => array_column($admitted167()['visible_returning_pages'], 'phase'), ['current', 'current', 'next', 'next']],
    'admitted blocked pages empty' => [static fn (): mixed => $admitted167()['blocked_next_source_pages'], []],
    'admitted next pages count' => [static fn (): mixed => count($admitted167()['next_returning_pages']), 2],
    'admitted next page zero names' => [static fn (): mixed => $admitted167()['next_returning_pages'][0]['names'], ['routing_rules', 'landing_url']],
    'admitted next page one names' => [static fn (): mixed => $admitted167()['next_returning_pages'][1]['names'], ['routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'admitted next page trigger source' => [static fn (): mixed => $admitted167()['next_returning_pages'][0]['trigger_sources'], ['main@trigger-cookie-167-next']],
    'admitted changes include both drains' => [static fn (): mixed => $admitted167()['changes'], 8],
    'admitted final landing_url value' => [static fn (): mixed => $admitted167()['after_savepoint'][1]['key_value'], 'https://next-landing_url.test'],

    'wide current pages count' => [static fn (): mixed => count($wide167()['current_returning_pages']), 2],
    'wide current page counts' => [static fn (): mixed => array_column($wide167()['current_returning_pages'], 'count'), [3, 1]],
    'wide current page names zero' => [static fn (): mixed => $wide167()['current_returning_pages'][0]['names'], ['module_seed', 'base_url', 'module_seed_retry']],
    'wide blocked page counts' => [static fn (): mixed => array_column($wide167()['blocked_next_source_pages'], 'count'), [3, 1]],

    'ignore current conflict skipped before drain' => [static fn (): mixed => array_column(array_column($ignore167()['current_skipped_rows'], 'returning'), 'key_name'), ['skip_me', 'base_url']],
    'ignore next conflict skipped after drain' => [static fn (): mixed => array_column(array_column($ignore167()['next_skipped_rows'], 'returning'), 'key_name'), ['landing_url', 'next_skip']],
    'ignore visible pages still include next phase' => [static fn (): mixed => array_column($ignore167()['visible_returning_pages'], 'phase'), ['current', 'current', 'next', 'next']],
    'ignore current page names' => [static fn (): mixed => $ignore167()['current_returning_pages'][0]['names'], ['module_seed', 'module_seed_retry']],
    'ignore next page names' => [static fn (): mixed => $ignore167()['next_returning_pages'][0]['names'], ['routing_rules', 'routing_rules_next_retry']],

    'custom cursor applied' => [static fn (): mixed => $plan167(['drain_cursor' => 'app-drain/custom_167'])['current_returning_pages'][0]['cursor'], 'app-drain/custom_167:current:0'],
    'page size zero throws' => [static fn (): mixed => $plan167(['page_size' => 0]), InvalidArgumentException::class],
    'bad cursor throws' => [static fn (): mixed => $plan167(['drain_cursor' => 'bad cursor']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases167 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next167 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows171 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView171 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-171-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-171-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-cursor',
];
$nextView171 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-171-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-171-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-cursor',
];
$currentInput171 = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'load_policy_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInput171 = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'load_policy_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning171 = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan171 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeReturningCursorWatermark(
    $rows171,
    $currentInput171,
    $nextInput171,
    $currentView171,
    $nextView171,
    $returning171,
    $options + ['key' => 'key_name', 'savepoint' => 'app_recursive_view_171', 'max_depth' => 2, 'page_size' => 2],
);

$open171 = static fn (): array => $plan171(['admit_next_source' => true, 'acknowledged_current_pages' => 1]);
$closed171 = static fn (): array => $plan171(['admit_next_source' => true]);
$pinned171 = static fn (): array => $plan171();
$none171 = static fn (): array => $plan171(['admit_next_source' => true, 'acknowledged_current_pages' => 0]);
$wideOpen171 = static fn (): array => $plan171(['admit_next_source' => true, 'page_size' => 3, 'acknowledged_current_pages' => 1]);
$ignoreOpen171 = static fn (): array => $plan171(['admit_next_source' => true, 'conflict_action' => 'ignore', 'acknowledged_current_pages' => 1]);

$cases171 = [
    'open status' => [static fn (): mixed => $open171()['status_next171'], 'trigger-recursive-view-returning-current-source-cursor-open-next171'],
    'open base admitted status' => [static fn (): mixed => $open171()['status_next167'], 'trigger-recursive-view-returning-next-source-admitted-after-current-drain-next167'],
    'open acknowledged page count' => [static fn (): mixed => $open171()['acknowledged_current_pages'], 1],
    'open pending page count' => [static fn (): mixed => $open171()['pending_current_pages'], 1],
    'open cursor incomplete' => [static fn (): mixed => $open171()['current_returning_cursor_complete'], false],
    'open fences next source' => [static fn (): mixed => $open171()['next_source_fenced_by_open_returning_cursor'], true],
    'open next not visible after cursor close' => [static fn (): mixed => $open171()['next_source_visible_after_cursor_close'], false],
    'open watermark' => [static fn (): mixed => $open171()['cursor_watermark_next171'], 'current-returning-drain-167:ack-1-of-2'],
    'open boundary' => [static fn (): mixed => $open171()['yield_boundary_next171'], 'recursive-view-returning-next171-open-current-cursor-fences-next-source'],
    'open visible page phases' => [static fn (): mixed => array_column($open171()['visible_returning_pages_next171'], 'phase'), ['current']],
    'open visible page names' => [static fn (): mixed => $open171()['visible_returning_pages_next171'][0]['names'], ['module_seed', 'base_url']],
    'open pending page names' => [static fn (): mixed => $open171()['current_returning_pending_pages'][0]['names'], ['module_seed_retry', 'module_seed_retry_retry']],
    'open blocked next phases' => [static fn (): mixed => array_column($open171()['blocked_next_source_pages_next171'], 'phase'), ['attempted-next', 'attempted-next']],
    'open blocked next page zero' => [static fn (): mixed => $open171()['blocked_next_source_pages_next171'][0]['names'], ['routing_rules', 'landing_url']],
    'open blocked next page one' => [static fn (): mixed => $open171()['blocked_next_source_pages_next171'][1]['names'], ['routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'open next rows still computed but fenced' => [static fn (): mixed => array_column(array_column($open171()['next_returning_rows'], 'returning'), 'key_name'), ['routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'open base source signature is next while cursor gate fences rows' => [static fn (): mixed => $open171()['source_signatures']['visible'], $open171()['source_signatures']['next']],
    'open dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next171', $open171()['dependencies_next171'], true), true],
    'open cursor dependency marker' => [static fn (): mixed => in_array('sqlite-returning-cursor-close-before-next-view-source', $open171()['dependencies_next171'], true), true],

    'closed status' => [static fn (): mixed => $closed171()['status_next171'], 'trigger-recursive-view-returning-next-source-visible-after-cursor-close-next171'],
    'closed acknowledged page count' => [static fn (): mixed => $closed171()['acknowledged_current_pages'], 2],
    'closed pending page count' => [static fn (): mixed => $closed171()['pending_current_pages'], 0],
    'closed cursor complete' => [static fn (): mixed => $closed171()['current_returning_cursor_complete'], true],
    'closed does not fence next source' => [static fn (): mixed => $closed171()['next_source_fenced_by_open_returning_cursor'], false],
    'closed next visible' => [static fn (): mixed => $closed171()['next_source_visible_after_cursor_close'], true],
    'closed watermark' => [static fn (): mixed => $closed171()['cursor_watermark_next171'], 'current-returning-drain-167:ack-2-of-2'],
    'closed boundary' => [static fn (): mixed => $closed171()['yield_boundary_next171'], 'recursive-view-returning-next171-current-cursor-closed-next-source-visible'],
    'closed visible phases' => [static fn (): mixed => array_column($closed171()['visible_returning_pages_next171'], 'phase'), ['current', 'current', 'next', 'next']],
    'closed blocked pages empty' => [static fn (): mixed => $closed171()['blocked_next_source_pages_next171'], []],
    'closed final landing_url value' => [static fn (): mixed => $closed171()['after_savepoint'][1]['key_value'], 'https://next-landing_url.test'],
    'closed current acknowledged names' => [static fn (): mixed => array_merge(...array_column($closed171()['current_returning_acknowledged_pages'], 'names')), ['module_seed', 'base_url', 'module_seed_retry', 'module_seed_retry_retry']],
    'closed next visible page source' => [static fn (): mixed => $closed171()['visible_returning_pages_next171'][2]['sources'], ['main@view-cookie-171-next']],
    'closed next visible trigger source' => [static fn (): mixed => $closed171()['visible_returning_pages_next171'][2]['trigger_sources'], ['main@trigger-cookie-171-next']],

    'pinned status after cursor close' => [static fn (): mixed => $pinned171()['status_next171'], 'trigger-recursive-view-returning-current-source-cursor-closed-next171'],
    'pinned next source not admitted' => [static fn (): mixed => $pinned171()['next_source_admitted'], false],
    'pinned cursor complete' => [static fn (): mixed => $pinned171()['current_returning_cursor_complete'], true],
    'pinned next not visible' => [static fn (): mixed => $pinned171()['next_source_visible_after_cursor_close'], false],
    'pinned blocked next phases' => [static fn (): mixed => array_column($pinned171()['blocked_next_source_pages_next171'], 'phase'), ['attempted-next', 'attempted-next']],
    'pinned visible pages current only' => [static fn (): mixed => array_column($pinned171()['visible_returning_pages_next171'], 'phase'), ['current', 'current']],
    'pinned boundary' => [static fn (): mixed => $pinned171()['yield_boundary_next171'], 'recursive-view-returning-next171-current-cursor-closed-next-source-still-pinned'],

    'none acknowledged status' => [static fn (): mixed => $none171()['status_next171'], 'trigger-recursive-view-returning-current-source-cursor-open-next171'],
    'none visible pages empty' => [static fn (): mixed => $none171()['visible_returning_pages_next171'], []],
    'none pending pages both current pages' => [static fn (): mixed => count($none171()['current_returning_pending_pages']), 2],
    'none watermark' => [static fn (): mixed => $none171()['cursor_watermark_next171'], 'current-returning-drain-167:ack-0-of-2'],

    'wide open acknowledged page has three rows' => [static fn (): mixed => $wideOpen171()['current_returning_acknowledged_pages'][0]['names'], ['module_seed', 'base_url', 'module_seed_retry']],
    'wide open pending page has recursive tail' => [static fn (): mixed => $wideOpen171()['current_returning_pending_pages'][0]['names'], ['module_seed_retry_retry']],
    'wide open watermark' => [static fn (): mixed => $wideOpen171()['cursor_watermark_next171'], 'current-returning-drain-167:ack-1-of-2'],
    'wide open blocks next despite base admission' => [static fn (): mixed => $wideOpen171()['next_source_visible_after_cursor_close'], false],

    'ignore open current acknowledged names' => [static fn (): mixed => $ignoreOpen171()['current_returning_acknowledged_pages'][0]['names'], ['module_seed', 'module_seed_retry']],
    'ignore open pending current names' => [static fn (): mixed => $ignoreOpen171()['current_returning_pending_pages'][0]['names'], ['module_seed_retry_retry']],
    'ignore open current skipped names' => [static fn (): mixed => array_column(array_column($ignoreOpen171()['current_skipped_rows'], 'returning'), 'key_name'), ['skip_me', 'base_url']],
    'ignore open next skipped names' => [static fn (): mixed => array_column(array_column($ignoreOpen171()['next_skipped_rows'], 'returning'), 'key_name'), ['landing_url', 'next_skip']],
    'ignore open blocked next names' => [static fn (): mixed => $ignoreOpen171()['blocked_next_source_pages_next171'][0]['names'], ['routing_rules', 'routing_rules_next_retry']],

    'ack beyond page count clamps' => [static fn (): mixed => $plan171(['admit_next_source' => true, 'acknowledged_current_pages' => 99])['acknowledged_current_pages'], 2],
    'custom cursor watermark' => [static fn (): mixed => $plan171(['admit_next_source' => true, 'drain_cursor' => 'app-current/returning_171', 'acknowledged_current_pages' => 1])['cursor_watermark_next171'], 'app-current/returning_171:ack-1-of-2'],
    'negative acknowledged pages throws' => [static fn (): mixed => $plan171(['acknowledged_current_pages' => -1]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases171 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next171 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

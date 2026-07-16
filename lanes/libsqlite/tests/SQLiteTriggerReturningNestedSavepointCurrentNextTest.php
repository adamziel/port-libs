<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningNestedSavepointCurrentNextPlan;

$baseRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'autoload' => 'yes', 'revision' => 1],
];
$insertRows = [
    ['option_id' => 3, 'option_name' => 'plugin_enabled', 'option_value' => 'draft', 'autoload' => 'no', 'revision' => 0],
    ['option_id' => 4, 'option_name' => 'plugin_abort', 'option_value' => 'draft', 'autoload' => 'no', 'revision' => 0],
];
$returning = [
    'option_id',
    'option_name',
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'new.option_value', 'as' => 'new_value'],
    ['expr' => 'new.revision', 'as' => 'next_revision'],
    static fn (array $next, array $old, int $ordinal, string $status): string => $status . ':' . $ordinal . ':' . ($old['option_name'] ?? 'insert') . '>' . $next['option_name'],
];
$triggers = [
    [
        'name' => 'wp_options_bi_tag_import',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'when' => ['new.autoload', '=', 'no'],
        'set' => [
            'autoload' => 'imported',
            'option_value' => static fn (array $old, array $new): string => 'inserted:' . $new['option_name'],
        ],
        'values' => ['name' => 'new.option_name', 'autoload' => 'new.autoload'],
    ],
    [
        'name' => 'wp_options_ai_audit',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_bu_increment_revision',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.autoload', '=', 'imported'],
        'set' => ['revision' => static fn (array $old, array $new): int => (int) $new['revision'] + 10],
        'values' => ['name' => 'old.option_name', 'revision' => 'new.revision'],
    ],
    [
        'name' => 'wp_options_au_abort_plugin',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'raise',
        'raise' => 'rollback',
        'when' => ['new.option_name', '=', 'plugin_abort'],
        'reason' => 'plugin update trigger aborts child savepoint',
    ],
];

$plan = static fn (): array => SQLiteTriggerReturningNestedSavepointCurrentNextPlan::apply(
    'wp_import_outer',
    'wp_import_insert_release',
    'wp_import_update_rollback',
    $baseRows,
    $insertRows,
    [
        'option_value' => static fn (array $row): string => 'updated:' . $row['option_name'],
        'revision' => static fn (array $row): int => (int) $row['revision'] + 1,
    ],
    static fn (array $row): bool => str_starts_with((string) $row['option_name'], 'plugin_'),
    $triggers,
    $returning,
);

$starPlan = static fn (): array => SQLiteTriggerReturningNestedSavepointCurrentNextPlan::apply(
    'outer_ok',
    'insert_release',
    'update_rollback',
    $baseRows,
    [$insertRows[0]],
    ['option_value' => 'updated'],
    static fn (array $row): bool => $row['option_name'] === 'plugin_enabled',
    [
        [
            'name' => 'abort_enabled',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'raise',
            'raise' => 'rollback',
            'when' => ['new.option_name', '=', 'plugin_enabled'],
            'reason' => 'abort star update',
        ],
    ],
    ['*'],
);

$cases = [
    'status is child rolled back' => [static fn (): mixed => $plan()['status'], 'child-rolled-back'],
    'outer savepoint retained' => [static fn (): mixed => $plan()['outer_savepoint'], 'wp_import_outer'],
    'released savepoint retained' => [static fn (): mixed => $plan()['released_savepoint'], 'wp_import_insert_release'],
    'rollback savepoint retained' => [static fn (): mixed => $plan()['rollback_savepoint'], 'wp_import_update_rollback'],
    'base row count' => [static fn (): mixed => count($plan()['base_rows']), 2],
    'release row count includes inserts' => [static fn (): mixed => count($plan()['release_rows']), 4],
    'next row count keeps released inserts' => [static fn (): mixed => count($plan()['next_rows']), 4],
    'current rows include attempted update' => [static fn (): mixed => array_column($plan()['current_rows'], 'option_value'), ['https://old.test', 'https://old.test/home', 'updated:plugin_enabled', 'updated:plugin_abort']],
    'next rows restore released image' => [static fn (): mixed => array_column($plan()['next_rows'], 'option_value'), ['https://old.test', 'https://old.test/home', 'inserted:plugin_enabled', 'inserted:plugin_abort']],
    'release rows match next rows' => [static fn (): mixed => $plan()['release_rows'], $plan()['next_rows']],
    'rollback attempt rows match current rows' => [static fn (): mixed => $plan()['rollback_attempt_rows'], $plan()['current_rows']],
    'insert trigger changed autoload' => [static fn (): mixed => array_column($plan()['release_rows'], 'autoload'), ['yes', 'yes', 'imported', 'imported']],
    'update trigger changed attempted revisions' => [static fn (): mixed => array_column($plan()['current_rows'], 'revision'), [1, 1, 11, 11]],
    'next revisions restore released rows' => [static fn (): mixed => array_column($plan()['next_rows'], 'revision'), [1, 1, 0, 0]],
    'released yield count' => [static fn (): mixed => count($plan()['released_yield_stream']), 2],
    'rollback current yield count' => [static fn (): mixed => count($plan()['rollback_current_yield_stream']), 2],
    'released yield events' => [static fn (): mixed => array_column($plan()['released_yield_stream'], 'event'), ['insert', 'insert']],
    'rollback yield events' => [static fn (): mixed => array_column($plan()['rollback_current_yield_stream'], 'event'), ['update', 'update']],
    'released yield statuses' => [static fn (): mixed => array_column($plan()['released_yield_stream'], 'status'), ['released-to-outer', 'released-to-outer']],
    'rollback yield statuses' => [static fn (): mixed => array_column($plan()['rollback_current_yield_stream'], 'status'), ['current-before-rollback', 'current-before-rollback']],
    'released yield row indexes' => [static fn (): mixed => array_column($plan()['released_yield_stream'], 'row_index'), [2, 3]],
    'rollback yield row indexes' => [static fn (): mixed => array_column($plan()['rollback_current_yield_stream'], 'row_index'), [2, 3]],
    'released returning names' => [static fn (): mixed => array_column($plan()['released_returning_rows'], 'option_name'), ['plugin_enabled', 'plugin_abort']],
    'released returning old values are null inserts' => [static fn (): mixed => array_column($plan()['released_returning_rows'], 'old_value'), [null, null]],
    'released returning new values' => [static fn (): mixed => array_column($plan()['released_returning_rows'], 'new_value'), ['inserted:plugin_enabled', 'inserted:plugin_abort']],
    'released returning revisions' => [static fn (): mixed => array_column($plan()['released_returning_rows'], 'next_revision'), [0, 0]],
    'released callable labels' => [static fn (): mixed => array_column($plan()['released_returning_rows'], 'expr5'), ['released-to-outer:0:insert>plugin_enabled', 'released-to-outer:1:insert>plugin_abort']],
    'rollback current returning names' => [static fn (): mixed => array_column($plan()['rollback_current_returning_rows'], 'option_name'), ['plugin_enabled', 'plugin_abort']],
    'rollback current returning old values' => [static fn (): mixed => array_column($plan()['rollback_current_returning_rows'], 'old_value'), ['inserted:plugin_enabled', 'inserted:plugin_abort']],
    'rollback current returning new values' => [static fn (): mixed => array_column($plan()['rollback_current_returning_rows'], 'new_value'), ['updated:plugin_enabled', 'updated:plugin_abort']],
    'rollback current returning revisions' => [static fn (): mixed => array_column($plan()['rollback_current_returning_rows'], 'next_revision'), [11, 11]],
    'rollback callable labels' => [static fn (): mixed => array_column($plan()['rollback_current_returning_rows'], 'expr5'), ['current-before-rollback:0:plugin_enabled>plugin_enabled', 'current-before-rollback:1:plugin_abort>plugin_abort']],
    'next returning suppresses rolled back update rows' => [static fn (): mixed => $plan()['next_returning_rows'], $plan()['released_returning_rows']],
    'release changes count' => [static fn (): mixed => $plan()['release_changes'], 2],
    'rollback attempted changes count' => [static fn (): mixed => $plan()['rollback_attempted_changes'], 2],
    'next changes keeps release only' => [static fn (): mixed => $plan()['next_changes'], 2],
    'rollback reason retained' => [static fn (): mixed => $plan()['rollback_reason'], 'plugin update trigger aborts child savepoint'],
    'rollback ordinal retained' => [static fn (): mixed => $plan()['rollback_at_ordinal'], 1],
    'discarded row count' => [static fn (): mixed => count($plan()['discarded']), 2],
    'discarded row indexes' => [static fn (): mixed => array_column($plan()['discarded'], 'row_index'), [2, 3]],
    'discarded attempted values' => [static fn (): mixed => array_column(array_column($plan()['discarded'], 'row'), 'option_value'), ['updated:plugin_enabled', 'updated:plugin_abort']],
    'discarded savepoint values' => [static fn (): mixed => array_column(array_column($plan()['discarded'], 'savepoint_row'), 'option_value'), ['inserted:plugin_enabled', 'inserted:plugin_abort']],
    'trigger effect names' => [static fn (): mixed => array_column($plan()['trigger_effects'], 'trigger'), ['wp_options_bi_tag_import', 'wp_options_ai_audit', 'wp_options_bi_tag_import', 'wp_options_ai_audit', 'wp_options_bu_increment_revision', 'wp_options_bu_increment_revision', null]],
    'trigger effect timings' => [static fn (): mixed => array_column($plan()['trigger_effects'], 'timing'), ['before', 'after', 'before', 'after', 'before', 'before', 'savepoint']],
    'trigger effect actions' => [static fn (): mixed => array_column($plan()['trigger_effects'], 'action'), ['set-new', 'audit', 'set-new', 'audit', 'set-new', 'set-new', 'rollback-child-savepoint']],
    'trigger effect ordinals' => [static fn (): mixed => array_column($plan()['trigger_effects'], 'ordinal'), [0, 0, 1, 1, 0, 1, 1]],
    'trigger effect projected names' => [static fn (): mixed => array_column(array_slice(array_column($plan()['trigger_effects'], 'row'), 0, 6), 'name'), ['plugin_enabled', 'plugin_enabled', 'plugin_abort', 'plugin_abort', 'plugin_enabled', 'plugin_abort']],
    'rollback effect reason' => [static fn (): mixed => $plan()['trigger_effects'][6]['reason'], 'plugin update trigger aborts child savepoint'],
    'rollback effect discarded count' => [static fn (): mixed => $plan()['trigger_effects'][6]['discarded_count'], 2],
    'release preserved in outer' => [static fn (): mixed => $plan()['release_preserved_in_outer'], true],
    'rollback preserved released rows' => [static fn (): mixed => $plan()['rollback_preserved_released_rows'], true],
    'dependency marker nested savepoint' => [static fn (): mixed => in_array('sqlite-trigger-returning-nested-savepoint', $plan()['dependencies'], true), true],
    'dependency marker release propagates' => [static fn (): mixed => in_array('sqlite-nested-savepoint-release-propagates', $plan()['dependencies'], true), true],
    'dependency marker rollback suppresses' => [static fn (): mixed => in_array('sqlite-nested-savepoint-rollback-suppresses-current-returning', $plan()['dependencies'], true), true],
    'star returning captures inserted row' => [static fn (): mixed => $starPlan()['released_returning_rows'][0]['*']['option_value'], 'draft'],
    'star rollback suppresses update next rows' => [static fn (): mixed => $starPlan()['next_returning_rows'][0]['*']['option_name'], 'plugin_enabled'],
    'bad outer savepoint throws' => [static fn (): mixed => SQLiteTriggerReturningNestedSavepointCurrentNextPlan::apply('bad-name', 'ok_name', 'ok_name2', $baseRows, $insertRows, ['option_value' => 'x'], static fn (): bool => true, [], $returning), InvalidArgumentException::class],
    'bad released savepoint throws' => [static fn (): mixed => SQLiteTriggerReturningNestedSavepointCurrentNextPlan::apply('ok_name', 'bad-name', 'ok_name2', $baseRows, $insertRows, ['option_value' => 'x'], static fn (): bool => true, [], $returning), InvalidArgumentException::class],
    'missing assignments throws' => [static fn (): mixed => SQLiteTriggerReturningNestedSavepointCurrentNextPlan::apply('ok_name', 'ok_name2', 'ok_name3', $baseRows, $insertRows, [], static fn (): bool => true, [], $returning), InvalidArgumentException::class],
    'bad returning alias throws' => [static fn (): mixed => SQLiteTriggerReturningNestedSavepointCurrentNextPlan::apply('ok_name', 'ok_name2', 'ok_name3', $baseRows, $insertRows, ['option_value' => 'x'], static fn (): bool => true, [], [['expr' => 'new.option_name', 'as' => 'bad-alias']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger returning nested savepoint ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

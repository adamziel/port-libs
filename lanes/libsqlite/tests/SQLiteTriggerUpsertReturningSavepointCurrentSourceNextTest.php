<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1],
];

$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'revision' => static fn (array $old, array $incoming): mixed => $old['revision'] + 1,
];

$triggers = [
    [
        'name' => 'wp_options_bu_siteurl_suffix',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.option_name', '=', 'siteurl'],
        'set' => ['option_value' => 'concat:new.option_value:/wp'],
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_ai_abort_bad_plugin',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'raise',
        'when' => ['new.option_name', '=', 'bad_plugin'],
        'reason' => 'blocked-plugin-option-after-returning',
        'values' => ['name' => 'new.option_name'],
    ],
];

$returning = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_id', 'as' => 'id'],
    ['expr' => 'old_or_null.option_id', 'as' => 'old_id'],
    ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $ordinal): string => $event . ':' . $ordinal . ':' . $new['option_name'],
];

$plan = static function (array $current = null, array $next = null, array $triggerSet = null, array $projection = null, array $options = []) use ($rows, $assignments, $triggers, $returning): array {
    return SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan::execute(
        $rows,
        $current ?? [
            ['option_id' => 11, 'option_name' => 'siteurl', 'option_value' => 'https://current.test', 'autoload' => 'yes', 'revision' => 0],
            ['option_id' => 12, 'option_name' => 'bad_plugin', 'option_value' => 'bad', 'autoload' => 'no', 'revision' => 0],
        ],
        $next ?? [
            ['option_id' => 21, 'option_name' => 'siteurl', 'option_value' => 'https://retry.test', 'autoload' => 'yes', 'revision' => 0],
            ['option_id' => 22, 'option_name' => 'fresh_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'revision' => 0],
        ],
        ['option_name'],
        $assignments,
        $triggerSet ?? $triggers,
        $projection ?? $returning,
        $options + ['savepoint' => 'wp_import_current', 'wal_frame' => 40],
    );
};

$rollback = static fn (): array => $plan();
$release = static fn (): array => $plan([
    ['option_id' => 31, 'option_name' => 'siteurl', 'option_value' => 'https://released.test', 'autoload' => 'yes', 'revision' => 0],
], [
    ['option_id' => 32, 'option_name' => 'fresh_after_release', 'option_value' => 'after', 'autoload' => 'no', 'revision' => 0],
]);
$nextRollback = static fn (): array => $plan([
    ['option_id' => 41, 'option_name' => 'home', 'option_value' => 'https://current-home.test', 'autoload' => 'yes', 'revision' => 0],
], [
    ['option_id' => 42, 'option_name' => 'bad_plugin', 'option_value' => 'retry-bad', 'autoload' => 'no', 'revision' => 0],
]);

$cases = [
    'rollback savepoint name retained' => [static fn (): mixed => $rollback()['savepoint'], 'wp_import_current'],
    'rollback status records next source application' => [static fn (): mixed => $rollback()['status'], 'current-rolled-back-next-source-applied'],
    'rollback current frame retained' => [static fn (): mixed => $rollback()['current_wal_frame'], 40],
    'rollback next frame advances only retry rows' => [static fn (): mixed => $rollback()['next_wal_frame'], 42],
    'rollback savepoint rows are original' => [static fn (): mixed => array_column($rollback()['savepoint_rows'], 'option_name'), ['siteurl', 'home']],
    'rollback attempted current rows include changed siteurl and bad insert' => [static fn (): mixed => array_column($rollback()['current_attempt_rows'], 'option_name'), ['siteurl', 'home', 'bad_plugin']],
    'rollback attempted current siteurl is current attempt value' => [static fn (): mixed => $rollback()['current_attempt_rows'][0]['option_value'], 'https://current.test/wp'],
    'rollback current returning suppressed' => [static fn (): mixed => $rollback()['current_returning_rows'], []],
    'rollback attempted current yield retained' => [static fn (): mixed => array_column($rollback()['attempted_current_yields'], 'option_name'), ['siteurl']],
    'rollback attempted current returning retained as diagnostic' => [static fn (): mixed => $rollback()['attempted_current_yields'][0]['returning']['name'], 'siteurl'],
    'rollback attempted current callable returning retained' => [static fn (): mixed => $rollback()['attempted_current_yields'][0]['returning']['expr4'], 'update:0:siteurl'],
    'rollback current changes reset' => [static fn (): mixed => $rollback()['current_changes'], 0],
    'rollback attempted changes include current and next attempts' => [static fn (): mixed => $rollback()['total_attempted_changes'], 3],
    'rollback committed changes include only next attempts' => [static fn (): mixed => $rollback()['committed_changes'], 2],
    'rollback next starts from savepoint' => [static fn (): mixed => $rollback()['next_started_from_savepoint'], true],
    'rollback returning suppressed flag set' => [static fn (): mixed => $rollback()['returning_suppressed_after_rollback'], true],
    'rollback reason names after trigger' => [static fn (): mixed => $rollback()['rollback_reason'], 'blocked-plugin-option-after-returning'],
    'rollback discarded rows include changed siteurl and bad plugin' => [static fn (): mixed => array_column($rollback()['discarded_current_rows'], 'option_name'), ['siteurl', 'bad_plugin']],
    'rollback next rows derive from retry not attempted current source' => [static fn (): mixed => $rollback()['next_rows'][0]['option_value'], 'https://retry.test/wp'],
    'rollback next rows include fresh retry insert' => [static fn (): mixed => array_column($rollback()['next_rows'], 'option_name'), ['siteurl', 'home', 'fresh_plugin']],
    'rollback next returning names' => [static fn (): mixed => array_column($rollback()['next_returning_rows'], 'name'), ['siteurl', 'fresh_plugin']],
    'rollback next returning old id for siteurl is savepoint id' => [static fn (): mixed => $rollback()['next_returning_rows'][0]['old_id'], 1],
    'rollback next returning incoming value preserves excluded row' => [static fn (): mixed => $rollback()['next_returning_rows'][0]['incoming_value'], 'https://retry.test'],
    'rollback next insert returning callable labels insert' => [static fn (): mixed => $rollback()['next_returning_rows'][1]['expr4'], 'insert:1:fresh_plugin'],
    'rollback next yields phases are next' => [static fn (): mixed => array_column($rollback()['attempted_next_yields'], 'phase'), ['next', 'next']],
    'rollback trigger effects phases include current and next' => [static fn (): mixed => array_column($rollback()['current_trigger_effects'], 'phase'), ['current', 'current']],
    'rollback savepoint effect is recorded' => [static fn (): mixed => $rollback()['current_trigger_effects'][1]['action'], 'rollback-current-source'],
    'rollback next trigger applies retry siteurl suffix' => [static fn (): mixed => $rollback()['next_trigger_effects'][0]['row']['value'], 'https://retry.test/wp'],
    'rollback dependencies include next129 marker' => [static fn (): mixed => in_array('sqlite-trigger-upsert-returning-savepoint-current-source-next129', $rollback()['dependencies'], true), true],
    'rollback dependencies include returning suppression marker' => [static fn (): mixed => in_array('sqlite-returning-yield-before-current-savepoint-rollback', $rollback()['dependencies'], true), true],

    'release status records current and next release' => [static fn (): mixed => $release()['status'], 'current-and-next-released'],
    'release current rows are committed' => [static fn (): mixed => array_column($release()['current_returning_rows'], 'name'), ['siteurl']],
    'release next does not start from savepoint' => [static fn (): mixed => $release()['next_started_from_savepoint'], false],
    'release next sees released current source' => [static fn (): mixed => $release()['next_rows'][0]['option_value'], 'https://released.test/wp'],
    'release next appends fresh row' => [static fn (): mixed => array_column($release()['next_rows'], 'option_name'), ['siteurl', 'home', 'fresh_after_release']],
    'release committed changes include both phases' => [static fn (): mixed => $release()['committed_changes'], 2],
    'release next frame includes current and next' => [static fn (): mixed => $release()['next_wal_frame'], 42],
    'release suppression flag false' => [static fn (): mixed => $release()['returning_suppressed_after_rollback'], false],
    'release rollback reason null' => [static fn (): mixed => $release()['rollback_reason'], null],
    'release discarded rows empty' => [static fn (): mixed => $release()['discarded_current_rows'], []],

    'next rollback status records second rollback' => [static fn (): mixed => $nextRollback()['status'], 'current-released-next-rolled-back'],
    'next rollback current committed' => [static fn (): mixed => $nextRollback()['current_changes'], 1],
    'next rollback next changes zero' => [static fn (): mixed => $nextRollback()['next_changes'], 0],
    'next rollback final rows are current source before retry' => [static fn (): mixed => array_column($nextRollback()['next_rows'], 'option_name'), ['siteurl', 'home']],
    'next rollback next returning suppressed' => [static fn (): mixed => $nextRollback()['next_returning_rows'], []],
    'next rollback current returning still visible' => [static fn (): mixed => $nextRollback()['current_returning_rows'][0]['name'], 'home'],
    'next rollback committed changes include current only' => [static fn (): mixed => $nextRollback()['committed_changes'], 1],

    'empty savepoint throws' => [static fn (): mixed => $plan([], [], null, null, ['savepoint' => ' ']), InvalidArgumentException::class],
    'empty conflict target throws' => [static fn (): mixed => SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan::execute($rows, [], [], [], $assignments, $triggers, $returning), InvalidArgumentException::class],
    'bad assignment column throws' => [static fn (): mixed => SQLiteTriggerUpsertReturningSavepointCurrentSourceNextPlan::execute($rows, [], [], ['option_name'], ['bad-column' => static fn (): int => 1], $triggers, $returning), InvalidArgumentException::class],
    'empty returning throws' => [static fn (): mixed => $plan([], [], null, []), InvalidArgumentException::class],
    'missing conflict column throws' => [static fn (): mixed => $plan([['option_id' => 99, 'option_value' => 'missing']], []), InvalidArgumentException::class],
    'old returning on insert throws' => [static fn (): mixed => $plan([['option_id' => 91, 'option_name' => 'fresh_old', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 0]], [], [], [['expr' => 'old.option_id', 'as' => 'old_id']]), InvalidArgumentException::class],
    'bad returning alias throws' => [static fn (): mixed => $plan([['option_id' => 92, 'option_name' => 'fresh_alias', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 0]], [], [], [['expr' => 'new.option_name', 'as' => 'bad-alias']]), InvalidArgumentException::class],
    'bad trigger action throws' => [static fn (): mixed => $plan([['option_id' => 93, 'option_name' => 'fresh_action', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 0]], [], [[
        'name' => 'bad',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'delete-row',
    ]]), InvalidArgumentException::class],
    'bad when operator throws' => [static fn (): mixed => $plan([['option_id' => 94, 'option_name' => 'fresh_when', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 0]], [], [[
        'name' => 'bad_when',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'when' => ['new.option_name', 'LIKE', 'fresh%'],
    ]]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger upsert returning savepoint current source next129 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningSavepointPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 3, 'option_name' => 'skip_plugin', 'option_value' => 'skip', 'autoload' => 'no', 'revision' => 2],
    ['option_id' => 4, 'option_name' => 'bad_plugin', 'option_value' => 'bad', 'autoload' => 'no', 'revision' => 3],
];

$assignments = [
    'option_value' => static fn (array $row): string => 'imported:' . $row['option_name'],
    'revision' => static fn (array $row): int => (int) $row['revision'] + 1,
];

$returning = [
    'option_name',
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'new.option_value', 'as' => 'new_value'],
    ['expr' => 'new.revision', 'as' => 'next_revision'],
    static fn (array $next, array $old, int $ordinal, string $status): string => $ordinal . ':' . $status . ':' . $old['option_name'] . '>' . $next['option_value'],
];

$baseTriggers = [
    [
        'name' => 'wp_options_bu_skip_plugin',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'raise',
        'raise' => 'ignore',
        'when' => ['old.option_name', '=', 'skip_plugin'],
        'reason' => 'skip plugin-owned option',
    ],
    [
        'name' => 'wp_options_bu_mark_autoload',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['old.autoload', '=', 'yes'],
        'set' => ['autoload' => 'imported'],
        'values' => ['name' => 'old.option_name', 'autoload' => 'new.autoload'],
    ],
    [
        'name' => 'wp_options_au_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'audit',
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
];

$commit = static fn (): array => SQLiteTriggerReturningSavepointPlan::updateRows(
    'wp_import_current',
    $rows,
    $assignments,
    static fn (array $row): bool => str_contains((string) $row['option_name'], 'plugin') || $row['autoload'] === 'yes',
    $baseTriggers,
    $returning,
);

$rollbackTriggers = $baseTriggers;
$rollbackTriggers[] = [
    'name' => 'wp_options_au_bad_plugin_rollback',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'raise',
    'raise' => 'rollback',
    'when' => ['new.option_name', '=', 'bad_plugin'],
    'reason' => 'bad plugin option aborts import',
];

$rollback = static fn (): array => SQLiteTriggerReturningSavepointPlan::updateRows(
    'wp_import_current',
    $rows,
    $assignments,
    static fn (array $row): bool => str_contains((string) $row['option_name'], 'plugin') || $row['autoload'] === 'yes',
    $rollbackTriggers,
    $returning,
);

$star = static fn (): array => SQLiteTriggerReturningSavepointPlan::updateRows(
    'wp_import_current',
    $rows,
    ['option_value' => 'star'],
    static fn (array $row): bool => $row['option_id'] === 1,
    [],
    ['*'],
);

$cases = [
    'commit status ok' => [static fn (): mixed => $commit()['status'], 'commit-ok'],
    'commit savepoint name retained' => [static fn (): mixed => $commit()['savepoint'], 'wp_import_current'],
    'commit changes excludes ignored row' => [static fn (): mixed => $commit()['changes'], 3],
    'commit attempted changes equals changes' => [static fn (): mixed => $commit()['attempted_changes'], 3],
    'commit not rolled back' => [static fn (): mixed => $commit()['rolled_back'], false],
    'commit rollback reason null' => [static fn (): mixed => $commit()['rollback_reason'], null],
    'commit rollback ordinal null' => [static fn (): mixed => $commit()['rollback_at_ordinal'], null],
    'commit savepoint not preserved' => [static fn (): mixed => $commit()['savepoint_preserved'], false],
    'commit rows preserve row count' => [static fn (): mixed => count($commit()['rows']), 4],
    'commit row names unchanged' => [static fn (): mixed => array_column($commit()['rows'], 'option_name'), ['siteurl', 'home', 'skip_plugin', 'bad_plugin']],
    'commit row values updated except ignored' => [static fn (): mixed => array_column($commit()['rows'], 'option_value'), ['imported:siteurl', 'imported:home', 'skip', 'imported:bad_plugin']],
    'commit autoload before trigger tags first rows' => [static fn (): mixed => array_column($commit()['rows'], 'autoload'), ['imported', 'imported', 'no', 'no']],
    'commit revisions advance changed rows' => [static fn (): mixed => array_column($commit()['rows'], 'revision'), [2, 2, 2, 4]],
    'commit current rows mirror rows' => [static fn (): mixed => $commit()['current_rows'], $commit()['rows']],
    'commit attempted rows mirror rows' => [static fn (): mixed => $commit()['attempted_rows'], $commit()['rows']],
    'commit yield stream changed row count' => [static fn (): mixed => count($commit()['yield_stream']), 3],
    'commit yield ordinals skip ignored row ordinal' => [static fn (): mixed => array_column($commit()['yield_stream'], 'ordinal'), [0, 1, 3]],
    'commit yield row indexes' => [static fn (): mixed => array_column($commit()['yield_stream'], 'row_index'), [0, 1, 3]],
    'commit yield statuses changed' => [static fn (): mixed => array_column($commit()['yield_stream'], 'status'), ['changed', 'changed', 'changed']],
    'commit yield changed flags' => [static fn (): mixed => array_column($commit()['yield_stream'], 'changed'), [true, true, true]],
    'commit first current row is old siteurl' => [static fn (): mixed => $commit()['yield_stream'][0]['current_row']['option_value'], 'https://old.test'],
    'commit first next row is imported siteurl' => [static fn (): mixed => $commit()['yield_stream'][0]['next_row']['option_value'], 'imported:siteurl'],
    'commit ignored row recorded once' => [static fn (): mixed => count($commit()['skipped']), 1],
    'commit ignored status skipped' => [static fn (): mixed => $commit()['skipped'][0]['status'], 'skipped'],
    'commit ignored timing before' => [static fn (): mixed => $commit()['skipped'][0]['timing'], 'before'],
    'commit ignored reason' => [static fn (): mixed => $commit()['skipped'][0]['reason'], 'skip plugin-owned option'],
    'commit ignored next row stays current before trigger' => [static fn (): mixed => $commit()['skipped'][0]['next_row']['option_value'], 'skip'],
    'commit ignored returning null' => [static fn (): mixed => $commit()['skipped'][0]['returning'], null],
    'commit returning row count' => [static fn (): mixed => count($commit()['returning_rows']), 3],
    'commit returning names' => [static fn (): mixed => array_column($commit()['returning_rows'], 'option_name'), ['siteurl', 'home', 'bad_plugin']],
    'commit returning old values' => [static fn (): mixed => array_column($commit()['returning_rows'], 'old_value'), ['https://old.test', 'https://home.test', 'bad']],
    'commit returning new values' => [static fn (): mixed => array_column($commit()['returning_rows'], 'new_value'), ['imported:siteurl', 'imported:home', 'imported:bad_plugin']],
    'commit returning next revisions' => [static fn (): mixed => array_column($commit()['returning_rows'], 'next_revision'), [2, 2, 4]],
    'commit callable returning labels' => [static fn (): mixed => array_column($commit()['returning_rows'], 'expr4'), ['0:changed:siteurl>imported:siteurl', '1:changed:home>imported:home', '3:changed:bad_plugin>imported:bad_plugin']],
    'commit yield returning mirrors returning rows' => [static fn (): mixed => array_column(array_column($commit()['yield_stream'], 'returning'), 'new_value'), ['imported:siteurl', 'imported:home', 'imported:bad_plugin']],
    'commit trigger effects include before and after' => [static fn (): mixed => array_column($commit()['trigger_effects'], 'trigger'), ['wp_options_bu_mark_autoload', 'wp_options_au_audit', 'wp_options_bu_mark_autoload', 'wp_options_au_audit', 'wp_options_au_audit']],
    'commit trigger effect timings' => [static fn (): mixed => array_column($commit()['trigger_effects'], 'timing'), ['before', 'after', 'before', 'after', 'after']],
    'commit trigger effect ordinals' => [static fn (): mixed => array_column($commit()['trigger_effects'], 'ordinal'), [0, 0, 1, 1, 3]],
    'commit trigger effect projection names' => [static fn (): mixed => array_column(array_column($commit()['trigger_effects'], 'row'), 'name'), ['siteurl', 'siteurl', 'home', 'home', 'bad_plugin']],
    'commit discarded empty' => [static fn (): mixed => $commit()['discarded'], []],
    'commit dependencies include trigger returning savepoint marker' => [static fn (): mixed => in_array('sqlite-trigger-returning-savepoint', $commit()['dependencies'], true), true],
    'commit dependencies include ignore marker' => [static fn (): mixed => in_array('sqlite-trigger-raise-ignore-yield', $commit()['dependencies'], true), true],
    'commit dependencies include rollback marker' => [static fn (): mixed => in_array('sqlite-savepoint-rollback-yield-suppression', $commit()['dependencies'], true), true],

    'rollback status rolled back' => [static fn (): mixed => $rollback()['status'], 'rolled-back'],
    'rollback restores rows' => [static fn (): mixed => $rollback()['rows'], $rows],
    'rollback current rows equal savepoint rows' => [static fn (): mixed => $rollback()['current_rows'], $rows],
    'rollback preserves attempted mutation image' => [static fn (): mixed => array_column($rollback()['attempted_rows'], 'option_value'), ['imported:siteurl', 'imported:home', 'skip', 'imported:bad_plugin']],
    'rollback returning rows suppressed' => [static fn (): mixed => $rollback()['returning_rows'], []],
    'rollback keeps diagnostic yields before rollback' => [static fn (): mixed => array_column($rollback()['yield_stream'], 'ordinal'), [0, 1, 3]],
    'rollback last diagnostic status' => [static fn (): mixed => $rollback()['yield_stream'][2]['status'], 'changed-before-trigger-rollback'],
    'rollback last diagnostic returning retained' => [static fn (): mixed => $rollback()['yield_stream'][2]['returning']['new_value'], 'imported:bad_plugin'],
    'rollback changes reset' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback attempted changes retained' => [static fn (): mixed => $rollback()['attempted_changes'], 3],
    'rollback marks rolled back true' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback reason retained' => [static fn (): mixed => $rollback()['rollback_reason'], 'bad plugin option aborts import'],
    'rollback ordinal retained' => [static fn (): mixed => $rollback()['rollback_at_ordinal'], 3],
    'rollback savepoint preserved true' => [static fn (): mixed => $rollback()['savepoint_preserved'], true],
    'rollback discarded row indexes' => [static fn (): mixed => array_column($rollback()['discarded'], 'row_index'), [0, 1, 3]],
    'rollback discarded savepoint rows available' => [static fn (): mixed => array_column(array_column($rollback()['discarded'], 'savepoint_row'), 'option_value'), ['https://old.test', 'https://home.test', 'bad']],
    'rollback skipped row still recorded' => [static fn (): mixed => $rollback()['skipped'][0]['row_index'], 2],
    'rollback final trigger effect is savepoint rollback' => [static fn (): mixed => $rollback()['trigger_effects'][array_key_last($rollback()['trigger_effects'])]['action'], 'rollback-to-savepoint'],
    'rollback final trigger effect discarded count' => [static fn (): mixed => $rollback()['trigger_effects'][array_key_last($rollback()['trigger_effects'])]['discarded_count'], 3],
    'rollback final trigger effect reason' => [static fn (): mixed => $rollback()['trigger_effects'][array_key_last($rollback()['trigger_effects'])]['reason'], 'bad plugin option aborts import'],

    'star projection returns complete next row' => [static fn (): mixed => $star()['returning_rows'][0]['*']['option_value'], 'star'],
    'bad savepoint throws' => [static fn (): mixed => SQLiteTriggerReturningSavepointPlan::updateRows('bad-name', $rows, $assignments, static fn (): bool => true), InvalidArgumentException::class],
    'missing assignments throws' => [static fn (): mixed => SQLiteTriggerReturningSavepointPlan::updateRows('ok_name', $rows, [], static fn (): bool => true), InvalidArgumentException::class],
    'bad trigger raise throws' => [static fn (): mixed => SQLiteTriggerReturningSavepointPlan::updateRows('ok_name', $rows, $assignments, static fn (): bool => true, [['timing' => 'before', 'event' => 'update', 'action' => 'raise', 'raise' => 'abort']]), InvalidArgumentException::class],
    'bad returning alias throws' => [static fn (): mixed => SQLiteTriggerReturningSavepointPlan::updateRows('ok_name', $rows, $assignments, static fn (): bool => true, [], [['expr' => 'new.option_name', 'as' => 'bad-alias']]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger returning savepoint ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

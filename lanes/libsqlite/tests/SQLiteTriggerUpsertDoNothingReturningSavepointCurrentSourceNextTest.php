<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan;

$rows142 = [
    ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1],
    ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'public_url', 'key_value' => 'https://public_url.test', 'load_policy' => 'yes', 'revision' => 1],
];
$currentIncoming142 = [
    ['setting_id' => 11, 'tenant_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://duplicate.test', 'load_policy' => 'yes', 'revision' => 0],
    ['setting_id' => 12, 'tenant_id' => 1, 'key_name' => 'module_seed', 'key_value' => 'seed', 'load_policy' => 'no', 'revision' => 0],
    ['setting_id' => 13, 'tenant_id' => 1, 'key_name' => 'public_url', 'key_value' => 'https://duplicate-public_url.test', 'load_policy' => 'yes', 'revision' => 0],
];
$nextIncoming142 = [
    ['setting_id' => 21, 'tenant_id' => 1, 'key_name' => 'module_seed', 'key_value' => 'seed-next', 'load_policy' => 'no', 'revision' => 0],
    ['setting_id' => 22, 'tenant_id' => 1, 'key_name' => 'theme_variant', 'key_value' => 'theme', 'load_policy' => 'yes', 'revision' => 0],
];
$triggers142 = [
    [
        'name' => 'app_settings_bi_slug',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'when' => ['new.load_policy', '=', 'no'],
        'set' => ['key_value' => 'concat:new.key_value:-prepared'],
        'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
    ],
    [
        'name' => 'app_settings_bi_audit',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['name' => 'new.key_name', 'load_policy' => 'new.load_policy'],
    ],
    [
        'name' => 'app_settings_ai_audit',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
    ],
];
$returning142 = [
    ['expr' => 'new.setting_id', 'as' => 'id'],
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    static fn (array $new, ?array $old, string $event, int $ordinal): string => $event . ':' . $ordinal . ':' . $new['key_name'],
];

$plan142 = static function (array $options = [], array $current = null, array $next = null) use ($rows142, $currentIncoming142, $nextIncoming142, $triggers142, $returning142): array {
    return SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan::execute(
        $rows142,
        $current ?? $currentIncoming142,
        $next ?? $nextIncoming142,
        ['tenant_id', 'key_name'],
        $triggers142,
        $returning142,
        $options + [
            'savepoint' => 'app_import_do_nothing_142',
            'current_source' => 'app-settings-current142',
            'next_source' => 'app-settings-next142',
        ],
    );
};

$released142 = static fn (): array => $plan142();
$rolled142 = static fn (): array => $plan142(['rollback_current' => true]);

$cases142 = [
    'released status' => [static fn (): mixed => $released142()['status'], 'trigger-upsert-do-nothing-returning-current-source-next142-released'],
    'released savepoint' => [static fn (): mixed => $released142()['savepoint'], 'app_import_do_nothing_142'],
    'released current source' => [static fn (): mixed => $released142()['current_source'], 'app-settings-current142'],
    'released next source' => [static fn (): mixed => $released142()['next_source'], 'app-settings-next142'],
    'released rollback flag' => [static fn (): mixed => $released142()['rollback_current'], false],
    'released current returning names' => [static fn (): mixed => array_column($released142()['current_returning_rows'], 'name'), ['module_seed']],
    'released next returning names' => [static fn (): mixed => array_column($released142()['next_returning_rows'], 'name'), ['theme_variant']],
    'released combined returning names' => [static fn (): mixed => array_column($released142()['returning_rows'], 'name'), ['module_seed', 'theme_variant']],
    'released current callable returning' => [static fn (): mixed => $released142()['current_returning_rows'][0]['expr3'], 'insert:1:module_seed'],
    'released next callable returning' => [static fn (): mixed => $released142()['next_returning_rows'][0]['expr3'], 'insert:1:theme_variant'],
    'released current value mutated before returning' => [static fn (): mixed => $released142()['current_returning_rows'][0]['value'], 'seed-prepared'],
    'released next seed skipped because current source inserted it' => [static fn (): mixed => array_column($released142()['next_skipped_conflicts'], 'conflict_key'), [['tenant_id' => 1, 'key_name' => 'module_seed']]],
    'released next rows include current module' => [static fn (): mixed => array_column($released142()['next_rows'], 'key_name'), ['base_url', 'public_url', 'module_seed', 'theme_variant']],
    'released next module value remains current source' => [static fn (): mixed => $released142()['next_rows'][2]['key_value'], 'seed-prepared'],
    'released skipped current conflict names' => [static fn (): mixed => array_column(array_column($released142()['current_skipped_conflicts'], 'incoming'), 'key_name'), ['base_url', 'public_url']],
    'released skipped current conflict indexes' => [static fn (): mixed => array_column($released142()['current_skipped_conflicts'], 'conflict_index'), [0, 1]],
    'released skipped conflict existing names' => [static fn (): mixed => array_column(array_column($released142()['current_skipped_conflicts'], 'existing'), 'key_name'), ['base_url', 'public_url']],
    'released current changes counts inserts only' => [static fn (): mixed => $released142()['current_changes'], 1],
    'released attempted current changes' => [static fn (): mixed => $released142()['attempted_current_changes'], 1],
    'released next changes' => [static fn (): mixed => $released142()['next_changes'], 1],
    'released committed changes' => [static fn (): mixed => $released142()['committed_changes'], 2],
    'released transition next starts from current source' => [static fn (): mixed => $released142()['source_transition']['next_started_from'], 'current-source'],
    'released transition conflict action' => [static fn (): mixed => $released142()['source_transition']['conflict_action'], 'do-nothing'],
    'released transition returning suppressed' => [static fn (): mixed => $released142()['source_transition']['returning_for_conflicts'], 'suppressed'],
    'released current before triggers fire for conflicts and insert' => [static fn (): mixed => array_column($released142()['current_trigger_effects'], 'timing'), ['before', 'before', 'before', 'after', 'before']],
    'released current trigger names include skipped conflicts' => [static fn (): mixed => array_column($released142()['current_trigger_effects'], 'trigger'), ['app_settings_bi_audit', 'app_settings_bi_slug', 'app_settings_bi_audit', 'app_settings_ai_audit', 'app_settings_bi_audit']],
    'released after trigger only for inserted current row' => [static fn (): mixed => array_column(array_filter($released142()['current_trigger_effects'], static fn (array $effect): bool => $effect['timing'] === 'after'), 'ordinal'), [1]],
    'released trigger mutation visible in skipped incoming' => [static fn (): mixed => $released142()['next_skipped_conflicts'][0]['incoming']['key_value'], 'seed-next-prepared'],
    'released dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-upsert-do-nothing-returning-savepoint-current-source-next142', $released142()['dependencies'], true), true],
    'released before trigger dependency marker' => [static fn (): mixed => in_array('sqlite-before-insert-trigger-fires-before-conflict-check', $released142()['dependencies'], true), true],

    'rolled status' => [static fn (): mixed => $rolled142()['status'], 'trigger-upsert-do-nothing-returning-current-source-next142-rolled-back'],
    'rolled current returning suppressed' => [static fn (): mixed => $rolled142()['current_returning_rows'], []],
    'rolled attempted current returning retained' => [static fn (): mixed => array_column($rolled142()['attempted_current_returning_rows'], 'name'), ['module_seed']],
    'rolled next returning names' => [static fn (): mixed => array_column($rolled142()['next_returning_rows'], 'name'), ['module_seed', 'theme_variant']],
    'rolled combined returning names' => [static fn (): mixed => array_column($rolled142()['returning_rows'], 'name'), ['module_seed', 'theme_variant']],
    'rolled next starts from savepoint rows' => [static fn (): mixed => array_column($rolled142()['next_start_rows'], 'key_name'), ['base_url', 'public_url']],
    'rolled next rows include retried module' => [static fn (): mixed => array_column($rolled142()['next_rows'], 'key_name'), ['base_url', 'public_url', 'module_seed', 'theme_variant']],
    'rolled retried module takes next value' => [static fn (): mixed => $rolled142()['next_rows'][2]['key_value'], 'seed-next-prepared'],
    'rolled current changes reset' => [static fn (): mixed => $rolled142()['current_changes'], 0],
    'rolled attempted current changes' => [static fn (): mixed => $rolled142()['attempted_current_changes'], 1],
    'rolled next changes' => [static fn (): mixed => $rolled142()['next_changes'], 2],
    'rolled committed changes' => [static fn (): mixed => $rolled142()['committed_changes'], 2],
    'rolled transition next starts from savepoint' => [static fn (): mixed => $rolled142()['source_transition']['next_started_from'], 'savepoint'],
    'rolled current statement retains attempted insert' => [static fn (): mixed => array_column($rolled142()['current_statement_rows'], 'key_name'), ['base_url', 'public_url', 'module_seed']],
    'rolled trigger effects include current and next sources' => [static fn (): mixed => array_values(array_unique(array_column($rolled142()['trigger_effects'], 'source'))), ['app-settings-current142', 'app-settings-next142']],
    'rolled next skipped conflict names' => [static fn (): mixed => array_column(array_column($rolled142()['next_skipped_conflicts'], 'incoming'), 'key_name'), []],

    'null conflict key does not conflict' => [static fn (): mixed => array_column($plan142([], [['setting_id' => 31, 'tenant_id' => 1, 'key_name' => null, 'key_value' => 'a', 'load_policy' => 'yes', 'revision' => 0]], [['setting_id' => 32, 'tenant_id' => 1, 'key_name' => null, 'key_value' => 'b', 'load_policy' => 'yes', 'revision' => 0]])['next_rows'], 'setting_id'), [1, 2, 31, 32]],
    'bad savepoint throws' => [static fn (): mixed => $plan142(['savepoint' => 'bad name']), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => $plan142(['next_source' => 'bad next']), InvalidArgumentException::class],
    'missing conflict column throws' => [static fn (): mixed => $plan142([], [['setting_id' => 40, 'tenant_id' => 1, 'key_value' => 'missing', 'load_policy' => 'yes']], []), InvalidArgumentException::class],
    'old returning throws for insert' => [static fn (): mixed => SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan::execute($rows142, [['setting_id' => 50, 'tenant_id' => 1, 'key_name' => 'new_old', 'key_value' => 'x', 'load_policy' => 'no']], [], ['tenant_id', 'key_name'], [], ['old.key_name']), InvalidArgumentException::class],
    'unsupported trigger action throws' => [static fn (): mixed => SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan::execute($rows142, [['setting_id' => 60, 'tenant_id' => 1, 'key_name' => 'bad_trigger', 'key_value' => 'x', 'load_policy' => 'no']], [], ['tenant_id', 'key_name'], [['timing' => 'before', 'event' => 'insert', 'action' => 'raise']], ['new.key_name']), InvalidArgumentException::class],
];

foreach ($cases142 as $name => [$callback, $expected]) {
    $tests['trigger upsert do nothing returning savepoint current source next142 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

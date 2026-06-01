<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningFkSavepointCurrentNextPlan;

$tests = [];

$parents = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1],
    ['setting_id' => 2, 'key_name' => 'public_url', 'key_value' => 'https://public_url.test', 'load_policy' => 'yes', 'revision' => 2],
    ['setting_id' => 3, 'key_name' => 'module_guard', 'key_value' => 'blocked', 'load_policy' => 'no', 'revision' => 3],
    ['setting_id' => 4, 'key_name' => 'skip_module', 'key_value' => 'skip', 'load_policy' => 'no', 'revision' => 4],
];
$children = [
    ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'owner', 'meta_value' => 'core'],
    ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'owner', 'meta_value' => 'core'],
    ['meta_id' => 12, 'setting_id' => 3, 'meta_key' => 'owner', 'meta_value' => 'module'],
    ['meta_id' => 13, 'setting_id' => 4, 'meta_key' => 'owner', 'meta_value' => 'module'],
];
$assignments = [
    'setting_id' => static fn (array $old): int => (int) $old['setting_id'] + 100,
    'key_value' => static fn (array $old): string => $old['key_name'] . ':next',
    'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
];
$returning = [
    'setting_id',
    'key_name',
    ['expr' => 'old.setting_id', 'as' => 'old_setting_id'],
    ['expr' => 'new.revision', 'as' => 'next_revision'],
    static fn (array $row, array $old, string $event): string => $event . ':' . $old['key_name'] . '=>' . $row['setting_id'],
];
$triggers = [
    [
        'name' => 'app_settings_bu_url_prefix',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.load_policy', '=', 'yes'],
        'set' => ['key_value' => 'concat:migrated::new.key_name'],
        'values' => ['old_key' => 'old.setting_id', 'new_key' => 'new.setting_id', 'value' => 'new.key_value'],
    ],
    [
        'name' => 'app_settings_bu_skip_module',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'raise',
        'when' => ['new.key_name', '=', 'skip_module'],
        'raise' => 'ignore',
        'reason' => 'module-row-ignored',
        'values' => ['name' => 'new.key_name'],
    ],
    [
        'name' => 'app_settings_au_block_module',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'raise',
        'when' => ['new.key_name', '=', 'module_guard'],
        'raise' => 'rollback',
        'reason' => 'module-trigger-rollback',
        'values' => ['name' => 'new.key_name'],
    ],
];

$cascade = static fn (): array => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update(
    $parents,
    $children,
    $assignments,
    static fn (array $row): bool => $row['load_policy'] === 'yes',
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'cascade'],
    $triggers,
    $returning,
    ['savepoint' => 'app_import_stmt'],
);
$deferred = static fn (): array => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update(
    $parents,
    $children,
    ['setting_id' => static fn (array $old): int => (int) $old['setting_id'] + 500],
    static fn (array $row): bool => $row['setting_id'] === 3,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action', 'deferred' => true],
    [],
    ['setting_id', ['expr' => 'old.setting_id', 'as' => 'old_setting_id']],
    ['conflict_action' => 'keep-deferred'],
);
$immediate = static fn (): array => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update(
    $parents,
    $children,
    ['setting_id' => static fn (array $old): int => (int) $old['setting_id'] + 700],
    static fn (array $row): bool => $row['setting_id'] === 1,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'no action'],
    [],
    ['setting_id', ['expr' => 'old.setting_id', 'as' => 'old_setting_id']],
);
$setNull = static fn (): array => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update(
    $parents,
    $children,
    ['setting_id' => static fn (array $old): int => (int) $old['setting_id'] + 800],
    static fn (array $row): bool => $row['setting_id'] === 2,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'set null'],
    [],
    ['*'],
);
$triggerRollback = static fn (): array => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update(
    $parents,
    $children,
    $assignments,
    static fn (array $row): bool => $row['setting_id'] >= 3,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'cascade'],
    $triggers,
    $returning,
);

$cases = [
    'cascade savepoint name is preserved' => [static fn (): mixed => $cascade()['savepoint'], 'app_import_stmt'],
    'cascade status releases savepoint' => [static fn (): mixed => $cascade()['status'], 'released'],
    'cascade changes two parent rows' => [static fn (): mixed => $cascade()['changes'], 2],
    'cascade attempted changes match changed rows' => [static fn (): mixed => $cascade()['attempted_changes'], 2],
    'cascade next parent keys are rewritten' => [static fn (): mixed => array_column($cascade()['next_parent'], 'setting_id'), [101, 102, 3, 4]],
    'cascade next child keys follow parent keys' => [static fn (): mixed => array_column($cascade()['next_child'], 'setting_id'), [101, 102, 3, 4]],
    'cascade current parent remains original' => [static fn (): mixed => array_column($cascade()['current_parent'], 'setting_id'), [1, 2, 3, 4]],
    'cascade current child remains original' => [static fn (): mixed => array_column($cascade()['current_child'], 'setting_id'), [1, 2, 3, 4]],
    'cascade attempt parent is next parent' => [static fn (): mixed => $cascade()['attempt_parent'], $cascade()['next_parent']],
    'cascade before trigger rewrites first value' => [static fn (): mixed => $cascade()['next_parent'][0]['key_value'], 'migratedbase_url'],
    'cascade before trigger rewrites second value' => [static fn (): mixed => $cascade()['next_parent'][1]['key_value'], 'migratedpublic_url'],
    'cascade yielded names in statement order' => [static fn (): mixed => array_column($cascade()['yielded'], 'key_name'), ['base_url', 'public_url']],
    'cascade yielded statuses changed' => [static fn (): mixed => array_column($cascade()['yielded'], 'status'), ['changed', 'changed']],
    'cascade returning rows include current keys' => [static fn (): mixed => array_column($cascade()['returning_rows'], 'setting_id'), [101, 102]],
    'cascade returning rows include old keys' => [static fn (): mixed => array_column($cascade()['returning_rows'], 'old_setting_id'), [1, 2]],
    'cascade returning computed label' => [static fn (): mixed => $cascade()['returning_rows'][0]['expr4'], 'update:base_url=>101'],
    'cascade current returning equals committed rows' => [static fn (): mixed => $cascade()['current_returning_rows'], $cascade()['returning_rows']],
    'cascade records two foreign key actions' => [static fn (): mixed => count($cascade()['foreign_key_actions']), 2],
    'cascade action names' => [static fn (): mixed => array_column($cascade()['foreign_key_actions'], 'action'), ['cascade', 'cascade']],
    'cascade action phases are statement' => [static fn (): mixed => array_column($cascade()['foreign_key_actions'], 'phase'), ['statement', 'statement']],
    'cascade action row counts' => [static fn (): mixed => array_column($cascade()['foreign_key_actions'], 'rows'), [1, 1]],
    'cascade first action from and to' => [static fn (): mixed => [$cascade()['foreign_key_actions'][0]['from'], $cascade()['foreign_key_actions'][0]['to']], [1, 101]],
    'cascade has no violations' => [static fn (): mixed => $cascade()['foreign_key_violations'], []],
    'cascade trigger effects record before triggers' => [static fn (): mixed => array_column($cascade()['trigger_effects'], 'trigger'), ['app_settings_bu_url_prefix', 'app_settings_bu_url_prefix']],
    'cascade trigger effect sees new key' => [static fn (): mixed => $cascade()['trigger_effects'][0]['row']['new_key'], 101],
    'cascade trigger effect sees rewritten value' => [static fn (): mixed => $cascade()['trigger_effects'][1]['row']['value'], 'migratedpublic_url'],
    'cascade discarded parent lists changed images' => [static fn (): mixed => array_column($cascade()['discarded_parent'], 'setting_id'), [101, 102]],
    'cascade discarded child lists cascaded images' => [static fn (): mixed => array_column($cascade()['discarded_child'], 'setting_id'), [101, 102]],
    'cascade savepoint not preserved' => [static fn (): mixed => $cascade()['savepoint_preserved'], false],
    'cascade dependency marker present' => [static fn (): mixed => in_array('sqlite-trigger-returning-fk-savepoint-current-next74', $cascade()['dependencies'], true), true],

    'deferred no action status reports deferred violation' => [static fn (): mixed => $deferred()['status'], 'deferred-violation'],
    'deferred no action keeps changed parent' => [static fn (): mixed => $deferred()['next_parent'][2]['setting_id'], 503],
    'deferred no action child remains old key' => [static fn (): mixed => $deferred()['next_child'][2]['setting_id'], 3],
    'deferred no action returns changed row' => [static fn (): mixed => $deferred()['returning_rows'][0]['setting_id'], 503],
    'deferred no action records no action' => [static fn (): mixed => $deferred()['foreign_key_actions'][0]['action'], 'no-action'],
    'deferred no action statement violation is deferred' => [static fn (): mixed => $deferred()['foreign_key_violations'][0]['deferred'], true],
    'deferred no action release violation phase recorded' => [static fn (): mixed => $deferred()['foreign_key_violations'][1]['phase'], 'savepoint-release'],
    'deferred no action savepoint not preserved' => [static fn (): mixed => $deferred()['savepoint_preserved'], false],

    'immediate no action rolls back savepoint' => [static fn (): mixed => $immediate()['status'], 'rolled-back'],
    'immediate no action suppresses returning rows' => [static fn (): mixed => $immediate()['returning_rows'], []],
    'immediate no action keeps attempted returning diagnostics' => [static fn (): mixed => $immediate()['current_returning_rows'][0]['setting_id'], 701],
    'immediate no action restores parent keys' => [static fn (): mixed => array_column($immediate()['next_parent'], 'setting_id'), [1, 2, 3, 4]],
    'immediate no action restores child keys' => [static fn (): mixed => array_column($immediate()['next_child'], 'setting_id'), [1, 2, 3, 4]],
    'immediate no action rollback reason is fk' => [static fn (): mixed => $immediate()['rollback_reason'], 'foreign-key-constraint'],
    'immediate no action savepoint preserved' => [static fn (): mixed => $immediate()['savepoint_preserved'], true],
    'immediate no action attempted changes recorded' => [static fn (): mixed => $immediate()['attempted_changes'], 1],

    'set null status releases' => [static fn (): mixed => $setNull()['status'], 'released'],
    'set null child key becomes null' => [static fn (): mixed => $setNull()['next_child'][1]['setting_id'], null],
    'set null action is recorded' => [static fn (): mixed => $setNull()['foreign_key_actions'][0]['action'], 'set-null'],
    'set null returning star has public_url' => [static fn (): mixed => $setNull()['returning_rows'][0]['*']['key_name'], 'public_url'],

    'trigger rollback rolls back after module guard' => [static fn (): mixed => $triggerRollback()['status'], 'rolled-back'],
    'trigger rollback reason is trigger reason' => [static fn (): mixed => $triggerRollback()['rollback_reason'], 'module-trigger-rollback'],
    'trigger rollback suppresses committed returning rows' => [static fn (): mixed => $triggerRollback()['returning_rows'], []],
    'trigger rollback retains current attempted returning row' => [static fn (): mixed => $triggerRollback()['current_returning_rows'][0]['key_name'], 'module_guard'],
    'trigger rollback restores parent rows' => [static fn (): mixed => array_column($triggerRollback()['next_parent'], 'setting_id'), [1, 2, 3, 4]],
    'trigger rollback restores child rows' => [static fn (): mixed => array_column($triggerRollback()['next_child'], 'setting_id'), [1, 2, 3, 4]],

    'bad savepoint rejected' => [static fn (): mixed => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update($parents, $children, $assignments, static fn (): bool => true, ['parent_key' => 'setting_id', 'child_key' => 'setting_id'], [], ['*'], ['savepoint' => 'bad-name']), InvalidArgumentException::class],
    'empty assignments rejected' => [static fn (): mixed => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update($parents, $children, [], static fn (): bool => true, ['parent_key' => 'setting_id', 'child_key' => 'setting_id']), InvalidArgumentException::class],
    'bad fk action rejected' => [static fn (): mixed => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update($parents, $children, $assignments, static fn (): bool => true, ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'restrict-now']), InvalidArgumentException::class],
    'bad trigger action rejected' => [static fn (): mixed => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update($parents, $children, $assignments, static fn (): bool => true, ['parent_key' => 'setting_id', 'child_key' => 'setting_id'], [['timing' => 'before', 'event' => 'update', 'action' => 'delete-row']]), InvalidArgumentException::class],
    'bad returning column rejected' => [static fn (): mixed => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update($parents, $children, $assignments, static fn (array $row): bool => $row['setting_id'] === 1, ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'cascade'], [], ['missing']), InvalidArgumentException::class],
    'bad conflict action rejected' => [static fn (): mixed => SQLiteTriggerReturningFkSavepointCurrentNextPlan::update($parents, $children, $assignments, static fn (): bool => true, ['parent_key' => 'setting_id', 'child_key' => 'setting_id'], [], ['*'], ['conflict_action' => 'replace']), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger returning fk savepoint current next74 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

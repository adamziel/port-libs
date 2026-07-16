<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan;

$rows137 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'parent_setting_id' => 10, 'revision' => 1],
    ['setting_id' => 2, 'key_name' => 'dashboard_url', 'key_value' => 'https://dashboard_url.test', 'load_policy' => 'yes', 'parent_setting_id' => 10, 'revision' => 1],
];
$parents137 = [
    ['parent_id' => 10, 'name' => 'core'],
    ['parent_id' => 20, 'name' => 'regional'],
];
$assignments137 = [
    'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
    'parent_setting_id' => static fn (array $old, array $incoming): mixed => $incoming['parent_setting_id'],
    'revision' => static fn (array $old, array $incoming): mixed => $old['revision'] + 1,
];
$triggers137 = [
    [
        'name' => 'app_settings_bu_base_url_suffix',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.key_name', '=', 'base_url'],
        'set' => ['key_value' => 'concat:new.key_value:/app'],
        'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
    ],
];
$returning137 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.setting_id', 'as' => 'id'],
    ['expr' => 'old_or_null.setting_id', 'as' => 'old_id'],
    ['expr' => 'new.parent_setting_id', 'as' => 'parent_id'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $ordinal): string => $event . ':' . $ordinal . ':' . $new['key_name'],
];
$fk137 = [
    'child_key' => 'parent_setting_id',
    'parent_key' => 'parent_id',
    'child_table' => 'app_settings',
    'parent_table' => 'app_setting_groups',
    'deferred' => true,
];

$plan137 = static function (array $current = null, array $next = null, array $parents = null, array $options = []) use ($rows137, $assignments137, $triggers137, $returning137, $parents137, $fk137): array {
    return SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan::execute(
        $rows137,
        $current ?? [
            ['setting_id' => 11, 'key_name' => 'base_url', 'key_value' => 'https://broken.test', 'load_policy' => 'yes', 'parent_setting_id' => 99, 'revision' => 0],
            ['setting_id' => 12, 'key_name' => 'fresh_bad', 'key_value' => 'bad', 'load_policy' => 'no', 'parent_setting_id' => 98, 'revision' => 0],
        ],
        $next ?? [
            ['setting_id' => 21, 'key_name' => 'base_url', 'key_value' => 'https://retry.test', 'load_policy' => 'yes', 'parent_setting_id' => 20, 'revision' => 0],
            ['setting_id' => 22, 'key_name' => 'fresh_good', 'key_value' => 'ok', 'load_policy' => 'no', 'parent_setting_id' => 10, 'revision' => 0],
        ],
        ['key_name'],
        $assignments137,
        $triggers137,
        $returning137,
        $parents ?? $parents137,
        $fk137,
        $options + [
            'savepoint' => 'app_import_deferred',
            'current_source' => 'app-settings-current',
            'next_source' => 'app-settings-next',
            'wal_frame' => 70,
        ],
    );
};

$blocked137 = static fn (): array => $plan137();
$released137 = static fn (): array => $plan137([
    ['setting_id' => 31, 'key_name' => 'base_url', 'key_value' => 'https://valid.test', 'load_policy' => 'yes', 'parent_setting_id' => 20, 'revision' => 0],
], [
    ['setting_id' => 32, 'key_name' => 'fresh_after_release', 'key_value' => 'after', 'load_policy' => 'no', 'parent_setting_id' => 10, 'revision' => 0],
]);
$notDeferred137 = static fn (): array => $plan137(null, null, null, ['rollback_on_deferred_violation' => true]) + [];

$cases137 = [
    'blocked status' => [static fn (): mixed => $blocked137()['status'], 'trigger-upsert-deferred-returning-current-source-next137-rolled-back'],
    'blocked savepoint' => [static fn (): mixed => $blocked137()['savepoint'], 'app_import_deferred'],
    'blocked current source' => [static fn (): mixed => $blocked137()['current_source'], 'app-settings-current'],
    'blocked next source' => [static fn (): mixed => $blocked137()['next_source'], 'app-settings-next'],
    'blocked deferred flag' => [static fn (): mixed => $blocked137()['deferred'], true],
    'blocked violation count' => [static fn (): mixed => $blocked137()['deferred_violation_count'], 2],
    'blocked violation values' => [static fn (): mixed => array_column($blocked137()['deferred_violations'], 'value'), [99, 98]],
    'blocked violation child table' => [static fn (): mixed => $blocked137()['deferred_violations'][0]['child_table'], 'app_settings'],
    'blocked violation parent table' => [static fn (): mixed => $blocked137()['deferred_violations'][0]['parent_table'], 'app_setting_groups'],
    'blocked violation rowid uses setting id' => [static fn (): mixed => $blocked137()['deferred_violations'][0]['rowid'], 11],
    'blocked commit flag' => [static fn (): mixed => $blocked137()['commit_blocked_after_returning'], true],
    'blocked current returning suppressed' => [static fn (): mixed => $blocked137()['current_returning_rows'], []],
    'blocked attempted current returning names' => [static fn (): mixed => array_column($blocked137()['attempted_current_returning_rows'], 'name'), ['base_url', 'fresh_bad']],
    'blocked attempted old id' => [static fn (): mixed => $blocked137()['attempted_current_returning_rows'][0]['old_id'], 1],
    'blocked attempted insert old id null' => [static fn (): mixed => $blocked137()['attempted_current_returning_rows'][1]['old_id'], null],
    'blocked attempted callable update' => [static fn (): mixed => $blocked137()['attempted_current_returning_rows'][0]['expr4'], 'update:0:base_url'],
    'blocked attempted callable insert' => [static fn (): mixed => $blocked137()['attempted_current_returning_rows'][1]['expr4'], 'insert:1:fresh_bad'],
    'blocked next names' => [static fn (): mixed => array_column($blocked137()['next_returning_rows'], 'name'), ['base_url', 'fresh_good']],
    'blocked returning rows are next only' => [static fn (): mixed => array_column($blocked137()['returning_rows'], 'name'), ['base_url', 'fresh_good']],
    'blocked next starts from savepoint rows' => [static fn (): mixed => array_column($blocked137()['next_start_rows'], 'key_name'), ['base_url', 'dashboard_url']],
    'blocked next rows include retry' => [static fn (): mixed => array_column($blocked137()['next_rows'], 'key_name'), ['base_url', 'dashboard_url', 'fresh_good']],
    'blocked next base_url value has trigger suffix' => [static fn (): mixed => $blocked137()['next_rows'][0]['key_value'], 'https://retry.test/app'],
    'blocked current statement kept bad row before release' => [static fn (): mixed => array_column($blocked137()['current_statement_rows'], 'key_name'), ['base_url', 'dashboard_url', 'fresh_bad']],
    'blocked current statement bad parent retained diagnostically' => [static fn (): mixed => $blocked137()['current_statement_rows'][0]['parent_setting_id'], 99],
    'blocked discarded names' => [static fn (): mixed => array_column($blocked137()['discarded_current_rows'], 'key_name'), ['base_url', 'fresh_bad']],
    'blocked current changes reset' => [static fn (): mixed => $blocked137()['current_changes'], 0],
    'blocked attempted current changes' => [static fn (): mixed => $blocked137()['attempted_current_changes'], 2],
    'blocked next changes' => [static fn (): mixed => $blocked137()['next_changes'], 2],
    'blocked committed changes' => [static fn (): mixed => $blocked137()['committed_changes'], 2],
    'blocked current stream not admitted' => [static fn (): mixed => array_column($blocked137()['suppressed_current_source_stream'], 'admitted'), [false, false]],
    'blocked attempted stream phases' => [static fn (): mixed => array_column($blocked137()['attempted_source_stream'], 'phase'), ['current', 'current']],
    'blocked next stream admitted' => [static fn (): mixed => array_column($blocked137()['next_source_stream'], 'admitted'), [true, true]],
    'blocked source barrier' => [static fn (): mixed => $blocked137()['source_transition']['barrier'], 'deferred-trigger-violation-rolls-back-current-source'],
    'blocked next source start token' => [static fn (): mixed => $blocked137()['source_transition']['next_started_from'], 'savepoint'],
    'blocked dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-upsert-deferred-returning-current-source-next137', $blocked137()['dependencies'], true), true],
    'blocked preserves next129 dependency' => [static fn (): mixed => in_array('sqlite-trigger-upsert-returning-savepoint-current-source-next129', $blocked137()['dependencies'], true), true],

    'released status' => [static fn (): mixed => $released137()['status'], 'trigger-upsert-deferred-returning-current-source-next137-released'],
    'released violation count' => [static fn (): mixed => $released137()['deferred_violation_count'], 0],
    'released current returning admitted' => [static fn (): mixed => array_column($released137()['current_returning_rows'], 'name'), ['base_url']],
    'released returning includes current and next' => [static fn (): mixed => array_column($released137()['returning_rows'], 'name'), ['base_url', 'fresh_after_release']],
    'released next starts from current source' => [static fn (): mixed => $released137()['source_transition']['next_started_from'], 'current-source'],
    'released current value visible to next' => [static fn (): mixed => $released137()['next_start_rows'][0]['key_value'], 'https://valid.test/app'],
    'released next rows retain current source' => [static fn (): mixed => $released137()['next_rows'][0]['key_value'], 'https://valid.test/app'],
    'released committed changes' => [static fn (): mixed => $released137()['committed_changes'], 2],
    'released discarded empty' => [static fn (): mixed => $released137()['discarded_current_rows'], []],
    'released suppressed stream empty' => [static fn (): mixed => $released137()['suppressed_current_source_stream'], []],
    'released source barrier' => [static fn (): mixed => $released137()['source_transition']['barrier'], 'deferred-trigger-check-admits-current-source'],

    'empty savepoint throws' => [static fn (): mixed => $plan137([], [], null, ['savepoint' => '']), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => $plan137([], [], null, ['current_source' => 'bad source']), InvalidArgumentException::class],
    'missing parent key throws' => [static fn (): mixed => $plan137(null, null, [['name' => 'missing']]), InvalidArgumentException::class],
    'missing child key throws' => [static fn (): mixed => $plan137([['setting_id' => 50, 'key_name' => 'bad_child', 'key_value' => 'x', 'load_policy' => 'no', 'revision' => 0]], []), InvalidArgumentException::class],
];

foreach ($cases137 as $name => [$callback, $expected]) {
    $tests['trigger upsert deferred returning current source next137 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

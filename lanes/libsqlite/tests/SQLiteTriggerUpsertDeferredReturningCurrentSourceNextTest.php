<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan;

$rows137 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'parent_option_id' => 10, 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'parent_option_id' => 10, 'revision' => 1],
];
$parents137 = [
    ['parent_id' => 10, 'name' => 'core'],
    ['parent_id' => 20, 'name' => 'network'],
];
$assignments137 = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'parent_option_id' => static fn (array $old, array $incoming): mixed => $incoming['parent_option_id'],
    'revision' => static fn (array $old, array $incoming): mixed => $old['revision'] + 1,
];
$triggers137 = [
    [
        'name' => 'wp_options_bu_siteurl_suffix',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.option_name', '=', 'siteurl'],
        'set' => ['option_value' => 'concat:new.option_value:/wp'],
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
];
$returning137 = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_id', 'as' => 'id'],
    ['expr' => 'old_or_null.option_id', 'as' => 'old_id'],
    ['expr' => 'new.parent_option_id', 'as' => 'parent_id'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $ordinal): string => $event . ':' . $ordinal . ':' . $new['option_name'],
];
$fk137 = [
    'child_key' => 'parent_option_id',
    'parent_key' => 'parent_id',
    'child_table' => 'wp_options',
    'parent_table' => 'wp_option_groups',
    'deferred' => true,
];

$plan137 = static function (array $current = null, array $next = null, array $parents = null, array $options = []) use ($rows137, $assignments137, $triggers137, $returning137, $parents137, $fk137): array {
    return SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan::execute(
        $rows137,
        $current ?? [
            ['option_id' => 11, 'option_name' => 'siteurl', 'option_value' => 'https://broken.test', 'autoload' => 'yes', 'parent_option_id' => 99, 'revision' => 0],
            ['option_id' => 12, 'option_name' => 'fresh_bad', 'option_value' => 'bad', 'autoload' => 'no', 'parent_option_id' => 98, 'revision' => 0],
        ],
        $next ?? [
            ['option_id' => 21, 'option_name' => 'siteurl', 'option_value' => 'https://retry.test', 'autoload' => 'yes', 'parent_option_id' => 20, 'revision' => 0],
            ['option_id' => 22, 'option_name' => 'fresh_good', 'option_value' => 'ok', 'autoload' => 'no', 'parent_option_id' => 10, 'revision' => 0],
        ],
        ['option_name'],
        $assignments137,
        $triggers137,
        $returning137,
        $parents ?? $parents137,
        $fk137,
        $options + [
            'savepoint' => 'wp_import_deferred',
            'current_source' => 'wp-options-current',
            'next_source' => 'wp-options-next',
            'wal_frame' => 70,
        ],
    );
};

$blocked137 = static fn (): array => $plan137();
$released137 = static fn (): array => $plan137([
    ['option_id' => 31, 'option_name' => 'siteurl', 'option_value' => 'https://valid.test', 'autoload' => 'yes', 'parent_option_id' => 20, 'revision' => 0],
], [
    ['option_id' => 32, 'option_name' => 'fresh_after_release', 'option_value' => 'after', 'autoload' => 'no', 'parent_option_id' => 10, 'revision' => 0],
]);
$notDeferred137 = static fn (): array => $plan137(null, null, null, ['rollback_on_deferred_violation' => true]) + [];

$cases137 = [
    'blocked status' => [static fn (): mixed => $blocked137()['status'], 'trigger-upsert-deferred-returning-current-source-next137-rolled-back'],
    'blocked savepoint' => [static fn (): mixed => $blocked137()['savepoint'], 'wp_import_deferred'],
    'blocked current source' => [static fn (): mixed => $blocked137()['current_source'], 'wp-options-current'],
    'blocked next source' => [static fn (): mixed => $blocked137()['next_source'], 'wp-options-next'],
    'blocked deferred flag' => [static fn (): mixed => $blocked137()['deferred'], true],
    'blocked violation count' => [static fn (): mixed => $blocked137()['deferred_violation_count'], 2],
    'blocked violation values' => [static fn (): mixed => array_column($blocked137()['deferred_violations'], 'value'), [99, 98]],
    'blocked violation child table' => [static fn (): mixed => $blocked137()['deferred_violations'][0]['child_table'], 'wp_options'],
    'blocked violation parent table' => [static fn (): mixed => $blocked137()['deferred_violations'][0]['parent_table'], 'wp_option_groups'],
    'blocked violation rowid uses option id' => [static fn (): mixed => $blocked137()['deferred_violations'][0]['rowid'], 11],
    'blocked commit flag' => [static fn (): mixed => $blocked137()['commit_blocked_after_returning'], true],
    'blocked current returning suppressed' => [static fn (): mixed => $blocked137()['current_returning_rows'], []],
    'blocked attempted current returning names' => [static fn (): mixed => array_column($blocked137()['attempted_current_returning_rows'], 'name'), ['siteurl', 'fresh_bad']],
    'blocked attempted old id' => [static fn (): mixed => $blocked137()['attempted_current_returning_rows'][0]['old_id'], 1],
    'blocked attempted insert old id null' => [static fn (): mixed => $blocked137()['attempted_current_returning_rows'][1]['old_id'], null],
    'blocked attempted callable update' => [static fn (): mixed => $blocked137()['attempted_current_returning_rows'][0]['expr4'], 'update:0:siteurl'],
    'blocked attempted callable insert' => [static fn (): mixed => $blocked137()['attempted_current_returning_rows'][1]['expr4'], 'insert:1:fresh_bad'],
    'blocked next names' => [static fn (): mixed => array_column($blocked137()['next_returning_rows'], 'name'), ['siteurl', 'fresh_good']],
    'blocked returning rows are next only' => [static fn (): mixed => array_column($blocked137()['returning_rows'], 'name'), ['siteurl', 'fresh_good']],
    'blocked next starts from savepoint rows' => [static fn (): mixed => array_column($blocked137()['next_start_rows'], 'option_name'), ['siteurl', 'home']],
    'blocked next rows include retry' => [static fn (): mixed => array_column($blocked137()['next_rows'], 'option_name'), ['siteurl', 'home', 'fresh_good']],
    'blocked next siteurl value has trigger suffix' => [static fn (): mixed => $blocked137()['next_rows'][0]['option_value'], 'https://retry.test/wp'],
    'blocked current statement kept bad row before release' => [static fn (): mixed => array_column($blocked137()['current_statement_rows'], 'option_name'), ['siteurl', 'home', 'fresh_bad']],
    'blocked current statement bad parent retained diagnostically' => [static fn (): mixed => $blocked137()['current_statement_rows'][0]['parent_option_id'], 99],
    'blocked discarded names' => [static fn (): mixed => array_column($blocked137()['discarded_current_rows'], 'option_name'), ['siteurl', 'fresh_bad']],
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
    'released current returning admitted' => [static fn (): mixed => array_column($released137()['current_returning_rows'], 'name'), ['siteurl']],
    'released returning includes current and next' => [static fn (): mixed => array_column($released137()['returning_rows'], 'name'), ['siteurl', 'fresh_after_release']],
    'released next starts from current source' => [static fn (): mixed => $released137()['source_transition']['next_started_from'], 'current-source'],
    'released current value visible to next' => [static fn (): mixed => $released137()['next_start_rows'][0]['option_value'], 'https://valid.test/wp'],
    'released next rows retain current source' => [static fn (): mixed => $released137()['next_rows'][0]['option_value'], 'https://valid.test/wp'],
    'released committed changes' => [static fn (): mixed => $released137()['committed_changes'], 2],
    'released discarded empty' => [static fn (): mixed => $released137()['discarded_current_rows'], []],
    'released suppressed stream empty' => [static fn (): mixed => $released137()['suppressed_current_source_stream'], []],
    'released source barrier' => [static fn (): mixed => $released137()['source_transition']['barrier'], 'deferred-trigger-check-admits-current-source'],

    'empty savepoint throws' => [static fn (): mixed => $plan137([], [], null, ['savepoint' => '']), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => $plan137([], [], null, ['current_source' => 'bad source']), InvalidArgumentException::class],
    'missing parent key throws' => [static fn (): mixed => $plan137(null, null, [['name' => 'missing']]), InvalidArgumentException::class],
    'missing child key throws' => [static fn (): mixed => $plan137([['option_id' => 50, 'option_name' => 'bad_child', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 0]], []), InvalidArgumentException::class],
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

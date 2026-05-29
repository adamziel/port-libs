<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan;

$base146 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 1],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'revision' => 1],
];
$mapping146 = [
    'import_id' => 'option_id',
    'blog' => 'blog_id',
    'name' => 'option_name',
    'value' => 'option_value',
    'autoload_flag' => 'autoload',
    'rev' => 'revision',
];
$currentRows146 = [
    ['import_id' => 101, 'blog' => 1, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'rev' => 2],
    ['import_id' => 102, 'blog' => 1, 'name' => 'plugin_seed', 'value' => 'seed', 'autoload_flag' => 'no', 'rev' => 1],
];
$nextRows146 = [
    ['import_id' => 201, 'blog' => 1, 'name' => 'plugin_seed', 'value' => 'next-seed', 'autoload_flag' => 'no', 'rev' => 3],
    ['import_id' => 202, 'blog' => 1, 'name' => 'theme_mods', 'value' => 'theme', 'autoload_flag' => 'yes', 'rev' => 1],
];
$triggers146 = [
    [
        'name' => 'wp_options_view_bu_revision',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'set' => ['revision' => 'concat:new.revision:-view'],
        'values' => ['name' => 'new.option_name', 'old_value' => 'old.option_value', 'view_name' => 'view.name'],
    ],
    [
        'name' => 'wp_options_view_bi_prepare',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'when' => ['new.autoload', '=', 'no'],
        'set' => ['option_value' => 'concat:new.option_value:-prepared'],
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_view_ai_audit',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['name' => 'new.option_name', 'source_value' => 'view.value'],
    ],
    [
        'name' => 'wp_options_view_au_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'audit',
        'values' => ['name' => 'new.option_name', 'old_id' => 'old.option_id', 'new_id' => 'new.option_id'],
    ],
];
$returning146 = [
    ['expr' => 'new.option_id', 'as' => 'id'],
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'old.option_value', 'as' => 'old_value'],
    ['expr' => 'view.value', 'as' => 'view_value'],
    static fn (array $new, ?array $old, array $view, string $event, int $ordinal): string => $event . ':' . $ordinal . ':' . $new['option_name'],
];

$run146 = static function (array $options = [], ?array $current = null, ?array $next = null, ?array $triggers = null, ?array $mapping = null, ?array $returning = null) use ($base146, $currentRows146, $nextRows146, $mapping146, $triggers146, $returning146): array {
    return SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan::executeNext146(
        $base146,
        $current ?? $currentRows146,
        $next ?? $nextRows146,
        $mapping ?? $mapping146,
        ['blog_id', 'option_name'],
        $triggers ?? $triggers146,
        $returning ?? $returning146,
        $options + [
            'savepoint' => 'wp_import_view_146',
            'view' => 'wp_option_import_view',
            'current_source' => 'wp-options-current146',
            'next_source' => 'wp-options-next146',
        ],
    );
};

$released146 = static fn (): array => $run146();
$rolled146 = static fn (): array => $run146(['rollback_current' => true]);
$triggerRollback146 = static fn (): array => $run146([], [
    ['import_id' => 111, 'blog' => 1, 'name' => 'siteurl', 'value' => 'https://bad.test', 'autoload_flag' => 'yes', 'rev' => 2],
    ['import_id' => 112, 'blog' => 1, 'name' => 'home', 'value' => 'bad', 'autoload_flag' => 'yes', 'rev' => 2],
], $nextRows146, [
    $triggers146[0],
    [
        'name' => 'wp_options_view_bu_raise_home',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'raise-rollback',
        'when' => ['new.option_name', '=', 'home'],
        'reason' => 'view-trigger-home-current-source-rollback',
        'values' => ['name' => 'new.option_name', 'old_value' => 'old.option_value'],
    ],
]);

$cases146 = [
    'released status' => [static fn (): mixed => $released146()['status'], 'trigger-savepoint-returning-view-current-source-next146-released'],
    'released savepoint' => [static fn (): mixed => $released146()['savepoint'], 'wp_import_view_146'],
    'released view' => [static fn (): mixed => $released146()['view'], 'wp_option_import_view'],
    'released current source' => [static fn (): mixed => $released146()['current_source'], 'wp-options-current146'],
    'released next source' => [static fn (): mixed => $released146()['next_source'], 'wp-options-next146'],
    'released rollback false' => [static fn (): mixed => $released146()['rollback_current'], false],
    'released current returning names' => [static fn (): mixed => array_column($released146()['current_returning_rows'], 'name'), ['siteurl', 'plugin_seed']],
    'released next returning names' => [static fn (): mixed => array_column($released146()['next_returning_rows'], 'name'), ['plugin_seed', 'theme_mods']],
    'released combined returning names' => [static fn (): mixed => array_column($released146()['returning_rows'], 'name'), ['siteurl', 'plugin_seed', 'plugin_seed', 'theme_mods']],
    'released current update old value returned' => [static fn (): mixed => $released146()['current_returning_rows'][0]['old_value'], 'https://old.test'],
    'released current insert old value null' => [static fn (): mixed => $released146()['current_returning_rows'][1]['old_value'], null],
    'released trigger mutation in returning update revision' => [static fn (): mixed => $released146()['current_statement_rows'][0]['revision'], '2-view'],
    'released trigger mutation in current insert value' => [static fn (): mixed => $released146()['current_statement_rows'][3]['option_value'], 'seed-prepared'],
    'released next updates current inserted plugin' => [static fn (): mixed => $released146()['next_rows'][3]['option_value'], 'next-seed'],
    'released next rows include theme' => [static fn (): mixed => array_column($released146()['next_rows'], 'option_name'), ['siteurl', 'home', 'blogname', 'plugin_seed', 'theme_mods']],
    'released callable returning labels' => [static fn (): mixed => array_column($released146()['returning_rows'], 'expr5'), ['update:0:siteurl', 'insert:1:plugin_seed', 'update:0:plugin_seed', 'insert:1:theme_mods']],
    'released current changes' => [static fn (): mixed => $released146()['current_changes'], 2],
    'released next changes' => [static fn (): mixed => $released146()['next_changes'], 2],
    'released committed changes' => [static fn (): mixed => $released146()['committed_changes'], 4],
    'released next starts from current source' => [static fn (): mixed => $released146()['source_transition']['next_started_from'], 'current-source'],
    'released returning stream marks both' => [static fn (): mixed => $released146()['source_transition']['returning_stream'], 'current-and-next-admitted'],
    'released current attempts ordinals' => [static fn (): mixed => array_column($released146()['current_view_attempts'], 'ordinal'), [0, 1]],
    'released current mapped incoming names' => [static fn (): mixed => array_column(array_column($released146()['current_view_attempts'], 'incoming_row'), 'option_name'), ['siteurl', 'plugin_seed']],
    'released current mapped incoming ids' => [static fn (): mixed => array_column(array_column($released146()['current_view_attempts'], 'incoming_row'), 'option_id'), [101, 102]],
    'released next mapped view rows retained' => [static fn (): mixed => array_column(array_column($released146()['next_view_attempts'], 'view_row'), 'name'), ['plugin_seed', 'theme_mods']],
    'released yield events' => [static fn (): mixed => array_column($released146()['returning_rows'], 'event'), ['update', 'insert', 'update', 'insert']],
    'released yield sources' => [static fn (): mixed => array_column($released146()['returning_rows'], 'source'), ['wp-options-current146', 'wp-options-current146', 'wp-options-next146', 'wp-options-next146']],
    'released trigger names' => [static fn (): mixed => array_column($released146()['trigger_effects'], 'trigger'), ['wp_options_view_bu_revision', 'wp_options_view_au_audit', 'wp_options_view_bi_prepare', 'wp_options_view_ai_audit', 'wp_options_view_bu_revision', 'wp_options_view_au_audit', 'wp_options_view_ai_audit']],
    'released trigger sources' => [static fn (): mixed => array_values(array_unique(array_column($released146()['trigger_effects'], 'source'))), ['wp-options-current146', 'wp-options-next146']],
    'released update trigger sees view name' => [static fn (): mixed => $released146()['current_trigger_effects'][0]['row']['view_name'], 'siteurl'],
    'released after insert sees original view value' => [static fn (): mixed => $released146()['current_trigger_effects'][3]['row']['source_value'], 'seed'],
    'released dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-savepoint-returning-view-current-source-next146', $released146()['dependencies'], true), true],

    'rolled status' => [static fn (): mixed => $rolled146()['status'], 'trigger-savepoint-returning-view-current-source-next146-current-rolled-back'],
    'rolled current returning suppressed' => [static fn (): mixed => $rolled146()['current_returning_rows'], []],
    'rolled attempted current returning retained' => [static fn (): mixed => array_column($rolled146()['attempted_current_returning_rows'], 'name'), ['siteurl', 'plugin_seed']],
    'rolled next starts from savepoint' => [static fn (): mixed => $rolled146()['source_transition']['next_started_from'], 'savepoint'],
    'rolled next start rows restored' => [static fn (): mixed => array_column($rolled146()['next_start_rows'], 'option_name'), ['siteurl', 'home', 'blogname']],
    'rolled next inserts plugin instead of update' => [static fn (): mixed => $rolled146()['next_returning_rows'][0]['event'], 'insert'],
    'rolled next plugin old value null' => [static fn (): mixed => $rolled146()['next_returning_rows'][0]['old_value'], null],
    'rolled final plugin next value' => [static fn (): mixed => $rolled146()['rows'][3]['option_value'], 'next-seed-prepared'],
    'rolled final siteurl restored' => [static fn (): mixed => $rolled146()['rows'][0]['option_value'], 'https://old.test'],
    'rolled current changes zero' => [static fn (): mixed => $rolled146()['current_changes'], 0],
    'rolled attempted current changes retained' => [static fn (): mixed => $rolled146()['attempted_current_changes'], 2],
    'rolled next changes' => [static fn (): mixed => $rolled146()['next_changes'], 2],
    'rolled committed changes' => [static fn (): mixed => $rolled146()['committed_changes'], 2],
    'rolled returning stream marks suppression' => [static fn (): mixed => $rolled146()['source_transition']['returning_stream'], 'current-suppressed-next-admitted'],
    'rolled trigger effects still diagnose attempted current' => [static fn (): mixed => array_values(array_unique(array_column($rolled146()['trigger_effects'], 'source'))), ['wp-options-current146', 'wp-options-next146']],

    'trigger rollback status' => [static fn (): mixed => $triggerRollback146()['status'], 'trigger-savepoint-returning-view-current-source-next146-current-rolled-back'],
    'trigger rollback reason' => [static fn (): mixed => $triggerRollback146()['rollback_reason'], 'view-trigger-home-current-source-rollback'],
    'trigger rollback attempted returning prior row' => [static fn (): mixed => array_column($triggerRollback146()['attempted_current_returning_rows'], 'name'), ['siteurl']],
    'trigger rollback current returning suppressed' => [static fn (): mixed => $triggerRollback146()['current_returning_rows'], []],
    'trigger rollback attempts include failing row' => [static fn (): mixed => array_column($triggerRollback146()['current_view_attempts'], 'ordinal'), [0, 1]],
    'trigger rollback effect includes prior update and raise' => [static fn (): mixed => array_column($triggerRollback146()['current_trigger_effects'], 'action'), ['set-new', 'set-new', 'raise-rollback']],
    'trigger rollback final starts next from savepoint' => [static fn (): mixed => array_column($triggerRollback146()['next_start_rows'], 'option_value'), ['https://old.test', 'https://home.test', 'Old Blog']],
    'trigger rollback next rows include next imports' => [static fn (): mixed => array_column($triggerRollback146()['rows'], 'option_name'), ['siteurl', 'home', 'blogname', 'plugin_seed', 'theme_mods']],

    'null unique column does not conflict' => [static fn (): mixed => array_column($run146([], [['import_id' => 301, 'blog' => 1, 'name' => null, 'value' => 'a', 'autoload_flag' => 'yes', 'rev' => 1]], [['import_id' => 302, 'blog' => 1, 'name' => null, 'value' => 'b', 'autoload_flag' => 'yes', 'rev' => 1]])['rows'], 'option_id'), [1, 2, 3, 301, 302]],
    'bad savepoint throws' => [static fn (): mixed => $run146(['savepoint' => 'bad name']), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => $run146(['next_source' => 'bad next']), InvalidArgumentException::class],
    'empty mapping throws' => [static fn (): mixed => $run146([], null, null, null, []), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $run146([], [['import_id' => 1, 'blog' => 1, 'name' => 'bad', 'autoload_flag' => 'no', 'rev' => 1]], []), InvalidArgumentException::class],
    'missing unique column throws' => [static fn (): mixed => SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan::executeNext146($base146, [['import_id' => 1, 'blog' => 1, 'name' => 'bad', 'value' => 'x', 'autoload_flag' => 'no', 'rev' => 1]], [], ['import_id' => 'option_id'], ['option_name'], [], ['new.option_name']), InvalidArgumentException::class],
    'empty returning throws' => [static fn (): mixed => SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan::executeNext146($base146, [], [], $mapping146, ['blog_id', 'option_name'], [], []), InvalidArgumentException::class],
    'bad trigger action throws' => [static fn (): mixed => $run146([], [['import_id' => 401, 'blog' => 1, 'name' => 'bad_action', 'value' => 'x', 'autoload_flag' => 'yes', 'rev' => 1]], [], [[
        'name' => 'bad_action',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'delete-world',
    ]]), InvalidArgumentException::class],
    'bad when operator throws' => [static fn (): mixed => $run146([], [['import_id' => 402, 'blog' => 1, 'name' => 'bad_when', 'value' => 'x', 'autoload_flag' => 'yes', 'rev' => 1]], [], [[
        'name' => 'bad_when',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'when' => ['new.option_name', 'LIKE', 'bad%'],
    ]]), InvalidArgumentException::class],
    'view returning expression missing throws' => [static fn (): mixed => $run146([], [['import_id' => 403, 'blog' => 1, 'name' => 'missing_returning', 'value' => 'x', 'autoload_flag' => 'yes', 'rev' => 1]], [], null, null, [['expr' => 'view.missing', 'as' => 'missing']]), InvalidArgumentException::class],
];

foreach ($cases146 as $name => [$callback, $expected]) {
    $tests['trigger savepoint returning view current source next146 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

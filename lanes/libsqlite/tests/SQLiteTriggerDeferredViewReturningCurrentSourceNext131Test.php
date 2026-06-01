<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan;

$rows131 = [
    ['key_name' => 'siteurl', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'parent_key_name' => null, 'revision' => 1],
    ['key_name' => 'theme_mods', 'key_value' => 'old-theme', 'load_policy' => 'yes', 'parent_key_name' => 'siteurl', 'revision' => 2],
    ['key_name' => 'plugin_cache', 'key_value' => 'old-cache', 'load_policy' => 'no', 'parent_key_name' => 'siteurl', 'revision' => 3],
];
$current131 = [
    ['key_name' => 'theme_mods', 'key_value' => 'new-theme', 'load_policy' => 'yes', 'parent_key_name' => 'missing-theme-parent', 'revision' => 4],
    ['key_name' => 'seo_settings', 'key_value' => 'enabled', 'load_policy' => 'yes', 'parent_key_name' => 'siteurl', 'revision' => 1],
];
$next131 = [
    ['key_name' => 'plugin_cache', 'key_value' => 'primed', 'load_policy' => 'yes', 'parent_key_name' => 'siteurl', 'revision' => 5],
    ['key_name' => 'rewrite_rules', 'key_value' => 'cached', 'load_policy' => 'no', 'parent_key_name' => 'siteurl', 'revision' => 1],
];
$fk131 = ['parent_key' => 'key_name', 'child_key' => 'parent_key_name', 'deferred' => true];
$view131 = [
    'name' => 'app_loadable_settings_131',
    'columns' => ['key_name', 'key_value', 'parent_key_name', 'load_policy'],
    'where' => static fn (array $row): bool => ($row['load_policy'] ?? null) === 'yes',
    'order_by' => 'key_name',
];
$returning131 = [
    'key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'excluded.key_value', 'as' => 'incoming_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    static fn (array $new, ?array $old, array $mutation, string $event, int $ordinal): string => $event . ':' . $ordinal . ':' . ($old['key_name'] ?? 'new') . '>' . $new['key_name'],
];

$run131 = static fn (array $rows = null, array $current = null, array $next = null, array $options = [], array $view = null, array $returning = null): array => SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan::execute(
    $rows ?? $rows131,
    $current ?? $current131,
    $next ?? $next131,
    $fk131,
    $view ?? $view131,
    $returning ?? $returning131,
    $options + [
        'key' => 'key_name',
        'trigger' => 'app_settings_view_io_update_131',
        'current_source' => 'main@cookie-131-current',
        'next_source' => 'main@cookie-131-next',
    ],
);

$blocked131 = static fn (): array => $run131();
$admitted131 = static fn (): array => $run131(null, [
    ['key_name' => 'theme_mods', 'key_value' => 'new-theme', 'load_policy' => 'yes', 'parent_key_name' => 'siteurl', 'revision' => 4],
    ['key_name' => 'seo_settings', 'key_value' => 'enabled', 'load_policy' => 'yes', 'parent_key_name' => 'siteurl', 'revision' => 1],
]);
$kept131 = static fn (): array => $run131(null, null, null, ['rollback_on_deferred_violation' => false]);

$cases131 = [
    'blocked status' => [static fn (): mixed => $blocked131()['status'], 'deferred-view-returning-current-source-rolled-back'],
    'trigger name retained' => [static fn (): mixed => $blocked131()['trigger'], 'app_settings_view_io_update_131'],
    'key retained' => [static fn (): mixed => $blocked131()['key'], 'key_name'],
    'current source retained' => [static fn (): mixed => $blocked131()['current_source'], 'main@cookie-131-current'],
    'next source retained' => [static fn (): mixed => $blocked131()['next_source'], 'main@cookie-131-next'],
    'blocked visible source is current' => [static fn (): mixed => $blocked131()['visible_source'], 'main@cookie-131-current'],
    'view name retained' => [static fn (): mixed => $blocked131()['view'], 'app_loadable_settings_131'],
    'view columns retained' => [static fn (): mixed => $blocked131()['view_columns'], ['key_name', 'key_value', 'parent_key_name', 'load_policy']],
    'current view rows ordered' => [static fn (): mixed => array_column($blocked131()['current_view_rows'], 'key_name'), ['seo_settings', 'siteurl', 'theme_mods']],
    'current view sees updated value' => [static fn (): mixed => $blocked131()['current_view_rows'][2]['key_value'], 'new-theme'],
    'current view sees inserted option' => [static fn (): mixed => $blocked131()['current_view_rows'][0]['parent_key_name'], 'siteurl'],
    'current view row count' => [static fn (): mixed => $blocked131()['current_view_row_count'], 3],
    'current returning count' => [static fn (): mixed => count($blocked131()['current_returning_rows']), 2],
    'current returning phases' => [static fn (): mixed => array_column($blocked131()['current_returning_rows'], 'phase'), ['current', 'current']],
    'current returning sources' => [static fn (): mixed => array_column($blocked131()['current_returning_rows'], 'source'), ['main@cookie-131-current', 'main@cookie-131-current']],
    'current returning events' => [static fn (): mixed => array_column($blocked131()['current_returning_rows'], 'event'), ['update', 'insert']],
    'current returning names' => [static fn (): mixed => array_column(array_column($blocked131()['current_returning_rows'], 'returning'), 'key_name'), ['theme_mods', 'seo_settings']],
    'current returning new values' => [static fn (): mixed => array_column(array_column($blocked131()['current_returning_rows'], 'returning'), 'value'), ['new-theme', 'enabled']],
    'current returning old values include null for insert' => [static fn (): mixed => array_column(array_column($blocked131()['current_returning_rows'], 'returning'), 'old_value'), ['old-theme', null]],
    'current returning incoming values' => [static fn (): mixed => array_column(array_column($blocked131()['current_returning_rows'], 'returning'), 'incoming_value'), ['new-theme', 'enabled']],
    'current returning event aliases' => [static fn (): mixed => array_column(array_column($blocked131()['current_returning_rows'], 'returning'), 'event_name'), ['update', 'insert']],
    'current returning ordinal aliases' => [static fn (): mixed => array_column(array_column($blocked131()['current_returning_rows'], 'returning'), 'ordinal_value'), [0, 1]],
    'current callable returning traces' => [static fn (): mixed => array_column(array_column($blocked131()['current_returning_rows'], 'returning'), 'expr6'), ['update:0:theme_mods>theme_mods', 'insert:1:new>seo_settings']],
    'blocked next returning suppressed' => [static fn (): mixed => $blocked131()['next_returning_rows'], []],
    'blocked attempted next retained' => [static fn (): mixed => count($blocked131()['attempted_next_returning_rows']), 2],
    'blocked attempted next events' => [static fn (): mixed => array_column($blocked131()['attempted_next_returning_rows'], 'event'), ['update', 'insert']],
    'blocked attempted next names' => [static fn (): mixed => array_column(array_column($blocked131()['attempted_next_returning_rows'], 'returning'), 'key_name'), ['plugin_cache', 'rewrite_rules']],
    'blocked next yield suppressed' => [static fn (): mixed => $blocked131()['next_yield_stream'], []],
    'blocked attempted next yield retained' => [static fn (): mixed => count($blocked131()['attempted_next_yield_stream']), 2],
    'blocked yield stream only current rows' => [static fn (): mixed => array_column($blocked131()['yield_stream'], 'phase'), ['current', 'current']],
    'blocked yield stream row keys' => [static fn (): mixed => array_column($blocked131()['yield_stream'], 'row_key'), ['theme_mods', 'seo_settings']],
    'blocked violation count' => [static fn (): mixed => $blocked131()['deferred_violation_count'], 1],
    'blocked violation key' => [static fn (): mixed => $blocked131()['deferred_violations'][0]['child_key'], 'missing-theme-parent'],
    'blocked violation option name' => [static fn (): mixed => $blocked131()['deferred_violations'][0]['key_name'], 'theme_mods'],
    'blocked violation phase' => [static fn (): mixed => $blocked131()['deferred_violations'][0]['phase'], 'deferred-check-after-current-view-returning'],
    'blocked final rows restored' => [static fn (): mixed => array_column($blocked131()['final_rows'], 'key_name'), ['siteurl', 'theme_mods', 'plugin_cache']],
    'blocked final value restored' => [static fn (): mixed => $blocked131()['final_rows'][1]['key_value'], 'old-theme'],
    'blocked current rows keep attempted mutation evidence' => [static fn (): mixed => array_column($blocked131()['current_rows'], 'key_name'), ['siteurl', 'theme_mods', 'plugin_cache', 'seo_settings']],
    'blocked current row mutated parent evidence' => [static fn (): mixed => $blocked131()['current_rows'][1]['parent_key_name'], 'missing-theme-parent'],
    'blocked children from before rows' => [static fn (): mixed => array_column($blocked131()['children'], 'key_name'), ['siteurl', 'theme_mods', 'plugin_cache']],
    'blocked deferred checked after view' => [static fn (): mixed => $blocked131()['deferred_checked_after_current_view'], true],
    'blocked rollback flag' => [static fn (): mixed => $blocked131()['rolled_back_to_current_source'], true],
    'blocked next source flag' => [static fn (): mixed => $blocked131()['next_source_blocked_by_deferred_fk'], true],
    'blocked yield boundary' => [static fn (): mixed => $blocked131()['yield_boundary'], 'current-view-returning-yield-then-deferred-fk-rollback'],
    'dependencies include next131' => [static fn (): mixed => in_array('sqlite-trigger-deferred-view-returning-current-source-next131', $blocked131()['dependencies'], true), true],
    'dependencies include instead of marker' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-returning-current-source', $blocked131()['dependencies'], true), true],
    'dependencies include deferred marker' => [static fn (): mixed => in_array('sqlite-deferred-fk-check-after-current-view-returning', $blocked131()['dependencies'], true), true],

    'admitted status' => [static fn (): mixed => $admitted131()['status'], 'deferred-view-returning-current-source-admitted'],
    'admitted visible source next' => [static fn (): mixed => $admitted131()['visible_source'], 'main@cookie-131-next'],
    'admitted no violations' => [static fn (): mixed => $admitted131()['deferred_violations'], []],
    'admitted next returning visible' => [static fn (): mixed => array_column(array_column($admitted131()['next_returning_rows'], 'returning'), 'key_name'), ['plugin_cache', 'rewrite_rules']],
    'admitted yield stream has both phases' => [static fn (): mixed => array_column($admitted131()['yield_stream'], 'phase'), ['current', 'current', 'next', 'next']],
    'admitted final names include next rows' => [static fn (): mixed => array_column($admitted131()['final_rows'], 'key_name'), ['siteurl', 'theme_mods', 'plugin_cache', 'seo_settings', 'rewrite_rules']],
    'admitted final plugin cache is primed' => [static fn (): mixed => $admitted131()['final_rows'][2]['key_value'], 'primed'],
    'admitted rollback flag false' => [static fn (): mixed => $admitted131()['rolled_back_to_current_source'], false],
    'admitted boundary next source' => [static fn (): mixed => $admitted131()['yield_boundary'], 'current-view-returning-yield-then-next-source'],

    'non rollback option keeps status admitted' => [static fn (): mixed => $kept131()['status'], 'deferred-view-returning-current-source-admitted'],
    'non rollback keeps violation evidence' => [static fn (): mixed => $kept131()['deferred_violation_count'], 1],
    'non rollback exposes next returning' => [static fn (): mixed => count($kept131()['next_returning_rows']), 2],
    'non rollback final includes next insert' => [static fn (): mixed => array_column($kept131()['final_rows'], 'key_name'), ['siteurl', 'theme_mods', 'plugin_cache', 'seo_settings', 'rewrite_rules']],

    'bad key throws' => [static fn (): mixed => $run131(null, null, null, ['key' => 'bad-key']), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => $run131(null, null, null, ['current_source' => 'bad source']), InvalidArgumentException::class],
    'empty returning throws' => [static fn (): mixed => $run131(null, null, null, [], null, []), InvalidArgumentException::class],
    'empty view columns throw' => [static fn (): mixed => $run131(null, null, null, [], ['name' => 'bad_view', 'columns' => []]), InvalidArgumentException::class],
    'bad view where throws' => [static fn (): mixed => $run131(null, null, null, [], ['name' => 'bad_view', 'columns' => ['key_name'], 'where' => 'no']), InvalidArgumentException::class],
    'duplicate keys throw' => [static fn (): mixed => $run131(array_merge($rows131, [['key_name' => 'siteurl']])), InvalidArgumentException::class],
    'mutation missing key throws' => [static fn (): mixed => $run131(null, [['key_value' => 'missing']]), InvalidArgumentException::class],
];

foreach ($cases131 as $name => [$callback, $expected]) {
    $tests['trigger deferred view returning current source next131 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

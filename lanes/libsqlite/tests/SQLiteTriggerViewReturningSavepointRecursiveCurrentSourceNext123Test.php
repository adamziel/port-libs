<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog([
        $record('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)', 1),
        $record('table', 'app_setting_audit', 'app_setting_audit', 3, 'CREATE TABLE app_setting_audit(setting_id integer, label text, key_name text)', 2),
        $record('view', 'app_setting_import_view', 'app_setting_import_view', 0, "CREATE VIEW app_setting_import_view AS SELECT setting_id, key_name, key_value, load_policy FROM app_settings WHERE load_policy = 'yes'", 3),
        $record('trigger', 'app_setting_import_view_insert', 'app_setting_import_view', 0, "CREATE TRIGGER app_setting_import_view_insert INSTEAD OF INSERT ON app_setting_import_view BEGIN INSERT INTO app_settings(setting_id, key_name, key_value, load_policy) VALUES(new.setting_id, new.key_name, new.key_value, new.load_policy); INSERT INTO app_setting_audit(setting_id, label, key_name) VALUES(new.setting_id, 'view-import', new.key_name); SELECT new.setting_id, new.key_name; END", 4),
        $record('trigger', 'app_setting_import_view_insert_rollback', 'app_setting_import_view', 0, "CREATE TRIGGER app_setting_import_view_insert_rollback INSTEAD OF INSERT ON app_setting_import_view BEGIN INSERT INTO app_settings(setting_id, key_name, key_value, load_policy) VALUES(new.setting_id, new.key_name, new.key_value, new.load_policy); INSERT INTO app_setting_audit(setting_id, label, key_name) VALUES(new.setting_id, 'rollback-current-savepoint', new.key_name); SELECT new.setting_id, new.key_name; END", 5),
    ]);
};

$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);
$tables = [
    'main.app_settings' => [
        ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ],
    'main.app_setting_audit' => [
        ['setting_id' => 1, 'label' => 'seed', 'key_name' => 'base_url'],
    ],
];
$currentRows = [
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing.test', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'site_title', 'key_value' => 'Ported Title', 'load_policy' => 'yes'],
];
$nextRows = [
    ['setting_id' => 4, 'key_name' => 'module_registry', 'key_value' => 'a:0:{}', 'load_policy' => 'yes'],
    ['setting_id' => 5, 'key_name' => 'route_rules', 'key_value' => 'cached', 'load_policy' => 'no'],
];
$returning = ['setting_id', 'key_name', 'value' => 'key_value'];
$options = [
    'page_size' => 512,
    'savepoint_page_images' => [2 => $page('before-settings'), 3 => $page('before-audit')],
    'dirty_pages' => [2 => $page('dirty-settings'), 3 => $page('dirty-audit'), 4 => $page('dirty-overflow')],
    'wal_start_frame' => 7,
    'wal_frames' => [
        ['frame_index' => 8, 'page_number' => 2],
        ['frame_index' => 9, 'page_number' => 3, 'commit_frame' => true],
    ],
];

$run = static fn (string $trigger = 'app_setting_import_view_insert', array $current = null, array $next = null, array $projection = null, array $extraOptions = []) => SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan::execute(
    $catalog(),
    $trigger,
    $tables,
    $current ?? $currentRows,
    $next ?? $nextRows,
    'app_import',
    $projection ?? $returning,
    $extraOptions + $options,
);

$success = static fn (): array => $run();
$nextRollback = static fn (): array => $run('app_setting_import_view_insert', $currentRows, [
    ['setting_id' => 4, 'key_name' => 'module_registry', 'key_value' => 'a:0:{}', 'load_policy' => 'yes'],
], $returning, ['next_trigger_name' => 'app_setting_import_view_insert_rollback', 'dirty_pages' => [2 => $page('dirty-settings')]]);
$currentRollback = static fn (): array => $run('app_setting_import_view_insert', $currentRows, $nextRows, $returning, ['current_trigger_name' => 'app_setting_import_view_insert_rollback']);
$star = static fn (): array => $run('app_setting_import_view_insert', [
    ['setting_id' => 10, 'key_name' => 'star_current', 'key_value' => 'star', 'load_policy' => 'yes'],
], [
    ['setting_id' => 11, 'key_name' => 'star_next', 'key_value' => 'next', 'load_policy' => 'yes'],
], ['*']);

$cases = [
    'success status applied' => [static fn (): mixed => $success()['status'], 'current-next-view-trigger-returning-applied'],
    'success dependency names next123' => [static fn (): mixed => $success()['dependencies'][0], 'sqlite-trigger-view-returning-savepoint-recursive-current-source-next123'],
    'success savepoint retained' => [static fn (): mixed => $success()['savepoint'], 'app_import'],
    'success current source starts with seed row' => [static fn (): mixed => array_column($success()['current_source_tables']['main.app_settings'], 'key_name'), ['base_url']],
    'success next source includes current inserted rows' => [static fn (): mixed => array_column($success()['next_source_tables']['main.app_settings'], 'key_name'), ['base_url', 'landing_url', 'site_title']],
    'success final settings include current and next rows' => [static fn (): mixed => array_column($success()['tables']['main.app_settings'], 'key_name'), ['base_url', 'landing_url', 'site_title', 'module_registry', 'route_rules']],
    'success final audit includes all trigger writes' => [static fn (): mixed => array_column($success()['tables']['main.app_setting_audit'], 'key_name'), ['base_url', 'landing_url', 'site_title', 'module_registry', 'route_rules']],
    'success current changes count two rows times two writes' => [static fn (): mixed => $success()['current']['changes'], 4],
    'success next changes count two rows times two writes' => [static fn (): mixed => $success()['next']['changes'], 4],
    'success combined changes count' => [static fn (): mixed => $success()['changes'], 8],
    'success current returning rows' => [static fn (): mixed => array_column($success()['current_returning'], 'key_name'), ['landing_url', 'site_title']],
    'success next returning rows' => [static fn (): mixed => array_column($success()['next_returning'], 'key_name'), ['module_registry', 'route_rules']],
    'success combined returning rows preserve phase order' => [static fn (): mixed => array_column($success()['returning_rows'], 'key_name'), ['landing_url', 'site_title', 'module_registry', 'route_rules']],
    'success returning alias value works' => [static fn (): mixed => array_column($success()['returning_rows'], 'value'), ['https://landing.test', 'Ported Title', 'a:0:{}', 'cached']],
    'success attempted returning rows count' => [static fn (): mixed => count($success()['attempted_returning_rows']), 4],
    'success attempted returning phases' => [static fn (): mixed => array_column($success()['attempted_returning_rows'], 'phase'), ['current', 'current', 'next', 'next']],
    'success attempted returning ordinals' => [static fn (): mixed => array_column($success()['attempted_returning_rows'], 'source_ordinal'), [0, 1, 0, 1]],
    'success current operations count six' => [static fn (): mixed => count($success()['current']['operations']), 6],
    'success next operations count six' => [static fn (): mixed => count($success()['next']['operations']), 6],
    'success operation phases retained' => [static fn (): mixed => array_values(array_unique(array_column($success()['yield_edges'], 'phase'))), ['current', 'next']],
    'success operation source ordinals retained' => [static fn (): mixed => array_column($success()['yield_edges'], 'source_ordinal'), [0, 1, 0, 1]],
    'success yield statuses committed' => [static fn (): mixed => array_column($success()['yield_edges'], 'status'), ['committed', 'committed', 'committed', 'committed']],
    'success first current operation writes settings' => [static fn (): mixed => $success()['current']['operations'][0]['table'], 'app_settings'],
    'success first current operation row name' => [static fn (): mixed => $success()['current']['operations'][0]['row']['key_name'], 'landing_url'],
    'success second current operation writes audit' => [static fn (): mixed => $success()['current']['operations'][1]['table'], 'app_setting_audit'],
    'success current select operation is retained' => [static fn (): mixed => $success()['current']['operations'][2]['kind'], 'select'],
    'success next first operation sees next ordinal zero' => [static fn (): mixed => $success()['next']['operations'][0]['source_ordinal'], 0],
    'success next select value returns module registry id' => [static fn (): mixed => $success()['next']['operations'][2]['values'], [4, 'module_registry']],
    'success no rolled back phases' => [static fn (): mixed => $success()['rolled_back_phases'], []],
    'success current not rolled back' => [static fn (): mixed => $success()['current']['rolled_back'], false],
    'success next not rolled back' => [static fn (): mixed => $success()['next']['rolled_back'], false],

    'current rollback status' => [static fn (): mixed => $currentRollback()['status'], 'current-source-view-trigger-savepoint-rolled-back'],
    'current rollback phases' => [static fn (): mixed => $currentRollback()['rolled_back_phases'], ['current']],
    'current rollback suppresses current returning' => [static fn (): mixed => $currentRollback()['current_returning'], []],
    'current rollback preserves attempted current returning first row' => [static fn (): mixed => $currentRollback()['current']['attempted_returning_rows'][0]['row']['key_name'], 'landing_url'],
    'current rollback stops current at first row' => [static fn (): mixed => count($currentRollback()['current']['attempted_returning_rows']), 1],
    'current rollback leaves next source as original tables' => [static fn (): mixed => array_column($currentRollback()['next_source_tables']['main.app_settings'], 'key_name'), ['base_url']],
    'current rollback still runs next source from original state' => [static fn (): mixed => array_column($currentRollback()['next_returning'], 'key_name'), ['module_registry', 'route_rules']],
    'current rollback final table contains original plus next only' => [static fn (): mixed => array_column($currentRollback()['tables']['main.app_settings'], 'key_name'), ['base_url', 'module_registry', 'route_rules']],
    'current rollback changes count only next phase' => [static fn (): mixed => $currentRollback()['changes'], 4],
    'current rollback reason retained' => [static fn (): mixed => $currentRollback()['current']['rollback']['reason'], 'view-trigger-raise-rollback-current-savepoint'],
    'current rollback page images include dirty pages' => [static fn (): mixed => $currentRollback()['current']['rollback']['rollback_page_numbers'], [2, 3, 4]],
    'current rollback wal frame restored to start' => [static fn (): mixed => $currentRollback()['current']['rollback']['rollback_to_wal_frame'], 7],
    'current rollback discarded wal frames' => [static fn (): mixed => array_column($currentRollback()['current']['rollback']['discarded_wal_frames'], 'frame_index'), [8, 9]],
    'current rollback yield edge marks rolled back' => [static fn (): mixed => $currentRollback()['yield_edges'][0]['status'], 'rolled-back'],
    'current rollback next yield edges still committed' => [static fn (): mixed => array_slice(array_column($currentRollback()['yield_edges'], 'status'), 1), ['committed', 'committed']],

    'next rollback status' => [static fn (): mixed => $nextRollback()['status'], 'next-source-view-trigger-savepoint-rolled-back'],
    'next rollback phases' => [static fn (): mixed => $nextRollback()['rolled_back_phases'], ['next']],
    'next rollback keeps current returning' => [static fn (): mixed => array_column($nextRollback()['current_returning'], 'key_name'), ['landing_url', 'site_title']],
    'next rollback suppresses next returning' => [static fn (): mixed => $nextRollback()['next_returning'], []],
    'next rollback final tables preserve current phase only' => [static fn (): mixed => array_column($nextRollback()['tables']['main.app_settings'], 'key_name'), ['base_url', 'landing_url', 'site_title']],
    'next rollback changes count only current phase' => [static fn (): mixed => $nextRollback()['changes'], 4],
    'next rollback attempted row retained' => [static fn (): mixed => $nextRollback()['next']['attempted_returning_rows'][0]['row']['key_name'], 'module_registry'],
    'next rollback page list uses narrowed dirty set' => [static fn (): mixed => $nextRollback()['next']['rollback']['rollback_page_numbers'], [2, 3]],
    'next rollback discarded wal frames retained' => [static fn (): mixed => array_column($nextRollback()['next']['rollback']['discarded_wal_frames'], 'frame_index'), [8, 9]],
    'next rollback yield statuses show current commits then rollback' => [static fn (): mixed => array_column($nextRollback()['yield_edges'], 'status'), ['committed', 'committed', 'rolled-back']],

    'star returning current exposes full row' => [static fn (): mixed => $star()['current_returning'][0]['key_name'], 'star_current'],
    'star returning next exposes full row' => [static fn (): mixed => $star()['next_returning'][0]['key_value'], 'next'],
    'star returning attempted phases two rows' => [static fn (): mixed => array_column($star()['attempted_returning_rows'], 'phase'), ['current', 'next']],
    'star final table includes star rows' => [static fn (): mixed => array_slice(array_column($star()['tables']['main.app_settings'], 'key_name'), -2), ['star_current', 'star_next']],

    'empty current rows rejected' => [static fn (): mixed => $run('app_setting_import_view_insert', [], $nextRows), InvalidArgumentException::class],
    'empty next rows rejected' => [static fn (): mixed => $run('app_setting_import_view_insert', $currentRows, []), InvalidArgumentException::class],
    'malformed current source rejected' => [static fn (): mixed => $run('app_setting_import_view_insert', ['bad' => $currentRows[0]], $nextRows), InvalidArgumentException::class],
    'malformed next source rejected during phase' => [static fn (): mixed => $run('app_setting_import_view_insert', $currentRows, ['bad']), InvalidArgumentException::class],
    'empty savepoint rejected' => [static fn (): mixed => SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan::execute($catalog(), 'app_setting_import_view_insert', $tables, $currentRows, $nextRows, ''), InvalidArgumentException::class],
    'missing returning column rejected' => [static fn (): mixed => $run('app_setting_import_view_insert', $currentRows, $nextRows, ['missing']), InvalidArgumentException::class],
    'bad dirty page key rejected' => [static fn (): mixed => $run('app_setting_import_view_insert_rollback', $currentRows, $nextRows, $returning, ['dirty_pages' => ['x' => $page('bad')]]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger view returning savepoint recursive current source next123 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

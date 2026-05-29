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
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, option_name text)', 2),
        $record('view', 'wp_option_import_view', 'wp_option_import_view', 0, "CREATE VIEW wp_option_import_view AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = 'yes'", 3),
        $record('trigger', 'wp_option_import_view_insert', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'view-import', new.option_name); SELECT new.option_id, new.option_name; END", 4),
        $record('trigger', 'wp_option_import_view_insert_rollback', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert_rollback INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'rollback-current-savepoint', new.option_name); SELECT new.option_id, new.option_name; END", 5),
    ]);
};

$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);
$tables = [
    'main.wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ],
    'main.wp_option_audit' => [
        ['option_id' => 1, 'label' => 'seed', 'option_name' => 'siteurl'],
    ],
];
$currentRows = [
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Ported Site', 'autoload' => 'yes'],
];
$nextRows = [
    ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'option_value' => 'cached', 'autoload' => 'no'],
];
$returning = ['option_id', 'option_name', 'value' => 'option_value'];
$options = [
    'page_size' => 512,
    'savepoint_page_images' => [2 => $page('before-options'), 3 => $page('before-audit')],
    'dirty_pages' => [2 => $page('dirty-options'), 3 => $page('dirty-audit'), 4 => $page('dirty-overflow')],
    'wal_start_frame' => 7,
    'wal_frames' => [
        ['frame_index' => 8, 'page_number' => 2],
        ['frame_index' => 9, 'page_number' => 3, 'commit_frame' => true],
    ],
];

$run = static fn (string $trigger = 'wp_option_import_view_insert', array $current = null, array $next = null, array $projection = null, array $extraOptions = []) => SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan::execute(
    $catalog(),
    $trigger,
    $tables,
    $current ?? $currentRows,
    $next ?? $nextRows,
    'wp_import',
    $projection ?? $returning,
    $extraOptions + $options,
);

$success = static fn (): array => $run();
$nextRollback = static fn (): array => $run('wp_option_import_view_insert', $currentRows, [
    ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
], $returning, ['next_trigger_name' => 'wp_option_import_view_insert_rollback', 'dirty_pages' => [2 => $page('dirty-options')]]);
$currentRollback = static fn (): array => $run('wp_option_import_view_insert', $currentRows, $nextRows, $returning, ['current_trigger_name' => 'wp_option_import_view_insert_rollback']);
$star = static fn (): array => $run('wp_option_import_view_insert', [
    ['option_id' => 10, 'option_name' => 'star_current', 'option_value' => 'star', 'autoload' => 'yes'],
], [
    ['option_id' => 11, 'option_name' => 'star_next', 'option_value' => 'next', 'autoload' => 'yes'],
], ['*']);

$cases = [
    'success status applied' => [static fn (): mixed => $success()['status'], 'current-next-view-trigger-returning-applied'],
    'success dependency names next123' => [static fn (): mixed => $success()['dependencies'][0], 'sqlite-trigger-view-returning-savepoint-recursive-current-source-next123'],
    'success savepoint retained' => [static fn (): mixed => $success()['savepoint'], 'wp_import'],
    'success current source starts with seed row' => [static fn (): mixed => array_column($success()['current_source_tables']['main.wp_options'], 'option_name'), ['siteurl']],
    'success next source includes current inserted rows' => [static fn (): mixed => array_column($success()['next_source_tables']['main.wp_options'], 'option_name'), ['siteurl', 'home', 'blogname']],
    'success final options include current and next rows' => [static fn (): mixed => array_column($success()['tables']['main.wp_options'], 'option_name'), ['siteurl', 'home', 'blogname', 'active_plugins', 'rewrite_rules']],
    'success final audit includes all trigger writes' => [static fn (): mixed => array_column($success()['tables']['main.wp_option_audit'], 'option_name'), ['siteurl', 'home', 'blogname', 'active_plugins', 'rewrite_rules']],
    'success current changes count two rows times two writes' => [static fn (): mixed => $success()['current']['changes'], 4],
    'success next changes count two rows times two writes' => [static fn (): mixed => $success()['next']['changes'], 4],
    'success combined changes count' => [static fn (): mixed => $success()['changes'], 8],
    'success current returning rows' => [static fn (): mixed => array_column($success()['current_returning'], 'option_name'), ['home', 'blogname']],
    'success next returning rows' => [static fn (): mixed => array_column($success()['next_returning'], 'option_name'), ['active_plugins', 'rewrite_rules']],
    'success combined returning rows preserve phase order' => [static fn (): mixed => array_column($success()['returning_rows'], 'option_name'), ['home', 'blogname', 'active_plugins', 'rewrite_rules']],
    'success returning alias value works' => [static fn (): mixed => array_column($success()['returning_rows'], 'value'), ['https://home.test', 'Ported Site', 'a:0:{}', 'cached']],
    'success attempted returning rows count' => [static fn (): mixed => count($success()['attempted_returning_rows']), 4],
    'success attempted returning phases' => [static fn (): mixed => array_column($success()['attempted_returning_rows'], 'phase'), ['current', 'current', 'next', 'next']],
    'success attempted returning ordinals' => [static fn (): mixed => array_column($success()['attempted_returning_rows'], 'source_ordinal'), [0, 1, 0, 1]],
    'success current operations count six' => [static fn (): mixed => count($success()['current']['operations']), 6],
    'success next operations count six' => [static fn (): mixed => count($success()['next']['operations']), 6],
    'success operation phases retained' => [static fn (): mixed => array_values(array_unique(array_column($success()['yield_edges'], 'phase'))), ['current', 'next']],
    'success operation source ordinals retained' => [static fn (): mixed => array_column($success()['yield_edges'], 'source_ordinal'), [0, 1, 0, 1]],
    'success yield statuses committed' => [static fn (): mixed => array_column($success()['yield_edges'], 'status'), ['committed', 'committed', 'committed', 'committed']],
    'success first current operation writes options' => [static fn (): mixed => $success()['current']['operations'][0]['table'], 'wp_options'],
    'success first current operation row name' => [static fn (): mixed => $success()['current']['operations'][0]['row']['option_name'], 'home'],
    'success second current operation writes audit' => [static fn (): mixed => $success()['current']['operations'][1]['table'], 'wp_option_audit'],
    'success current select operation is retained' => [static fn (): mixed => $success()['current']['operations'][2]['kind'], 'select'],
    'success next first operation sees next ordinal zero' => [static fn (): mixed => $success()['next']['operations'][0]['source_ordinal'], 0],
    'success next select value returns active plugins id' => [static fn (): mixed => $success()['next']['operations'][2]['values'], [4, 'active_plugins']],
    'success no rolled back phases' => [static fn (): mixed => $success()['rolled_back_phases'], []],
    'success current not rolled back' => [static fn (): mixed => $success()['current']['rolled_back'], false],
    'success next not rolled back' => [static fn (): mixed => $success()['next']['rolled_back'], false],

    'current rollback status' => [static fn (): mixed => $currentRollback()['status'], 'current-source-view-trigger-savepoint-rolled-back'],
    'current rollback phases' => [static fn (): mixed => $currentRollback()['rolled_back_phases'], ['current']],
    'current rollback suppresses current returning' => [static fn (): mixed => $currentRollback()['current_returning'], []],
    'current rollback preserves attempted current returning first row' => [static fn (): mixed => $currentRollback()['current']['attempted_returning_rows'][0]['row']['option_name'], 'home'],
    'current rollback stops current at first row' => [static fn (): mixed => count($currentRollback()['current']['attempted_returning_rows']), 1],
    'current rollback leaves next source as original tables' => [static fn (): mixed => array_column($currentRollback()['next_source_tables']['main.wp_options'], 'option_name'), ['siteurl']],
    'current rollback still runs next source from original state' => [static fn (): mixed => array_column($currentRollback()['next_returning'], 'option_name'), ['active_plugins', 'rewrite_rules']],
    'current rollback final table contains original plus next only' => [static fn (): mixed => array_column($currentRollback()['tables']['main.wp_options'], 'option_name'), ['siteurl', 'active_plugins', 'rewrite_rules']],
    'current rollback changes count only next phase' => [static fn (): mixed => $currentRollback()['changes'], 4],
    'current rollback reason retained' => [static fn (): mixed => $currentRollback()['current']['rollback']['reason'], 'view-trigger-raise-rollback-current-savepoint'],
    'current rollback page images include dirty pages' => [static fn (): mixed => $currentRollback()['current']['rollback']['rollback_page_numbers'], [2, 3, 4]],
    'current rollback wal frame restored to start' => [static fn (): mixed => $currentRollback()['current']['rollback']['rollback_to_wal_frame'], 7],
    'current rollback discarded wal frames' => [static fn (): mixed => array_column($currentRollback()['current']['rollback']['discarded_wal_frames'], 'frame_index'), [8, 9]],
    'current rollback yield edge marks rolled back' => [static fn (): mixed => $currentRollback()['yield_edges'][0]['status'], 'rolled-back'],
    'current rollback next yield edges still committed' => [static fn (): mixed => array_slice(array_column($currentRollback()['yield_edges'], 'status'), 1), ['committed', 'committed']],

    'next rollback status' => [static fn (): mixed => $nextRollback()['status'], 'next-source-view-trigger-savepoint-rolled-back'],
    'next rollback phases' => [static fn (): mixed => $nextRollback()['rolled_back_phases'], ['next']],
    'next rollback keeps current returning' => [static fn (): mixed => array_column($nextRollback()['current_returning'], 'option_name'), ['home', 'blogname']],
    'next rollback suppresses next returning' => [static fn (): mixed => $nextRollback()['next_returning'], []],
    'next rollback final tables preserve current phase only' => [static fn (): mixed => array_column($nextRollback()['tables']['main.wp_options'], 'option_name'), ['siteurl', 'home', 'blogname']],
    'next rollback changes count only current phase' => [static fn (): mixed => $nextRollback()['changes'], 4],
    'next rollback attempted row retained' => [static fn (): mixed => $nextRollback()['next']['attempted_returning_rows'][0]['row']['option_name'], 'active_plugins'],
    'next rollback page list uses narrowed dirty set' => [static fn (): mixed => $nextRollback()['next']['rollback']['rollback_page_numbers'], [2, 3]],
    'next rollback discarded wal frames retained' => [static fn (): mixed => array_column($nextRollback()['next']['rollback']['discarded_wal_frames'], 'frame_index'), [8, 9]],
    'next rollback yield statuses show current commits then rollback' => [static fn (): mixed => array_column($nextRollback()['yield_edges'], 'status'), ['committed', 'committed', 'rolled-back']],

    'star returning current exposes full row' => [static fn (): mixed => $star()['current_returning'][0]['option_name'], 'star_current'],
    'star returning next exposes full row' => [static fn (): mixed => $star()['next_returning'][0]['option_value'], 'next'],
    'star returning attempted phases two rows' => [static fn (): mixed => array_column($star()['attempted_returning_rows'], 'phase'), ['current', 'next']],
    'star final table includes star rows' => [static fn (): mixed => array_slice(array_column($star()['tables']['main.wp_options'], 'option_name'), -2), ['star_current', 'star_next']],

    'empty current rows rejected' => [static fn (): mixed => $run('wp_option_import_view_insert', [], $nextRows), InvalidArgumentException::class],
    'empty next rows rejected' => [static fn (): mixed => $run('wp_option_import_view_insert', $currentRows, []), InvalidArgumentException::class],
    'malformed current source rejected' => [static fn (): mixed => $run('wp_option_import_view_insert', ['bad' => $currentRows[0]], $nextRows), InvalidArgumentException::class],
    'malformed next source rejected during phase' => [static fn (): mixed => $run('wp_option_import_view_insert', $currentRows, ['bad']), InvalidArgumentException::class],
    'empty savepoint rejected' => [static fn (): mixed => SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan::execute($catalog(), 'wp_option_import_view_insert', $tables, $currentRows, $nextRows, ''), InvalidArgumentException::class],
    'missing returning column rejected' => [static fn (): mixed => $run('wp_option_import_view_insert', $currentRows, $nextRows, ['missing']), InvalidArgumentException::class],
    'bad dirty page key rejected' => [static fn (): mixed => $run('wp_option_import_view_insert_rollback', $currentRows, $nextRows, $returning, ['dirty_pages' => ['x' => $page('bad')]]), InvalidArgumentException::class],
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

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningSavepointViewCurrentSourceNextPlan;

$record136 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog136 = static function () use ($record136): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog([
        $record136('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record136('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, option_name text)', 2),
        $record136('view', 'wp_option_import_view', 'wp_option_import_view', 0, "CREATE VIEW wp_option_import_view AS SELECT option_id, option_name, option_value, autoload FROM wp_options", 3),
        $record136('trigger', 'wp_option_import_view_insert', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'view-import', new.option_name); SELECT new.option_id, new.option_name; END", 4),
        $record136('trigger', 'wp_option_import_view_insert_rollback', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert_rollback INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'rollback-current-savepoint', new.option_name); SELECT new.option_id, new.option_name; END", 5),
    ]);
};

$page136 = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);
$tables136 = [
    'main.wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ],
    'main.wp_option_audit' => [
        ['option_id' => 1, 'label' => 'seed', 'option_name' => 'siteurl'],
    ],
];
$current136 = [
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Ported Site', 'autoload' => 'yes'],
];
$next136 = [
    ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'option_value' => 'cached', 'autoload' => 'no'],
];
$returning136 = ['option_id', 'option_name', 'value' => 'option_value'];
$options136 = [
    'current_source' => 'main@cookie-136-current',
    'next_source' => 'main@cookie-136-next',
    'page_size' => 512,
    'savepoint_page_images' => [2 => $page136('before-options'), 3 => $page136('before-audit')],
    'dirty_pages' => [2 => $page136('dirty-options'), 3 => $page136('dirty-audit'), 4 => $page136('dirty-overflow')],
    'wal_start_frame' => 11,
    'wal_frames' => [
        ['frame_index' => 12, 'page_number' => 2],
        ['frame_index' => 13, 'page_number' => 3, 'commit_frame' => true],
    ],
];

$run136 = static fn (array $extra = [], array $currentRows = null, array $nextRows = null, array $projection = null): array => SQLiteTriggerReturningSavepointViewCurrentSourceNextPlan::execute(
    $catalog136(),
    'wp_option_import_view_insert',
    $tables136,
    $currentRows ?? $current136,
    $nextRows ?? $next136,
    'wp_import_next136',
    $projection ?? $returning136,
    $extra + $options136,
);

$success136 = static fn (): array => $run136();
$currentRollback136 = static fn (): array => $run136(['current_trigger_name' => 'wp_option_import_view_insert_rollback']);
$nextRollback136 = static fn (): array => $run136(['next_trigger_name' => 'wp_option_import_view_insert_rollback']);
$bothRollback136 = static fn (): array => $run136(['current_trigger_name' => 'wp_option_import_view_insert_rollback', 'next_trigger_name' => 'wp_option_import_view_insert_rollback']);
$star136 = static fn (): array => $run136([], [
    ['option_id' => 10, 'option_name' => 'star_current', 'option_value' => 'star', 'autoload' => 'yes'],
], [
    ['option_id' => 11, 'option_name' => 'star_next', 'option_value' => 'next', 'autoload' => 'yes'],
], ['*']);

$cases136 = [
    'success status' => [static fn (): mixed => $success136()['status'], 'current-next-view-trigger-returning-source-admitted'],
    'success current source token' => [static fn (): mixed => $success136()['current_source'], 'main@cookie-136-current'],
    'success next source token' => [static fn (): mixed => $success136()['next_source'], 'main@cookie-136-next'],
    'success source transition input' => [static fn (): mixed => $success136()['source_transition']['next_input'], 'current-phase-output'],
    'success visible source is next' => [static fn (): mixed => $success136()['source_transition']['visible_source'], 'main@cookie-136-next'],
    'success current admitted' => [static fn (): mixed => $success136()['current_source_admitted'], true],
    'success next admitted' => [static fn (): mixed => $success136()['next_source_admitted'], true],
    'success admitted current stream names' => [static fn (): mixed => array_column(array_column($success136()['admitted_current_source_stream'], 'returning'), 'option_name'), ['home', 'blogname']],
    'success admitted next stream names' => [static fn (): mixed => array_column(array_column($success136()['admitted_next_source_stream'], 'returning'), 'option_name'), ['active_plugins', 'rewrite_rules']],
    'success no suppressed current stream' => [static fn (): mixed => $success136()['suppressed_current_source_stream'], []],
    'success no suppressed next stream' => [static fn (): mixed => $success136()['suppressed_next_source_stream'], []],
    'success returning rows combine current and next' => [static fn (): mixed => array_column($success136()['returning_rows'], 'option_name'), ['home', 'blogname', 'active_plugins', 'rewrite_rules']],
    'success admitted returning rows alias values' => [static fn (): mixed => array_column($success136()['admitted_returning_rows'], 'value'), ['https://home.test', 'Ported Site', 'a:0:{}', 'cached']],
    'success attempted stream phases' => [static fn (): mixed => array_column($success136()['attempted_source_stream'], 'phase'), ['current', 'current', 'next', 'next']],
    'success attempted stream source ordinals' => [static fn (): mixed => array_column($success136()['attempted_source_stream'], 'source_ordinal'), [0, 1, 0, 1]],
    'success attempted stream admitted flags' => [static fn (): mixed => array_column($success136()['attempted_source_stream'], 'admitted'), [true, true, true, true]],
    'success final table includes all rows' => [static fn (): mixed => array_column($success136()['tables']['main.wp_options'], 'option_name'), ['siteurl', 'home', 'blogname', 'active_plugins', 'rewrite_rules']],
    'success next source tables include current phase rows' => [static fn (): mixed => array_column($success136()['next_source_tables']['main.wp_options'], 'option_name'), ['siteurl', 'home', 'blogname']],
    'success dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-returning-savepoint-view-current-source-next136', $success136()['dependencies'], true), true],
    'success dependency closure' => [static fn (): mixed => $success136()['dependency_closure'], 'reuses-native-view-trigger-returning-savepoint-plans'],

    'current rollback status' => [static fn (): mixed => $currentRollback136()['status'], 'current-view-trigger-rollback-next-source-admitted'],
    'current rollback transition records saved image' => [static fn (): mixed => $currentRollback136()['source_transition']['next_input'], 'saved-current-source'],
    'current rollback visible source is next' => [static fn (): mixed => $currentRollback136()['source_transition']['visible_source'], 'main@cookie-136-next'],
    'current rollback current admitted false' => [static fn (): mixed => $currentRollback136()['current_source_admitted'], false],
    'current rollback next admitted true' => [static fn (): mixed => $currentRollback136()['next_source_admitted'], true],
    'current rollback suppressed current names' => [static fn (): mixed => array_column(array_column($currentRollback136()['suppressed_current_source_stream'], 'returning'), 'option_name'), ['home']],
    'current rollback suppressed current admitted flags' => [static fn (): mixed => array_column($currentRollback136()['suppressed_current_source_stream'], 'admitted'), [false]],
    'current rollback admitted current empty' => [static fn (): mixed => $currentRollback136()['admitted_current_source_stream'], []],
    'current rollback admitted next names' => [static fn (): mixed => array_column(array_column($currentRollback136()['admitted_next_source_stream'], 'returning'), 'option_name'), ['active_plugins', 'rewrite_rules']],
    'current rollback returning rows are next only' => [static fn (): mixed => array_column($currentRollback136()['returning_rows'], 'option_name'), ['active_plugins', 'rewrite_rules']],
    'current rollback suppressed returning rows keep attempted current' => [static fn (): mixed => array_column($currentRollback136()['suppressed_returning_rows'], 'option_name'), ['home']],
    'current rollback next starts from saved source' => [static fn (): mixed => array_column($currentRollback136()['next_source_tables']['main.wp_options'], 'option_name'), ['siteurl']],
    'current rollback final table excludes current rows' => [static fn (): mixed => array_column($currentRollback136()['tables']['main.wp_options'], 'option_name'), ['siteurl', 'active_plugins', 'rewrite_rules']],
    'current rollback base phase marker' => [static fn (): mixed => $currentRollback136()['rolled_back_phases'], ['current']],
    'current rollback page numbers retained' => [static fn (): mixed => $currentRollback136()['current']['rollback']['rollback_page_numbers'], [2, 3, 4]],
    'current rollback wal frame retained' => [static fn (): mixed => $currentRollback136()['current']['rollback']['rollback_to_wal_frame'], 11],
    'current rollback discarded frames retained' => [static fn (): mixed => array_column($currentRollback136()['current']['rollback']['discarded_wal_frames'], 'frame_index'), [12, 13]],
    'current rollback attempted stream flags' => [static fn (): mixed => array_column($currentRollback136()['attempted_source_stream'], 'admitted'), [false, true, true]],
    'current rollback attempted stream phases' => [static fn (): mixed => array_column($currentRollback136()['attempted_source_stream'], 'phase'), ['current', 'next', 'next']],

    'next rollback status' => [static fn (): mixed => $nextRollback136()['status'], 'current-view-trigger-admitted-next-source-rolled-back'],
    'next rollback current admitted true' => [static fn (): mixed => $nextRollback136()['current_source_admitted'], true],
    'next rollback next admitted false' => [static fn (): mixed => $nextRollback136()['next_source_admitted'], false],
    'next rollback admitted current names' => [static fn (): mixed => array_column(array_column($nextRollback136()['admitted_current_source_stream'], 'returning'), 'option_name'), ['home', 'blogname']],
    'next rollback suppressed next names' => [static fn (): mixed => array_column(array_column($nextRollback136()['suppressed_next_source_stream'], 'returning'), 'option_name'), ['active_plugins']],
    'next rollback returning rows are current only' => [static fn (): mixed => array_column($nextRollback136()['returning_rows'], 'option_name'), ['home', 'blogname']],
    'next rollback suppressed returning rows are next attempted' => [static fn (): mixed => array_column($nextRollback136()['suppressed_returning_rows'], 'option_name'), ['active_plugins']],
    'next rollback final table is current phase output' => [static fn (): mixed => array_column($nextRollback136()['tables']['main.wp_options'], 'option_name'), ['siteurl', 'home', 'blogname']],
    'next rollback visible source names rolled back next' => [static fn (): mixed => $nextRollback136()['source_transition']['visible_source'], 'main@cookie-136-next:rolled-back'],
    'next rollback phases' => [static fn (): mixed => $nextRollback136()['rolled_back_phases'], ['next']],
    'next rollback stream flags' => [static fn (): mixed => array_column($nextRollback136()['attempted_source_stream'], 'admitted'), [true, true, false]],

    'both rollback status' => [static fn (): mixed => $bothRollback136()['status'], 'current-next-view-trigger-savepoints-rolled-back'],
    'both rollback current admitted false' => [static fn (): mixed => $bothRollback136()['current_source_admitted'], false],
    'both rollback next admitted false' => [static fn (): mixed => $bothRollback136()['next_source_admitted'], false],
    'both rollback returning rows empty' => [static fn (): mixed => $bothRollback136()['returning_rows'], []],
    'both rollback suppressed rows include current and next attempts' => [static fn (): mixed => array_column($bothRollback136()['suppressed_returning_rows'], 'option_name'), ['home', 'active_plugins']],
    'both rollback final table restores seed only' => [static fn (): mixed => array_column($bothRollback136()['tables']['main.wp_options'], 'option_name'), ['siteurl']],

    'star returning current row preserved' => [static fn (): mixed => $star136()['returning_rows'][0]['option_name'], 'star_current'],
    'star returning next row preserved' => [static fn (): mixed => $star136()['returning_rows'][1]['option_value'], 'next'],
    'star stream phases' => [static fn (): mixed => array_column($star136()['attempted_source_stream'], 'phase'), ['current', 'next']],
    'star final table rows' => [static fn (): mixed => array_slice(array_column($star136()['tables']['main.wp_options'], 'option_name'), -2), ['star_current', 'star_next']],

    'bad current token rejected' => [static fn (): mixed => $run136(['current_source' => 'bad token']), InvalidArgumentException::class],
    'bad next token rejected' => [static fn (): mixed => $run136(['next_source' => 'bad token']), InvalidArgumentException::class],
    'empty current rows rejected' => [static fn (): mixed => $run136([], []), InvalidArgumentException::class],
    'empty next rows rejected' => [static fn (): mixed => $run136([], null, []), InvalidArgumentException::class],
    'missing returning column rejected' => [static fn (): mixed => $run136([], null, null, ['missing']), InvalidArgumentException::class],
];

foreach ($cases136 as $name => [$callback, $expected]) {
    $tests['trigger returning savepoint view current source next136 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

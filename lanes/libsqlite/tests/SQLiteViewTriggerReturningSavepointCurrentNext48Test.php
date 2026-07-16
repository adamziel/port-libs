<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewTriggerReturningSavepointPlan;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = static function () use ($record): SQLiteAttachedSchemaCatalog {
    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, old_value text, new_value text)', 2),
        $record('view', 'active_options', 'active_options', 0, "CREATE VIEW active_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 3),
        $record('trigger', 'active_options_insert_current', 'active_options', 0, "CREATE TRIGGER active_options_insert_current INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'view-insert', new.option_value); SELECT new.option_id, new.option_name; END", 4),
        $record('trigger', 'active_options_insert_rollback', 'active_options', 0, "CREATE TRIGGER active_options_insert_rollback INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, 'yes'); INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'view-insert', new.option_value); INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'rollback-current-savepoint', 'rollback'); END", 5),
    ], [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 6),
        $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, temp_name text)', 7),
        $record('view', 'active_options', 'active_options', null, 'CREATE TEMP VIEW active_options AS SELECT option_id, temp_name, option_value FROM temp.wp_options', 8),
        $record('trigger', 'temp_active_insert_current', 'active_options', null, "CREATE TEMP TRIGGER temp_active_insert_current INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(option_id, temp_name, option_value) VALUES(new.option_id, new.temp_name, new.option_value); INSERT INTO wp_option_audit(option_id, label, temp_name) VALUES(new.option_id, 'temp-view', new.temp_name); SELECT new.option_id, new.temp_name; END", 9),
    ]);

    $catalog->attach('site', '/srv/site.sqlite', [
        $record('table', 'wp_options', 'wp_options', 20, 'CREATE TABLE site.wp_options(blog_id integer, option_name text, option_value text)', 10),
        $record('table', 'wp_option_audit', 'wp_option_audit', 21, 'CREATE TABLE site.wp_option_audit(blog_id integer, label text, option_name text)', 11),
        $record('view', 'active_options', 'active_options', 0, 'CREATE VIEW site.active_options AS SELECT blog_id, option_name, option_value FROM wp_options', 12),
        $record('trigger', 'site_active_insert_current', 'active_options', 0, "CREATE TRIGGER site_active_insert_current INSTEAD OF INSERT ON active_options BEGIN INSERT INTO wp_options(blog_id, option_name, option_value) VALUES(new.blog_id, new.option_name, new.option_value); INSERT INTO site.wp_option_audit(blog_id, label, option_name) VALUES(new.blog_id, 'site-view', new.option_name); SELECT new.blog_id, new.option_name; END", 13),
    ]);

    return $catalog;
};

$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);
$tables = [
    'main.wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ],
    'main.wp_option_audit' => [
        ['option_id' => 1, 'label' => 'seed', 'new_value' => 'https://old.test'],
    ],
    'temp.wp_options' => [],
    'temp.wp_option_audit' => [],
    'site.wp_options' => [],
    'site.wp_option_audit' => [],
];
$newMain = ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://new.test'];
$newTemp = ['option_id' => 8, 'temp_name' => 'plugin_cache', 'option_value' => '{"enabled":true}'];
$newSite = ['blog_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Network Site'];
$options = [
    'page_size' => 512,
    'savepoint_page_images' => [2 => $page('before-main'), 3 => $page('before-audit')],
    'dirty_pages' => [2 => $page('dirty-main'), 3 => $page('dirty-audit'), 4 => $page('dirty-overflow')],
    'wal_start_frame' => 4,
    'wal_frames' => [
        ['frame_index' => 5, 'page_number' => 2],
        ['frame_index' => 6, 'page_number' => 3, 'commit_frame' => true],
    ],
];

$run = static fn (string $trigger, array $row, array $returning = ['*'], array $extraOptions = []) => SQLiteViewTriggerReturningSavepointPlan::insertIntoView(
    $catalog(),
    $trigger,
    $tables,
    $row,
    'view_insert',
    $returning,
    $extraOptions + $options,
);
$main = static fn () => $run('active_options_insert_current', $newMain, ['option_id', 'option_name', 'value' => 'option_value']);
$rollback = static fn () => $run('active_options_insert_rollback', $newMain, ['option_id', 'option_name']);
$wildcard = static fn () => $run('active_options_insert_current', $newMain);
$computed = static fn () => $run('active_options_insert_current', $newMain, [
    'label' => static fn (array $row): string => $row['option_name'] . ':' . $row['option_value'],
    'id' => 'option_id',
]);
$temp = static fn () => $run('temp_active_insert_current', $newTemp, ['option_id', 'temp_name']);
$site = static fn () => $run('site.site_active_insert_current', $newSite, ['blog_id', 'option_name']);

$cases = [
    'main commits one view row returning' => [static fn (): mixed => $main()['returning_rows'], [['option_id' => 2, 'option_name' => 'home', 'value' => 'https://new.test']]],
    'main attempted returning matches committed' => [static fn (): mixed => $main()['attempted_returning_rows'], $main()['returning_rows']],
    'main returning columns preserve aliases' => [static fn (): mixed => $main()['returning_columns'], ['option_id', 'option_name', 'value']],
    'main change count includes two trigger writes' => [static fn (): mixed => $main()['changes'], 2],
    'main has no rollback' => [static fn (): mixed => $main()['rolled_back_to_savepoint'], false],
    'main rollback reason empty' => [static fn (): mixed => $main()['rollback_reason'], null],
    'main rollback pages empty on success' => [static fn (): mixed => $main()['rollback_page_numbers'], []],
    'main rollback wal frame zero on success' => [static fn (): mixed => $main()['rollback_to_wal_frame'], 0],
    'main discarded frames empty on success' => [static fn (): mixed => $main()['discarded_wal_frames'], []],
    'main writes by schema count' => [static fn (): mixed => $main()['writes_by_schema'], ['main' => 2]],
    'main read count from select body' => [static fn (): mixed => $main()['read_count'], 1],
    'main operation kinds preserve trigger order' => [static fn (): mixed => array_column($main()['operations'], 'kind'), ['insert', 'insert', 'select']],
    'main first operation targets wp_options' => [static fn (): mixed => $main()['operations'][0]['table'], 'wp_options'],
    'main first operation row autoload literal' => [static fn (): mixed => $main()['operations'][0]['row']['autoload'], 'yes'],
    'main second operation targets audit' => [static fn (): mixed => $main()['operations'][1]['table'], 'wp_option_audit'],
    'main second operation row uses new value' => [static fn (): mixed => $main()['operations'][1]['row']['new_value'], 'https://new.test'],
    'main select operation emits current row values' => [static fn (): mixed => $main()['operations'][2]['values'], [2, 'home']],
    'main table gets inserted option' => [static fn (): mixed => array_column($main()['tables']['main.wp_options'], 'option_name'), ['siteurl', 'home']],
    'main table inserted autoload yes' => [static fn (): mixed => $main()['tables']['main.wp_options'][1]['autoload'], 'yes'],
    'main audit table gets trigger audit' => [static fn (): mixed => array_column($main()['tables']['main.wp_option_audit'], 'label'), ['seed', 'view-insert']],
    'main temp table unchanged' => [static fn (): mixed => $main()['tables']['temp.wp_options'], []],
    'main dependencies include view trigger' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger-yield', $main()['dependencies'], true), true],
    'main dependencies include returning' => [static fn (): mixed => in_array('sqlite-returning-current-row', $main()['dependencies'], true), true],
    'main dependencies include savepoint' => [static fn (): mixed => in_array('sqlite-savepoint-current-rollback', $main()['dependencies'], true), true],

    'wildcard returning exposes view input row' => [static fn (): mixed => $wildcard()['returning_rows'][0], $newMain],
    'wildcard returning columns advertise star' => [static fn (): mixed => $wildcard()['returning_columns'], ['*']],
    'computed returning label works' => [static fn (): mixed => $computed()['returning_rows'][0]['label'], 'home:https://new.test'],
    'computed returning id alias works' => [static fn (): mixed => $computed()['returning_rows'][0]['id'], 2],
    'computed returning columns preserve computed aliases' => [static fn (): mixed => $computed()['returning_columns'], ['label', 'id']],

    'rollback clears committed returning rows' => [static fn (): mixed => $rollback()['returning_rows'], []],
    'rollback preserves attempted returning' => [static fn (): mixed => $rollback()['attempted_returning_rows'], [['option_id' => 2, 'option_name' => 'home']]],
    'rollback clears changes' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback marks current savepoint' => [static fn (): mixed => $rollback()['rolled_back_to_savepoint'], true],
    'rollback reason is trigger raise' => [static fn (): mixed => $rollback()['rollback_reason'], 'view-trigger-raise-rollback-current-savepoint'],
    'rollback restores original main options' => [static fn (): mixed => array_column($rollback()['tables']['main.wp_options'], 'option_name'), ['siteurl']],
    'rollback restores original audit table' => [static fn (): mixed => array_column($rollback()['tables']['main.wp_option_audit'], 'label'), ['seed']],
    'rollback includes triggering writes through rollback row' => [static fn (): mixed => array_column($rollback()['operations'], 'kind'), ['insert', 'insert', 'insert']],
    'rollback reason row is retained in attempted operations' => [static fn (): mixed => $rollback()['operations'][2]['row']['label'], 'rollback-current-savepoint'],
    'rollback pages include dirty page images' => [static fn (): mixed => $rollback()['rollback_page_numbers'], [2, 3, 4]],
    'rollback truncates wal to savepoint prefix' => [static fn (): mixed => $rollback()['rollback_to_wal_frame'], 4],
    'rollback discards savepoint wal frames' => [static fn (): mixed => array_column($rollback()['discarded_wal_frames'], 'frame_index'), [5, 6]],
    'rollback preserves commit frame flag' => [static fn (): mixed => $rollback()['discarded_wal_frames'][1]['commit_frame'], true],
    'rollback write count includes attempted writes' => [static fn (): mixed => $rollback()['writes_by_schema'], ['main' => 3]],
    'rollback select body is not reached after raise row' => [static fn (): mixed => $rollback()['read_count'], 0],

    'temp trigger resolves unqualified writes to temp' => [static fn (): mixed => $temp()['writes_by_schema'], ['temp' => 2]],
    'temp trigger inserts temp option' => [static fn (): mixed => $temp()['tables']['temp.wp_options'][0]['temp_name'], 'plugin_cache'],
    'temp trigger inserts temp audit' => [static fn (): mixed => $temp()['tables']['temp.wp_option_audit'][0]['label'], 'temp-view'],
    'temp trigger leaves main options unchanged' => [static fn (): mixed => array_column($temp()['tables']['main.wp_options'], 'option_name'), ['siteurl']],
    'temp trigger returning uses temp columns' => [static fn (): mixed => $temp()['returning_rows'][0], ['option_id' => 8, 'temp_name' => 'plugin_cache']],
    'temp trigger select values use temp row' => [static fn (): mixed => $temp()['operations'][2]['values'], [8, 'plugin_cache']],

    'attached trigger writes attached schema' => [static fn (): mixed => $site()['writes_by_schema'], ['site' => 2]],
    'attached trigger inserts site option' => [static fn (): mixed => $site()['tables']['site.wp_options'][0]['option_name'], 'blogname'],
    'attached trigger inserts site audit' => [static fn (): mixed => $site()['tables']['site.wp_option_audit'][0]['label'], 'site-view'],
    'attached trigger returning uses attached row' => [static fn (): mixed => $site()['returning_rows'][0], ['blog_id' => 3, 'option_name' => 'blogname']],
    'attached trigger select values use attached row' => [static fn (): mixed => $site()['operations'][2]['values'], [3, 'blogname']],
    'attached trigger leaves main and temp unchanged' => [static fn (): mixed => [$site()['tables']['main.wp_options'], $site()['tables']['temp.wp_options']], [$tables['main.wp_options'], []]],

    'empty savepoint rejected' => [static fn (): mixed => SQLiteViewTriggerReturningSavepointPlan::insertIntoView($catalog(), 'active_options_insert_current', $tables, $newMain, ''), InvalidArgumentException::class],
    'missing target table rejected' => [static fn (): mixed => SQLiteViewTriggerReturningSavepointPlan::insertIntoView($catalog(), 'active_options_insert_current', ['main.wp_options' => []], $newMain, 'view_insert'), InvalidArgumentException::class],
    'bad table key rejected' => [static fn (): mixed => SQLiteViewTriggerReturningSavepointPlan::insertIntoView($catalog(), 'active_options_insert_current', ['wp_options' => []], $newMain, 'view_insert'), InvalidArgumentException::class],
    'missing returning column rejected' => [static fn (): mixed => $run('active_options_insert_current', $newMain, ['missing']), InvalidArgumentException::class],
    'missing aliased returning expression rejected' => [static fn (): mixed => $run('active_options_insert_current', $newMain, ['x' => 'missing']), InvalidArgumentException::class],
    'bad page size rejected' => [static fn (): mixed => $run('active_options_insert_rollback', $newMain, ['option_id'], ['page_size' => 0]), InvalidArgumentException::class],
    'bad dirty page key rejected' => [static fn (): mixed => $run('active_options_insert_rollback', $newMain, ['option_id'], ['dirty_pages' => ['x' => $page('bad')]]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['view trigger returning savepoint current next48 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

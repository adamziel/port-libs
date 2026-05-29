<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueSavepointUpsertCurrentSourceNextPlan;

$tests = [];

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 18, 'option_value' => 'https://home.test', 'revision' => 1],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'blogname', 'autoload' => 'no', 'status' => 'archived', 'bytes' => 9, 'option_value' => 'Old Blog', 'revision' => 3],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 22, 'option_value' => 'https://network.test', 'revision' => 2],
];
$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name'], ['option_id']];

$updateWhereSql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value, revision) VALUES (5, 1, 'siteurl', 'no', 'incoming', 5, 'https://new.test', 4) ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value, revision) = (excluded.autoload, 'merged', bytes + excluded.bytes, option_value || ':' || excluded.option_value, revision + excluded.revision) WHERE excluded.revision > revision RETURNING option_id, option_name, autoload, status, bytes, option_value, revision";
$skipWhereSql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value, revision) VALUES (6, 1, 'home', 'no', 'incoming', 5, 'https://skip.test', 1) ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value, revision) = (excluded.autoload, 'merged', bytes + excluded.bytes, excluded.option_value, revision + excluded.revision) WHERE excluded.revision > revision RETURNING option_id, option_name, status, revision";
$doNothingSql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value, revision) VALUES (7, 1, 'blogname', 'yes', 'incoming', 3, 'New Blog', 5) ON CONFLICT (blog_id, option_name) DO NOTHING RETURNING option_id, option_name, status";
$partialUpdateSql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value, revision) VALUES (8, 2, 'siteurl', 'no', 'live', 4, 'https://network-new.test', 2) ON CONFLICT (blog_id, option_name) WHERE status = 'live' DO UPDATE SET (autoload, status, bytes, option_value, revision) = (excluded.autoload, 'partial', bytes + excluded.bytes, excluded.option_value, revision + excluded.revision) RETURNING option_id, blog_id, option_name, status, bytes, revision";
$partialInsertSql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value, revision) VALUES (9, 3, 'siteurl', 'no', 'draft', 4, 'https://draft.test', 1) ON CONFLICT (blog_id, option_name) WHERE status = 'live' DO UPDATE SET (status, bytes) = ('partial', bytes + excluded.bytes) RETURNING option_id, blog_id, option_name, status, bytes";
$nullWhereSql = "INSERT INTO wp_options (option_id, blog_id, option_name, autoload, status, bytes, option_value, revision) VALUES (10, 1, 'siteurl', 'no', 'incoming', 5, NULL, 8) ON CONFLICT (blog_id, option_name) DO UPDATE SET (status, option_value) = ('null-merged', excluded.option_value) WHERE excluded.option_value IS NOT NULL RETURNING option_id, option_name, status, option_value";

$plan = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute(
    $tables,
    [$updateWhereSql, $skipWhereSql, $doNothingSql, $partialUpdateSql, $partialInsertSql],
    $unique,
    'wp_options_rowvalue_conflict_current_next134'
);
$nullSkip = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, [$nullWhereSql], $unique, 'wp_options_rowvalue_null_where_next134');
$parsedNothing = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::parse($doNothingSql);
$parsedPartial = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::parse($partialUpdateSql);
$parsedUpdateWhere = static fn (): array => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::parse($updateWhereSql);

$cases = [
    'parse do nothing action' => [static fn (): mixed => $parsedNothing()['action'], 'nothing'],
    'parse do nothing assignments empty' => [static fn (): mixed => $parsedNothing()['assignments'], []],
    'parse do nothing returning columns' => [static fn (): mixed => $parsedNothing()['returning'], ['option_id', 'option_name', 'status']],
    'parse partial conflict where' => [static fn (): mixed => $parsedPartial()['conflict_where'], "status = 'live'"],
    'parse update where expression' => [static fn (): mixed => $parsedUpdateWhere()['update_where'], 'excluded.revision > revision'],
    'parse update action remains update' => [static fn (): mixed => $parsedUpdateWhere()['action'], 'update'],
    'status released' => [static fn (): mixed => $plan()['status'], 'released'],
    'executed five statements' => [static fn (): mixed => count($plan()['executed_statements']), 5],
    'actions include update skip nothing update insert' => [static fn (): mixed => array_column($plan()['executed_statements'], 'action'), ['update', 'where-skipped', 'nothing', 'update', 'insert']],
    'changes count excludes skipped conflicts' => [static fn (): mixed => $plan()['changes'], 3],
    'attempted changes exclude skipped conflicts' => [static fn (): mixed => $plan()['attempted_changes'], 3],
    'yield stream keeps five statement ordinals' => [static fn (): mixed => array_column($plan()['yielded_returning'], 'ordinal'), [0, 1, 2, 3, 4]],
    'yield stream omits where skipped rows' => [static fn (): mixed => $plan()['yielded_returning'][1]['rows'], []],
    'yield stream omits do nothing rows' => [static fn (): mixed => $plan()['yielded_returning'][2]['rows'], []],
    'first returning uses current rowid' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['option_id'], 1],
    'first returning copies excluded autoload' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['autoload'], 'no'],
    'first returning sets merged status' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['status'], 'merged'],
    'first returning adds bytes from current and excluded' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['bytes'], 25],
    'first returning concatenates values from current and excluded' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['option_value'], 'https://old.test:https://new.test'],
    'first returning revision sees current row before update' => [static fn (): mixed => $plan()['yielded_returning'][0]['rows'][0]['revision'], 5],
    'where skipped row recorded' => [static fn (): mixed => $plan()['skipped_rows'][0]['reason'], 'where-skipped'],
    'where skipped input row preserved' => [static fn (): mixed => $plan()['skipped_rows'][0]['row']['option_name'], 'home'],
    'do nothing row recorded' => [static fn (): mixed => $plan()['skipped_rows'][1]['reason'], 'nothing'],
    'do nothing input row preserved' => [static fn (): mixed => $plan()['skipped_rows'][1]['row']['option_name'], 'blogname'],
    'skipped home row remains live' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[2], 'live'],
    'do nothing blogname row remains archived' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[3], 'archived'],
    'partial conflict update returns network row' => [static fn (): mixed => $plan()['yielded_returning'][3]['rows'][0]['option_id'], 4],
    'partial conflict update status' => [static fn (): mixed => $plan()['yielded_returning'][3]['rows'][0]['status'], 'partial'],
    'partial conflict update bytes' => [static fn (): mixed => $plan()['yielded_returning'][3]['rows'][0]['bytes'], 26],
    'partial conflict update revision' => [static fn (): mixed => $plan()['yielded_returning'][3]['rows'][0]['revision'], 4],
    'partial false candidate inserts' => [static fn (): mixed => $plan()['yielded_returning'][4]['rows'][0]['option_id'], 9],
    'partial false candidate keeps draft status' => [static fn (): mixed => $plan()['yielded_returning'][4]['rows'][0]['status'], 'draft'],
    'partial false candidate increases row count' => [static fn (): mixed => count($plan()['current_source_tables']['wp_options']), 5],
    'updated rows include only changed conflicts' => [static fn (): mixed => array_column(array_column($plan()['updated_rows'], 'row'), 'option_name'), ['siteurl', 'siteurl']],
    'inserted rows include partial false candidate' => [static fn (): mixed => $plan()['inserted_rows'][0]['row']['option_id'], 9],
    'conflicts include changed and skipped conflicts' => [static fn (): mixed => array_column($plan()['conflicts'], 'key'), ['1|siteurl', '1|home', '1|blogname', '2|siteurl']],
    'conflict rows preserve source ordinals' => [static fn (): mixed => array_column($plan()['conflicts'], 'ordinal'), [0, 1, 2, 3]],
    'current source siteurl revision updated' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'revision', 'option_id')[1], 5],
    'current source network siteurl value updated' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[4], 'https://network-new.test'],
    'savepoint image still has original siteurl revision' => [static fn (): mixed => array_column($plan()['savepoint_image_tables']['wp_options'], 'revision', 'option_id')[1], 1],
    'next source equals released current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'null update where status released' => [static fn (): mixed => $nullSkip()['status'], 'released'],
    'null update where skips conflict' => [static fn (): mixed => $nullSkip()['executed_statements'][0]['action'], 'where-skipped'],
    'null update where emits no returning rows' => [static fn (): mixed => $nullSkip()['yielded_returning'][0]['rows'], []],
    'null update where records one skipped row' => [static fn (): mixed => count($nullSkip()['skipped_rows']), 1],
    'null update where changes zero' => [static fn (): mixed => $nullSkip()['changes'], 0],
    'null update where keeps current value' => [static fn (): mixed => array_column($nullSkip()['current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test'],
    'dependency marker includes upsert' => [static fn (): mixed => in_array('sqlite-insert-on-conflict-do-update', $plan()['dependencies'], true), true],
    'dependency marker includes row value assignment' => [static fn (): mixed => in_array('sqlite-row-value-upsert-assignment', $plan()['dependencies'], true), true],
    'dependency marker includes savepoint current source' => [static fn (): mixed => in_array('sqlite-savepoint-current-source-upsert-rollback', $plan()['dependencies'], true), true],
    'malformed empty do update where rejected' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::parse("INSERT INTO wp_options (option_id, blog_id, option_name) VALUES (10, 1, 'x') ON CONFLICT (blog_id, option_name) DO UPDATE SET (status, bytes) = ('x', 1) WHERE RETURNING option_id"), InvalidArgumentException::class],
    'malformed unsupported where rolls back' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, ["INSERT INTO wp_options (option_id, blog_id, option_name, revision) VALUES (11, 1, 'siteurl', 9) ON CONFLICT (blog_id, option_name) DO UPDATE SET (revision) = (excluded.revision) WHERE revision BETWEEN 1 AND 9 RETURNING option_id"], $unique)['rollback_reason'], 'SQLite row-value UPSERT WHERE clause is unsupported: revision BETWEEN 1'],
    'malformed do nothing unknown target rolls back' => [static fn (): mixed => SQLiteRowValueSavepointUpsertCurrentSourceNextPlan::execute($tables, ["INSERT INTO wp_options (option_id, blog_id, option_name) VALUES (12, 1, 'siteurl') ON CONFLICT (option_name) DO NOTHING RETURNING option_id"], $unique)['status'], 'rolled-back-to-savepoint'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue upsert returning conflict current source next134 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

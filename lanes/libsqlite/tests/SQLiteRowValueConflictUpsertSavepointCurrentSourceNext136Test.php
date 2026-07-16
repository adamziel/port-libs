<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan;

$tests = [];

$rows136 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => 'theme_mods', 'autoload' => 'no', 'status' => 'draft', 'bytes' => 10, 'option_value' => 'a:0:{}'],
    ['option_id' => 4, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'network', 'bytes' => 25, 'option_value' => 'https://network.test'],
];
$tables136 = ['wp_options' => $rows136];
$unique136 = [['blog_id', 'option_name'], ['option_id']];

$moveSql136 = "INSERT INTO wp_options (option_id, blog_id, option_name, move_name, autoload, status, bytes, option_value) VALUES (10, 1, 'siteurl', 'plugin_cache', 'no', 'incoming', 5, 'cache-a') ON CONFLICT (blog_id, option_name) DO UPDATE SET (option_name, autoload, status, bytes, option_value) = (excluded.move_name, excluded.autoload, 'moved', bytes + excluded.bytes, option_value || ':' || excluded.option_value) RETURNING option_id, blog_id, option_name, autoload, status, bytes, option_value";
$hitMovedSql136 = "INSERT INTO wp_options (option_id, blog_id, option_name, move_name, autoload, status, bytes, option_value) VALUES (11, 1, 'plugin_cache', 'plugin_cache', 'yes', 'incoming', 3, 'cache-b') ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value) = (excluded.autoload, 'refreshed', bytes + excluded.bytes, option_value || ':' || excluded.option_value) RETURNING option_id, blog_id, option_name, autoload, status, bytes, option_value";
$insertSql136 = "INSERT INTO wp_options (option_id, blog_id, option_name, move_name, autoload, status, bytes, option_value) VALUES (12, 1, 'new_plugin', 'new_plugin', 'no', 'inserted', 7, 'enabled') ON CONFLICT (blog_id, option_name) DO UPDATE SET (autoload, status, bytes, option_value) = (excluded.autoload, 'updated', bytes + excluded.bytes, excluded.option_value) RETURNING option_id, blog_id, option_name, autoload, status, bytes, option_value";
$duplicateSql136 = "INSERT INTO wp_options (option_id, blog_id, option_name, move_name, autoload, status, bytes, option_value) VALUES (13, 1, 'home', 'theme_mods', 'no', 'incoming', 4, 'bad') ON CONFLICT (blog_id, option_name) DO UPDATE SET (option_name, status, bytes, option_value) = (excluded.move_name, 'duplicate-key', bytes + excluded.bytes, excluded.option_value) RETURNING option_id, blog_id, option_name, status, bytes";
$nullSql136 = "INSERT INTO wp_options (option_id, blog_id, option_name, move_name, autoload, status, bytes, option_value) VALUES (14, 1, NULL, 'null-move', 'no', 'null-key', 1, 'anonymous') ON CONFLICT (blog_id, option_name) DO UPDATE SET (option_name, status) = (excluded.move_name, 'updated') RETURNING option_id, blog_id, option_name, status";

$released136 = static fn (): array => SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan::execute(
    $tables136,
    [$moveSql136, $hitMovedSql136, $insertSql136],
    $unique136,
    ['blog_id', 'option_name'],
    'wp_import_conflict_move',
);
$rolled136 = static fn (): array => SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan::execute(
    $tables136,
    [$moveSql136, $hitMovedSql136, $duplicateSql136],
    $unique136,
    ['blog_id', 'option_name'],
    'wp_import_conflict_move',
);
$null136 = static fn (): array => SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan::execute(
    $tables136,
    [$nullSql136],
    $unique136,
    ['blog_id', 'option_name'],
    'wp_import_null_conflict',
);

$cases136 = [
    'released status records current source' => [static fn (): mixed => $released136()['status'], 'rowvalue-conflict-upsert-savepoint-released-current-source-next136'],
    'released savepoint is preserved' => [static fn (): mixed => $released136()['savepoint'], 'wp_import_conflict_move'],
    'released flag is false' => [static fn (): mixed => $released136()['rolled_back'], false],
    'released table name is detected' => [static fn (): mixed => $released136()['table'], 'wp_options'],
    'released conflict key columns are recorded' => [static fn (): mixed => $released136()['conflict_key_columns'], ['blog_id', 'option_name']],
    'released actions are update update insert' => [static fn (): mixed => $released136()['statement_actions'], ['update', 'update', 'insert']],
    'released first statement conflicts on original key' => [static fn (): mixed => $released136()['statement_conflict_keys'][0], '1|siteurl'],
    'released second statement conflicts on moved key' => [static fn (): mixed => $released136()['statement_conflict_keys'][1], '1|plugin_cache'],
    'released insert has no conflict key' => [static fn (): mixed => $released136()['statement_conflict_keys'][2], null],
    'released movement count is one' => [static fn (): mixed => count($released136()['moved_conflict_keys']), 1],
    'released movement row id is original siteurl' => [static fn (): mixed => $released136()['moved_conflict_keys'][0]['row_id'], 1],
    'released movement before key is original conflict key' => [static fn (): mixed => $released136()['moved_conflict_keys'][0]['before_key'], '1|siteurl'],
    'released movement after key is plugin cache' => [static fn (): mixed => $released136()['moved_conflict_keys'][0]['after_key'], '1|plugin_cache'],
    'released movement before values project key' => [static fn (): mixed => $released136()['moved_conflict_keys'][0]['before_values'], ['blog_id' => 1, 'option_name' => 'siteurl']],
    'released movement after values project key' => [static fn (): mixed => $released136()['moved_conflict_keys'][0]['after_values'], ['blog_id' => 1, 'option_name' => 'plugin_cache']],
    'released current-source movement matches attempted movement' => [static fn (): mixed => $released136()['current_source_moved_conflict_keys'], $released136()['moved_conflict_keys']],
    'released matched moved key points to second statement' => [static fn (): mixed => $released136()['matched_moved_conflict_keys'], [['ordinal' => 1, 'row_id' => 1, 'key' => '1|plugin_cache']]],
    'released changes include three statements' => [static fn (): mixed => $released136()['changes'], 3],
    'released attempted changes include three statements' => [static fn (): mixed => $released136()['attempted_changes'], 3],
    'released current rows include inserted plugin' => [static fn (): mixed => array_column($released136()['current_source_tables']['wp_options'], 'option_name', 'option_id')[12], 'new_plugin'],
    'released moved row keeps original rowid' => [static fn (): mixed => array_column($released136()['current_source_tables']['wp_options'], 'option_name', 'option_id')[1], 'plugin_cache'],
    'released moved row status reflects second upsert' => [static fn (): mixed => array_column($released136()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'refreshed'],
    'released moved row autoload reflects second excluded row' => [static fn (): mixed => array_column($released136()['current_source_tables']['wp_options'], 'autoload', 'option_id')[1], 'yes'],
    'released moved row bytes accumulate both conflicts' => [static fn (): mixed => array_column($released136()['current_source_tables']['wp_options'], 'bytes', 'option_id')[1], 32],
    'released moved row value concatenates in statement order' => [static fn (): mixed => array_column($released136()['current_source_tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test:cache-a:cache-b'],
    'released original home row remains live' => [static fn (): mixed => array_column($released136()['current_source_tables']['wp_options'], 'status', 'option_id')[2], 'live'],
    'released original theme row remains draft' => [static fn (): mixed => array_column($released136()['current_source_tables']['wp_options'], 'status', 'option_id')[3], 'draft'],
    'released savepoint image retains original siteurl key' => [static fn (): mixed => array_column($released136()['savepoint_image_tables']['wp_options'], 'option_name', 'option_id')[1], 'siteurl'],
    'released savepoint image omits inserted row' => [static fn (): mixed => in_array(12, array_column($released136()['savepoint_image_tables']['wp_options'], 'option_id'), true), false],
    'released yielding names preserve update update insert order' => [static fn (): mixed => array_column(array_merge(...array_column($released136()['yielded_returning'], 'rows')), 'option_name'), ['plugin_cache', 'plugin_cache', 'new_plugin']],
    'released yielding statuses preserve final statement images' => [static fn (): mixed => array_column(array_merge(...array_column($released136()['yielded_returning'], 'rows')), 'status'), ['moved', 'refreshed', 'inserted']],
    'released executed conflict target is composite' => [static fn (): mixed => $released136()['executed_statements'][0]['conflict_target'], ['blog_id', 'option_name']],
    'released executed input carries move name' => [static fn (): mixed => $released136()['executed_statements'][0]['input_row']['move_name'], 'plugin_cache'],
    'released dependencies include moved-key marker' => [static fn (): mixed => in_array('sqlite-row-value-conflict-key-current-source-upsert', $released136()['dependencies'], true), true],
    'released dependencies include savepoint restore marker' => [static fn (): mixed => in_array('sqlite-upsert-savepoint-rollback-restores-moved-conflict-key', $released136()['dependencies'], true), true],

    'rollback status records current source' => [static fn (): mixed => $rolled136()['status'], 'rowvalue-conflict-upsert-savepoint-rolled-back-current-source-next136'],
    'rollback flag is true' => [static fn (): mixed => $rolled136()['rolled_back'], true],
    'rollback statement ordinal is duplicate key update' => [static fn (): mixed => $rolled136()['rollback_statement_ordinal'], 2],
    'rollback reason names moved composite key' => [static fn (): mixed => $rolled136()['rollback_reason'], 'SQLite UPSERT unique constraint failed after DO UPDATE: blog_id,option_name=1|theme_mods'],
    'rollback changes reset to zero' => [static fn (): mixed => $rolled136()['changes'], 0],
    'rollback attempted changes count successful prefix' => [static fn (): mixed => $rolled136()['attempted_changes'], 2],
    'rollback yielded rows keep successful prefix only' => [static fn (): mixed => array_column($rolled136()['yielded_returning'], 'action'), ['update', 'update']],
    'rollback attempted returning count is two' => [static fn (): mixed => count($rolled136()['attempted_returning']), 2],
    'rollback current source restores siteurl key' => [static fn (): mixed => array_column($rolled136()['current_source_tables']['wp_options'], 'option_name', 'option_id')[1], 'siteurl'],
    'rollback current source restores siteurl status' => [static fn (): mixed => array_column($rolled136()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'live'],
    'rollback current source keeps home key' => [static fn (): mixed => array_column($rolled136()['current_source_tables']['wp_options'], 'option_name', 'option_id')[2], 'home'],
    'rollback current source has no moved conflict key' => [static fn (): mixed => $rolled136()['current_source_moved_conflict_keys'], []],
    'rollback attempted next source retains moved key prefix' => [static fn (): mixed => array_column($rolled136()['next_source_tables']['wp_options'], 'option_name', 'option_id')[1], 'plugin_cache'],
    'rollback attempted next source retains second update status' => [static fn (): mixed => array_column($rolled136()['next_source_tables']['wp_options'], 'status', 'option_id')[1], 'refreshed'],
    'rollback attempted movement still records prefix movement' => [static fn (): mixed => $rolled136()['moved_conflict_keys'][0]['after_key'], '1|plugin_cache'],
    'rollback matched moved key still points to second statement' => [static fn (): mixed => $rolled136()['matched_moved_conflict_keys'][0]['ordinal'], 1],
    'rollback savepoint image equals original table rows' => [static fn (): mixed => $rolled136()['savepoint_image_tables'], $tables136],
    'rollback conflicting duplicate statement is not in executed list' => [static fn (): mixed => count($rolled136()['executed_statements']), 2],

    'null conflict key inserts instead of moving' => [static fn (): mixed => $null136()['statement_actions'], ['insert']],
    'null conflict key has no movement' => [static fn (): mixed => $null136()['moved_conflict_keys'], []],
    'null conflict key current row count increments' => [static fn (): mixed => count($null136()['current_source_tables']['wp_options']), 5],
    'null conflict key returning preserves null option name' => [static fn (): mixed => $null136()['yielded_returning'][0]['rows'][0]['option_name'], null],
    'null conflict key changes one row' => [static fn (): mixed => $null136()['changes'], 1],

    'malformed empty conflict key columns rejected' => [static fn (): mixed => SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan::execute($tables136, [$moveSql136], $unique136, []), InvalidArgumentException::class],
    'malformed empty savepoint rejected' => [static fn (): mixed => SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan::execute($tables136, [$moveSql136], $unique136, ['blog_id', 'option_name'], '  '), InvalidArgumentException::class],
    'malformed missing conflict key column rejected' => [static fn (): mixed => SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan::execute($tables136, [$moveSql136], $unique136, ['blog_id', 'missing']), InvalidArgumentException::class],
    'malformed missing row id rejected' => [static fn (): mixed => SQLiteRowValueConflictUpsertSavepointCurrentSourceNextPlan::execute(['wp_options' => [['blog_id' => 1, 'option_name' => 'x']]], [$moveSql136], $unique136, ['blog_id', 'option_name']), InvalidArgumentException::class],
];

foreach ($cases136 as $name => [$callback, $expected]) {
    $tests['rowvalue conflict upsert savepoint current source next136 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;

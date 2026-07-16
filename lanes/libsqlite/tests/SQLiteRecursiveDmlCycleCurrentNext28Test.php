<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveDmlCurrentSource;

$tests = [];

$edges = [
    ['src' => 1, 'dst' => 2],
    ['src' => 2, 'dst' => 3],
    ['src' => 3, 'dst' => 1],
    ['src' => 3, 'dst' => 4],
    ['src' => 4, 'dst' => 4],
];

$options = [
    ['setting_id' => 1, 'key_name' => 'site_endpoint', 'key_value' => 'old', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'old', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'site_title', 'key_value' => 'old', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => 'cache_seed', 'key_value' => 'old', 'load_policy' => 'no'],
    ['setting_id' => 9, 'key_name' => 'orphan', 'key_value' => 'old', 'load_policy' => 'no'],
];

$archive = [
    ['setting_id' => 10, 'key_name' => 'existing', 'key_value' => 'keep', 'load_policy' => 'no'],
];

$insertSql = "WITH RECURSIVE walk(id, depth) AS (
    VALUES (:root, 0)
    UNION
    SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < :max_depth
) INSERT INTO archive_settings(setting_id, key_name, key_value, load_policy)
SELECT setting_id + 100, key_name, key_value || ':archived', load_policy
FROM app_settings JOIN walk ON walk.id = app_settings.setting_id
ORDER BY setting_id";
$insertSql = str_replace('SELECT setting_id + 100', 'SELECT DISTINCT setting_id + 100', $insertSql);

$tests['recursive dml current next28 insert select materializes recursive cycle once'] = static function (TestRunner $t) use ($insertSql, $edges, $options, $archive): void {
    $result = SQLiteRecursiveDmlCurrentSource::insertSelect($insertSql, ['edges' => $edges, 'app_settings' => $options, 'archive_settings' => $archive], [':root' => 1, ':max_depth' => 4]);
    $t->same(4, $result['changes']);
    $t->same([10, 101, 102, 103, 104], array_column($result['after'], 'setting_id'));
    $t->same(['site_endpoint', 'home', 'site_title', 'cache_seed'], array_column($result['inserted_rows'], 'key_name'));
    $t->same('old:archived', $result['inserted_rows'][0]['key_value']);
    $t->same('archive_settings', $result['target']);
    $t->same(['setting_id', 'key_name', 'key_value', 'load_policy'], $result['columns']);
    $t->same([], $result['deleted_rows']);
    $t->same([], $result['ignored_rows']);
};

$tests['recursive dml current next28 insert select can ignore recursive duplicate conflicts'] = static function (TestRunner $t) use ($insertSql, $edges, $options): void {
    $archive = [['setting_id' => 101, 'key_name' => 'site_endpoint', 'key_value' => 'already', 'load_policy' => 'yes']];
    $result = SQLiteRecursiveDmlCurrentSource::insertSelect(str_replace('INSERT INTO', 'INSERT OR IGNORE INTO', $insertSql), ['edges' => $edges, 'app_settings' => $options, 'archive_settings' => $archive], [':root' => 1, ':max_depth' => 4], [['setting_id']]);
    $t->same(3, $result['changes']);
    $t->same([101], array_column($result['ignored_rows'], 'setting_id'));
    $t->same([101, 102, 103, 104], array_column($result['after'], 'setting_id'));
    $t->same(['ignore'], [$result['conflict_action']]);
    $t->same(['home', 'site_title', 'cache_seed'], array_column($result['inserted_rows'], 'key_name'));
    $t->same('already', $result['after'][0]['key_value']);
};

$tests['recursive dml current next28 insert select can replace recursive duplicate conflicts'] = static function (TestRunner $t) use ($insertSql, $edges, $options): void {
    $archive = [['setting_id' => 101, 'key_name' => 'site_endpoint', 'key_value' => 'already', 'load_policy' => 'yes']];
    $result = SQLiteRecursiveDmlCurrentSource::insertSelect(str_replace('INSERT INTO', 'INSERT OR REPLACE INTO', $insertSql), ['edges' => $edges, 'app_settings' => $options, 'archive_settings' => $archive], [':root' => 1, ':max_depth' => 4], [['setting_id']]);
    $t->same(4, $result['changes']);
    $t->same(['already'], array_column($result['deleted_rows'], 'key_value'));
    $t->same('old:archived', $result['after'][0]['key_value']);
    $t->same('replace', $result['conflict_action']);
    $t->same([101, 102, 103, 104], array_column($result['inserted_rows'], 'setting_id'));
};

$updateSql = "WITH RECURSIVE walk(id) AS (
    VALUES (?1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id
) UPDATE app_settings SET key_value = walk.id, load_policy = 'cycle' FROM walk WHERE app_settings.setting_id = walk.id";

$tests['recursive dml current next28 update from uses last current recursive match'] = static function (TestRunner $t) use ($updateSql, $edges, $options): void {
    $result = SQLiteRecursiveDmlCurrentSource::updateFrom($updateSql, ['edges' => $edges, 'app_settings' => $options], [1]);
    $t->same(4, $result['changes']);
    $t->same([1, 2, 3, 4], array_column($result['updated_rows'], 'setting_id'));
    $t->same(['cycle', 'cycle', 'cycle', 'cycle', 'no'], array_column($result['after'], 'load_policy'));
    $t->same([1, 2, 3, 4], array_slice(array_column($result['after'], 'key_value'), 0, 4));
    $t->same('app_settings', $result['target']);
    $t->same(['key_value' => 'walk.id', 'load_policy' => "'cycle'"], $result['assignments']);
    $t->same([], $result['deleted_rows']);
};

$tests['recursive dml current next28 update from leaves rows outside cycle unchanged'] = static function (TestRunner $t) use ($updateSql, $edges, $options): void {
    $result = SQLiteRecursiveDmlCurrentSource::updateFrom($updateSql, ['edges' => $edges, 'app_settings' => $options], [4]);
    $t->same(1, $result['changes']);
    $t->same([4], array_column($result['updated_rows'], 'setting_id'));
    $t->same('old', $result['after'][0]['key_value']);
    $t->same('cycle', $result['after'][3]['load_policy']);
    $t->same('old', $result['after'][4]['key_value']);
    $t->same([3], array_column($result['matched_rows'], '__sqlite_update_index'));
};

$tests['recursive dml current next28 update from replace deletes current unique conflict'] = static function (TestRunner $t) use ($edges, $options): void {
    $sql = "WITH RECURSIVE walk(id, new_name) AS (
        VALUES (1, 'orphan')
        UNION
        SELECT edges.dst, 'cycle_' || edges.dst FROM edges JOIN walk ON edges.src = walk.id WHERE walk.id < 2
    ) UPDATE OR REPLACE app_settings SET key_name = walk.new_name FROM walk WHERE app_settings.setting_id = walk.id";
    $result = SQLiteRecursiveDmlCurrentSource::updateFrom($sql, ['edges' => $edges, 'app_settings' => $options], [], [['key_name']]);
    $t->same(2, $result['changes']);
    $t->same(['orphan'], array_column($result['deleted_rows'], 'key_name'));
    $t->same(['orphan', 'cycle_2', 'site_title', 'cache_seed'], array_column($result['after'], 'key_name'));
    $t->same('replace', $result['conflict_action']);
    $t->same([1, 2], array_column($result['updated_rows'], 'setting_id'));
};

$deleteSql = "WITH RECURSIVE walk(id) AS (
    VALUES (1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id
) DELETE FROM app_settings WHERE setting_id IN (SELECT id FROM walk) RETURNING setting_id, key_name ORDER BY setting_id";

$tests['recursive dml current next28 delete returning rewrites recursive in source'] = static function (TestRunner $t) use ($deleteSql, $edges, $options): void {
    $result = SQLiteRecursiveDmlCurrentSource::updateDeleteReturning($deleteSql, ['edges' => $edges, 'app_settings' => $options]);
    $t->same('delete', $result['action']);
    $t->same([1, 2, 3, 4], array_column($result['returning'], 'setting_id'));
    $t->same([9], array_column($result['tables']['app_settings'], 'setting_id'));
    $t->same('app_settings', $result['table']);
    $t->same(['site_endpoint', 'home', 'site_title', 'cache_seed'], array_column($result['returning'], 'key_name'));
};

$tests['recursive dml current next28 delete returning supports not in current source'] = static function (TestRunner $t) use ($edges, $options): void {
    $sql = "WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id)
        DELETE FROM app_settings WHERE setting_id NOT IN (SELECT id FROM walk) RETURNING setting_id, key_name";
    $result = SQLiteRecursiveDmlCurrentSource::updateDeleteReturning($sql, ['edges' => $edges, 'app_settings' => $options]);
    $t->same([9], array_column($result['returning'], 'setting_id'));
    $t->same([1, 2, 3, 4], array_column($result['tables']['app_settings'], 'setting_id'));
    $t->same(1, count($result['returning']));
    $t->same('orphan', $result['returning'][0]['key_name']);
};

$tests['recursive dml current next28 update returning rewrites recursive in source'] = static function (TestRunner $t) use ($edges, $options): void {
    $sql = "WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id)
        UPDATE app_settings SET key_value = key_name || ':visited' WHERE setting_id IN (SELECT id FROM walk) RETURNING setting_id, key_value ORDER BY setting_id";
    $result = SQLiteRecursiveDmlCurrentSource::updateDeleteReturning($sql, ['edges' => $edges, 'app_settings' => $options]);
    $t->same('update', $result['action']);
    $t->same([1, 2, 3, 4], array_column($result['returning'], 'setting_id'));
    $t->same('site_endpoint:visited', $result['returning'][0]['key_value']);
    $t->same('cache_seed:visited', $result['returning'][3]['key_value']);
    $t->same('old', $result['tables']['app_settings'][4]['key_value']);
};

$tests['recursive dml current next28 rejects malformed current source body'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveDmlCurrentSource::updateDeleteReturning('WITH RECURSIVE walk(id) AS (VALUES (1) DELETE FROM app_settings WHERE setting_id IN (SELECT id FROM walk) RETURNING setting_id', ['app_settings' => $options]));
};

$tests['recursive dml current next28 rejects cte table shadowing'] = static function (TestRunner $t) use ($edges, $options): void {
    $sql = "WITH RECURSIVE edges(id) AS (VALUES (1)) DELETE FROM app_settings WHERE setting_id IN (SELECT id FROM edges) RETURNING setting_id";
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveDmlCurrentSource::updateDeleteReturning($sql, ['edges' => $edges, 'app_settings' => $options]));
};

return $tests;

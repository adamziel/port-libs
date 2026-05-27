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
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'old', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'old', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'old', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'option_value' => 'old', 'autoload' => 'no'],
    ['option_id' => 9, 'option_name' => 'orphan', 'option_value' => 'old', 'autoload' => 'no'],
];

$archive = [
    ['option_id' => 10, 'option_name' => 'existing', 'option_value' => 'keep', 'autoload' => 'no'],
];

$insertSql = "WITH RECURSIVE walk(id, depth) AS (
    VALUES (:root, 0)
    UNION
    SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < :max_depth
) INSERT INTO archive_options(option_id, option_name, option_value, autoload)
SELECT option_id + 100, option_name, option_value || ':archived', autoload
FROM wp_options JOIN walk ON walk.id = wp_options.option_id
ORDER BY option_id";
$insertSql = str_replace('SELECT option_id + 100', 'SELECT DISTINCT option_id + 100', $insertSql);

$tests['recursive dml current next28 insert select materializes recursive cycle once'] = static function (TestRunner $t) use ($insertSql, $edges, $options, $archive): void {
    $result = SQLiteRecursiveDmlCurrentSource::insertSelect($insertSql, ['edges' => $edges, 'wp_options' => $options, 'archive_options' => $archive], [':root' => 1, ':max_depth' => 4]);
    $t->same(4, $result['changes']);
    $t->same([10, 101, 102, 103, 104], array_column($result['after'], 'option_id'));
    $t->same(['siteurl', 'home', 'blogname', 'cache_seed'], array_column($result['inserted_rows'], 'option_name'));
    $t->same('old:archived', $result['inserted_rows'][0]['option_value']);
    $t->same('archive_options', $result['target']);
    $t->same(['option_id', 'option_name', 'option_value', 'autoload'], $result['columns']);
    $t->same([], $result['deleted_rows']);
    $t->same([], $result['ignored_rows']);
};

$tests['recursive dml current next28 insert select can ignore recursive duplicate conflicts'] = static function (TestRunner $t) use ($insertSql, $edges, $options): void {
    $archive = [['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'already', 'autoload' => 'yes']];
    $result = SQLiteRecursiveDmlCurrentSource::insertSelect(str_replace('INSERT INTO', 'INSERT OR IGNORE INTO', $insertSql), ['edges' => $edges, 'wp_options' => $options, 'archive_options' => $archive], [':root' => 1, ':max_depth' => 4], [['option_id']]);
    $t->same(3, $result['changes']);
    $t->same([101], array_column($result['ignored_rows'], 'option_id'));
    $t->same([101, 102, 103, 104], array_column($result['after'], 'option_id'));
    $t->same(['ignore'], [$result['conflict_action']]);
    $t->same(['home', 'blogname', 'cache_seed'], array_column($result['inserted_rows'], 'option_name'));
    $t->same('already', $result['after'][0]['option_value']);
};

$tests['recursive dml current next28 insert select can replace recursive duplicate conflicts'] = static function (TestRunner $t) use ($insertSql, $edges, $options): void {
    $archive = [['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'already', 'autoload' => 'yes']];
    $result = SQLiteRecursiveDmlCurrentSource::insertSelect(str_replace('INSERT INTO', 'INSERT OR REPLACE INTO', $insertSql), ['edges' => $edges, 'wp_options' => $options, 'archive_options' => $archive], [':root' => 1, ':max_depth' => 4], [['option_id']]);
    $t->same(4, $result['changes']);
    $t->same(['already'], array_column($result['deleted_rows'], 'option_value'));
    $t->same('old:archived', $result['after'][0]['option_value']);
    $t->same('replace', $result['conflict_action']);
    $t->same([101, 102, 103, 104], array_column($result['inserted_rows'], 'option_id'));
};

$updateSql = "WITH RECURSIVE walk(id) AS (
    VALUES (?1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id
) UPDATE wp_options SET option_value = walk.id, autoload = 'cycle' FROM walk WHERE wp_options.option_id = walk.id";

$tests['recursive dml current next28 update from uses last current recursive match'] = static function (TestRunner $t) use ($updateSql, $edges, $options): void {
    $result = SQLiteRecursiveDmlCurrentSource::updateFrom($updateSql, ['edges' => $edges, 'wp_options' => $options], [1]);
    $t->same(4, $result['changes']);
    $t->same([1, 2, 3, 4], array_column($result['updated_rows'], 'option_id'));
    $t->same(['cycle', 'cycle', 'cycle', 'cycle', 'no'], array_column($result['after'], 'autoload'));
    $t->same([1, 2, 3, 4], array_slice(array_column($result['after'], 'option_value'), 0, 4));
    $t->same('wp_options', $result['target']);
    $t->same(['option_value' => 'walk.id', 'autoload' => "'cycle'"], $result['assignments']);
    $t->same([], $result['deleted_rows']);
};

$tests['recursive dml current next28 update from leaves rows outside cycle unchanged'] = static function (TestRunner $t) use ($updateSql, $edges, $options): void {
    $result = SQLiteRecursiveDmlCurrentSource::updateFrom($updateSql, ['edges' => $edges, 'wp_options' => $options], [4]);
    $t->same(1, $result['changes']);
    $t->same([4], array_column($result['updated_rows'], 'option_id'));
    $t->same('old', $result['after'][0]['option_value']);
    $t->same('cycle', $result['after'][3]['autoload']);
    $t->same('old', $result['after'][4]['option_value']);
    $t->same([3], array_column($result['matched_rows'], '__sqlite_update_index'));
};

$tests['recursive dml current next28 update from replace deletes current unique conflict'] = static function (TestRunner $t) use ($edges, $options): void {
    $sql = "WITH RECURSIVE walk(id, new_name) AS (
        VALUES (1, 'orphan')
        UNION
        SELECT edges.dst, 'cycle_' || edges.dst FROM edges JOIN walk ON edges.src = walk.id WHERE walk.id < 2
    ) UPDATE OR REPLACE wp_options SET option_name = walk.new_name FROM walk WHERE wp_options.option_id = walk.id";
    $result = SQLiteRecursiveDmlCurrentSource::updateFrom($sql, ['edges' => $edges, 'wp_options' => $options], [], [['option_name']]);
    $t->same(2, $result['changes']);
    $t->same(['orphan'], array_column($result['deleted_rows'], 'option_name'));
    $t->same(['orphan', 'cycle_2', 'blogname', 'cache_seed'], array_column($result['after'], 'option_name'));
    $t->same('replace', $result['conflict_action']);
    $t->same([1, 2], array_column($result['updated_rows'], 'option_id'));
};

$deleteSql = "WITH RECURSIVE walk(id) AS (
    VALUES (1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id
) DELETE FROM wp_options WHERE option_id IN (SELECT id FROM walk) RETURNING option_id, option_name ORDER BY option_id";

$tests['recursive dml current next28 delete returning rewrites recursive in source'] = static function (TestRunner $t) use ($deleteSql, $edges, $options): void {
    $result = SQLiteRecursiveDmlCurrentSource::updateDeleteReturning($deleteSql, ['edges' => $edges, 'wp_options' => $options]);
    $t->same('delete', $result['action']);
    $t->same([1, 2, 3, 4], array_column($result['returning'], 'option_id'));
    $t->same([9], array_column($result['tables']['wp_options'], 'option_id'));
    $t->same('wp_options', $result['table']);
    $t->same(['siteurl', 'home', 'blogname', 'cache_seed'], array_column($result['returning'], 'option_name'));
};

$tests['recursive dml current next28 delete returning supports not in current source'] = static function (TestRunner $t) use ($edges, $options): void {
    $sql = "WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id)
        DELETE FROM wp_options WHERE option_id NOT IN (SELECT id FROM walk) RETURNING option_id, option_name";
    $result = SQLiteRecursiveDmlCurrentSource::updateDeleteReturning($sql, ['edges' => $edges, 'wp_options' => $options]);
    $t->same([9], array_column($result['returning'], 'option_id'));
    $t->same([1, 2, 3, 4], array_column($result['tables']['wp_options'], 'option_id'));
    $t->same(1, count($result['returning']));
    $t->same('orphan', $result['returning'][0]['option_name']);
};

$tests['recursive dml current next28 update returning rewrites recursive in source'] = static function (TestRunner $t) use ($edges, $options): void {
    $sql = "WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id)
        UPDATE wp_options SET option_value = option_name || ':visited' WHERE option_id IN (SELECT id FROM walk) RETURNING option_id, option_value ORDER BY option_id";
    $result = SQLiteRecursiveDmlCurrentSource::updateDeleteReturning($sql, ['edges' => $edges, 'wp_options' => $options]);
    $t->same('update', $result['action']);
    $t->same([1, 2, 3, 4], array_column($result['returning'], 'option_id'));
    $t->same('siteurl:visited', $result['returning'][0]['option_value']);
    $t->same('cache_seed:visited', $result['returning'][3]['option_value']);
    $t->same('old', $result['tables']['wp_options'][4]['option_value']);
};

$tests['recursive dml current next28 rejects malformed current source body'] = static function (TestRunner $t) use ($options): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveDmlCurrentSource::updateDeleteReturning('WITH RECURSIVE walk(id) AS (VALUES (1) DELETE FROM wp_options WHERE option_id IN (SELECT id FROM walk) RETURNING option_id', ['wp_options' => $options]));
};

$tests['recursive dml current next28 rejects cte table shadowing'] = static function (TestRunner $t) use ($edges, $options): void {
    $sql = "WITH RECURSIVE edges(id) AS (VALUES (1)) DELETE FROM wp_options WHERE option_id IN (SELECT id FROM edges) RETURNING option_id";
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveDmlCurrentSource::updateDeleteReturning($sql, ['edges' => $edges, 'wp_options' => $options]));
};

return $tests;

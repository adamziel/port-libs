<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'hits' => 5],
    ['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 2],
    ['option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'hits' => 7],
    ['option_name' => null, 'option_value' => 'anonymous', 'autoload' => 'no', 'hits' => 1],
];

$sql = static function (string $values, string $returning = 'option_name, option_value, autoload, hits'): string {
    return 'INSERT INTO wp_options(option_name, option_value, autoload, hits) VALUES '
        . $values
        . ' ON CONFLICT(option_name) DO NOTHING RETURNING '
        . $returning;
};

$run = static fn (string $values, string $returning = 'option_name, option_value, autoload, hits'): array => SQLiteUpsertReturningSql::execute(
    $sql($values, $returning),
    ['wp_options' => $rows],
    [['option_name']],
);

$tests = [
    'upsert do nothing returning current parses action' => static fn (TestRunner $t) => $t->same('nothing', SQLiteUpsertReturningSql::parse($sql("('x','v','yes',1)"))['action']),
    'upsert do nothing returning current parses target' => static fn (TestRunner $t) => $t->same('wp_options', SQLiteUpsertReturningSql::parse($sql("('x','v','yes',1)"))['target']),
    'upsert do nothing returning current parses conflict target' => static fn (TestRunner $t) => $t->same(['option_name'], SQLiteUpsertReturningSql::parse($sql("('x','v','yes',1)"))['conflict_target']),
    'upsert do nothing returning current parses returning list' => static fn (TestRunner $t) => $t->same('option_name AS name, hits + 1 AS next_hits', SQLiteUpsertReturningSql::parse($sql("('x','v','yes',1)", 'option_name AS name, hits + 1 AS next_hits'))['returning']),
    'upsert do nothing returning current has no assignments' => static fn (TestRunner $t) => $t->same([], SQLiteUpsertReturningSql::parse($sql("('x','v','yes',1)"))['assignments']),

    'upsert do nothing returning current inserts new row' => static fn (TestRunner $t) => $t->same(['new_plugin'], array_column($run("('new_plugin','enabled','no',1)")['inserted_rows'], 'option_name')),
    'upsert do nothing returning current inserted row is returned' => static fn (TestRunner $t) => $t->same([['option_name' => 'new_plugin', 'option_value' => 'enabled']], $run("('new_plugin','enabled','no',1)", 'option_name, option_value')['returning']),
    'upsert do nothing returning current insert increments changes' => static fn (TestRunner $t) => $t->same(1, $run("('new_plugin','enabled','no',1)")['changes']),
    'upsert do nothing returning current appends inserted row' => static fn (TestRunner $t) => $t->same('new_plugin', $run("('new_plugin','enabled','no',1)")['after'][4]['option_name']),
    'upsert do nothing returning current wildcard returns inserted final row' => static fn (TestRunner $t) => $t->same('enabled', $run("('new_plugin','enabled','no',1)", '*')['returning'][0]['option_value']),

    'upsert do nothing returning current skips existing conflict' => static fn (TestRunner $t) => $t->same(['siteurl'], array_column($run("('siteurl','https://new.test','no',9)")['skipped_rows'], 'option_name')),
    'upsert do nothing returning current conflict has no returned row' => static fn (TestRunner $t) => $t->same([], $run("('siteurl','https://new.test','no',9)")['returning']),
    'upsert do nothing returning current conflict makes no changes' => static fn (TestRunner $t) => $t->same(0, $run("('siteurl','https://new.test','no',9)")['changes']),
    'upsert do nothing returning current conflict keeps current row' => static fn (TestRunner $t) => $t->same('https://old.test', $run("('siteurl','https://new.test','no',9)")['after'][0]['option_value']),
    'upsert do nothing returning current conflict does not update rows' => static fn (TestRunner $t) => $t->same([], $run("('siteurl','https://new.test','no',9)")['updated_rows']),

    'upsert do nothing returning current mixed rows return inserts only' => static fn (TestRunner $t) => $t->same(['new_plugin', 'runtime_cache'], array_column($run("('siteurl','new','no',9),('new_plugin','enabled','no',1),('home','new','yes',3),('runtime_cache','on','no',4)", 'option_name')['returning'], 'option_name')),
    'upsert do nothing returning current mixed rows count inserts only' => static fn (TestRunner $t) => $t->same(2, $run("('siteurl','new','no',9),('new_plugin','enabled','no',1),('home','new','yes',3),('runtime_cache','on','no',4)")['changes']),
    'upsert do nothing returning current mixed rows record skipped conflicts' => static fn (TestRunner $t) => $t->same(['siteurl', 'home'], array_column($run("('siteurl','new','no',9),('new_plugin','enabled','no',1),('home','new','yes',3),('runtime_cache','on','no',4)")['skipped_rows'], 'option_name')),
    'upsert do nothing returning current repeated incoming skips second row' => static fn (TestRunner $t) => $t->same(['transient_x'], array_column($run("('transient_x','one','no',1),('transient_x','two','yes',2)", 'option_name, option_value')['returning'], 'option_name')),
    'upsert do nothing returning current repeated incoming keeps first inserted value' => static fn (TestRunner $t) => $t->same('one', $run("('transient_x','one','no',1),('transient_x','two','yes',2)", 'option_name, option_value')['after'][4]['option_value']),
    'upsert do nothing returning current repeated incoming records second skipped' => static fn (TestRunner $t) => $t->same(['two'], array_column($run("('transient_x','one','no',1),('transient_x','two','yes',2)")['skipped_rows'], 'option_value')),
    'upsert do nothing returning current null conflict key still inserts' => static fn (TestRunner $t) => $t->same(5, count($run("(NULL,'anon-two','yes',2)")['after'])),
    'upsert do nothing returning current null conflict key returns inserted null row' => static fn (TestRunner $t) => $t->same([[null, 'anon-two']], array_map(static fn (array $row): array => [$row['option_name'], $row['option_value']], $run("(NULL,'anon-two','yes',2)", 'option_name, option_value')['returning'])),
    'upsert do nothing returning current expression sees inserted row' => static fn (TestRunner $t) => $t->same([['label' => 'new_plugin:no:2']], $run("('new_plugin','enabled','no',2)", "option_name || ':' || autoload || ':' || hits AS label")['returning']),
    'upsert do nothing returning current expression not run for skipped conflict' => static fn (TestRunner $t) => $t->same([], $run("('siteurl','new','no',9)", "option_name || ':' || autoload AS label")['returning']),
    'upsert do nothing returning current aliases inserted values' => static fn (TestRunner $t) => $t->same([['name' => 'new_plugin', 'score' => 3]], $run("('new_plugin','enabled','no',2)", 'option_name AS name, hits + 1 AS score')['returning']),

    'upsert do nothing returning current rejects missing returning' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute("INSERT INTO wp_options(option_name, option_value, autoload, hits) VALUES ('x','v','yes',1) ON CONFLICT(option_name) DO NOTHING", ['wp_options' => $rows])),
    'upsert do nothing returning current rejects where clause' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute("INSERT INTO wp_options(option_name, option_value, autoload, hits) VALUES ('x','v','yes',1) ON CONFLICT(option_name) DO NOTHING WHERE 1 RETURNING option_name", ['wp_options' => $rows])),
    'upsert do nothing returning current rejects assignment text' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute("INSERT INTO wp_options(option_name, option_value, autoload, hits) VALUES ('x','v','yes',1) ON CONFLICT(option_name) DO NOTHING SET hits = 1 RETURNING option_name", ['wp_options' => $rows])),
    'upsert do nothing returning current rejects missing conflict column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute("INSERT INTO wp_options(option_name, option_value, autoload, hits) VALUES ('x','v','yes',1) ON CONFLICT(missing) DO NOTHING RETURNING option_name", ['wp_options' => $rows])),
    'upsert do nothing returning current rejects excluded returning reference' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run("('new_plugin','enabled','no',2)", 'excluded.option_name AS name')),
];

return $tests;

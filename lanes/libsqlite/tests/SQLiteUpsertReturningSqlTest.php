<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$baseRows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'hits' => 5, 'touched' => 'old'],
    ['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 2, 'touched' => 'old'],
    ['option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'hits' => 7, 'touched' => 'old'],
    ['option_name' => null, 'option_value' => 'anonymous', 'autoload' => 'no', 'hits' => 1, 'touched' => 'old'],
];

$tables = static fn (): array => ['wp_options' => $baseRows];

$sql = static function (string $values, ?string $where = '1', string $returning = 'option_name, option_value, autoload, hits, touched'): string {
    $whereSql = $where === null ? '' : ' WHERE ' . $where;

    return 'INSERT INTO wp_options(option_name, option_value, autoload, hits, touched) VALUES ' . $values
        . ' ON CONFLICT(option_name) DO UPDATE SET '
        . 'option_value = excluded.option_value, '
        . 'autoload = excluded.autoload, '
        . 'hits = wp_options.hits + excluded.hits, '
        . 'touched = excluded.touched'
        . $whereSql
        . ' RETURNING ' . $returning;
};

$run = static fn (string $values, ?string $where = '1', string $returning = 'option_name, option_value, autoload, hits, touched'): array => SQLiteUpsertReturningSql::execute(
    $sql($values, $where, $returning),
    $tables(),
    [['option_name']],
);

$tests = [
    'upsert returning sql parses target table' => static fn (TestRunner $t) => $t->same('wp_options', SQLiteUpsertReturningSql::parse($sql("('x','v','yes',1,'n')"))['target']),
    'upsert returning sql parses target columns' => static fn (TestRunner $t) => $t->same(['option_name', 'option_value', 'autoload', 'hits', 'touched'], SQLiteUpsertReturningSql::parse($sql("('x','v','yes',1,'n')"))['columns']),
    'upsert returning sql parses conflict target' => static fn (TestRunner $t) => $t->same(['option_name'], SQLiteUpsertReturningSql::parse($sql("('x','v','yes',1,'n')"))['conflict_target']),
    'upsert returning sql parses incoming literal row' => static fn (TestRunner $t) => $t->same('x', SQLiteUpsertReturningSql::parse($sql("('x','v','yes',1,'n')"))['incoming_rows'][0]['option_name']),
    'upsert returning sql parses escaped string literal' => static fn (TestRunner $t) => $t->same("Bob's Blog", SQLiteUpsertReturningSql::parse($sql("('blogname','Bob''s Blog','yes',1,'n')"))['incoming_rows'][0]['option_value']),
    'upsert returning sql parses null unique value' => static fn (TestRunner $t) => $t->same(null, SQLiteUpsertReturningSql::parse($sql("(NULL,'anon','no',1,'n')"))['incoming_rows'][0]['option_name']),
    'upsert returning sql parses where expression' => static fn (TestRunner $t) => $t->same("excluded.hits > wp_options.hits", SQLiteUpsertReturningSql::parse($sql("('home','new','yes',9,'n')", 'excluded.hits > wp_options.hits'))['where']),
    'upsert returning sql parses returning aliases' => static fn (TestRunner $t) => $t->same('option_name AS name, hits AS total', SQLiteUpsertReturningSql::parse($sql("('home','new','yes',9,'n')", '1', 'option_name AS name, hits AS total'))['returning']),

    'upsert returning sql inserts non-conflicting row' => static fn (TestRunner $t) => $t->same(['new_plugin'], array_column($run("('new_plugin','enabled','no',1,'i1')")['inserted_rows'], 'option_name')),
    'upsert returning sql insert appears in returning rows' => static fn (TestRunner $t) => $t->same([['option_name' => 'new_plugin', 'option_value' => 'enabled']], $run("('new_plugin','enabled','no',1,'i1')", '1', 'option_name, option_value')['returning']),
    'upsert returning sql insert increments changes' => static fn (TestRunner $t) => $t->same(1, $run("('new_plugin','enabled','no',1,'i1')")['changes']),
    'upsert returning sql insert appends after rows' => static fn (TestRunner $t) => $t->same('new_plugin', $run("('new_plugin','enabled','no',1,'i1')")['after'][4]['option_name']),
    'upsert returning sql null unique does not conflict' => static fn (TestRunner $t) => $t->same(5, count($run("(NULL,'second','yes',2,'i2')")['after'])),

    'upsert returning sql updates when where true' => static fn (TestRunner $t) => $t->same(['https://new.test'], array_column($run("('siteurl','https://new.test','no',3,'u1')")['updated_rows'], 'option_value')),
    'upsert returning sql update adds current and excluded hits' => static fn (TestRunner $t) => $t->same([8], array_column($run("('siteurl','https://new.test','no',3,'u1')")['returning'], 'hits')),
    'upsert returning sql update copies excluded autoload' => static fn (TestRunner $t) => $t->same(['no'], array_column($run("('siteurl','https://new.test','no',3,'u1')")['returning'], 'autoload')),
    'upsert returning sql update copies excluded touched' => static fn (TestRunner $t) => $t->same(['u1'], array_column($run("('siteurl','https://new.test','no',3,'u1')")['returning'], 'touched')),
    'upsert returning sql update preserves row order' => static fn (TestRunner $t) => $t->same('siteurl', $run("('siteurl','https://new.test','no',3,'u1')")['after'][0]['option_name']),
    'upsert returning sql same values still count update' => static fn (TestRunner $t) => $t->same(1, $run("('home','https://home.test','yes',0,'old')")['changes']),

    'upsert returning sql where false skips conflict' => static fn (TestRunner $t) => $t->same([], $run("('siteurl','skip','no',3,'u2')", '0')['updated_rows']),
    'upsert returning sql skipped conflict has no returning row' => static fn (TestRunner $t) => $t->same([], $run("('siteurl','skip','no',3,'u2')", '0')['returning']),
    'upsert returning sql skipped conflict records excluded row' => static fn (TestRunner $t) => $t->same(['siteurl'], array_column($run("('siteurl','skip','no',3,'u2')", '0')['skipped_rows'], 'option_name')),
    'upsert returning sql null where skips like sqlite' => static fn (TestRunner $t) => $t->same(0, $run("('siteurl','skip','no',3,'u3')", 'NULL')['changes']),
    'upsert returning sql absent where updates conflict' => static fn (TestRunner $t) => $t->same([12], array_column($run("('blogname','New Blog','yes',5,'u4')", null)['returning'], 'hits')),

    'upsert returning sql where current autoload matches' => static fn (TestRunner $t) => $t->same(1, $run("('home','home-new','no',4,'u5')", "wp_options.autoload = 'yes'")['changes']),
    'upsert returning sql where current autoload rejects' => static fn (TestRunner $t) => $t->same(0, $run("('blogname','blog-new','yes',4,'u6')", "wp_options.autoload = 'yes'")['changes']),
    'upsert returning sql where excluded autoload matches' => static fn (TestRunner $t) => $t->same(1, $run("('blogname','blog-new','yes',4,'u7')", "excluded.autoload = 'yes'")['changes']),
    'upsert returning sql where excluded autoload rejects' => static fn (TestRunner $t) => $t->same(0, $run("('siteurl','site-new','no',4,'u8')", "excluded.autoload = 'yes'")['changes']),
    'upsert returning sql where current hits greater' => static fn (TestRunner $t) => $t->same(1, $run("('blogname','blog-new','yes',1,'u9')", 'wp_options.hits > 5')['changes']),
    'upsert returning sql where current hits lower rejects' => static fn (TestRunner $t) => $t->same(0, $run("('home','home-new','yes',1,'u10')", 'wp_options.hits > 5')['changes']),
    'upsert returning sql where excluded hits greater' => static fn (TestRunner $t) => $t->same(1, $run("('home','home-new','yes',9,'u11')", 'excluded.hits > wp_options.hits')['changes']),
    'upsert returning sql where excluded hits lower rejects' => static fn (TestRunner $t) => $t->same(0, $run("('blogname','blog-new','yes',2,'u12')", 'excluded.hits > wp_options.hits')['changes']),
    'upsert returning sql where current LIKE matches' => static fn (TestRunner $t) => $t->same(1, $run("('siteurl','site-new','yes',2,'u13')", "wp_options.option_value LIKE 'https://%'")['changes']),
    'upsert returning sql where current LIKE rejects' => static fn (TestRunner $t) => $t->same(0, $run("('blogname','blog-new','yes',2,'u14')", "wp_options.option_value LIKE 'https://%'")['changes']),
    'upsert returning sql where current GLOB matches' => static fn (TestRunner $t) => $t->same(1, $run("('blogname','blog-new','yes',2,'u15')", "wp_options.option_name GLOB 'blog*'")['changes']),
    'upsert returning sql where value differs updates' => static fn (TestRunner $t) => $t->same(1, $run("('home','https://alt.test','yes',1,'u16')", 'excluded.option_value <> wp_options.option_value')['changes']),
    'upsert returning sql where same value skips' => static fn (TestRunner $t) => $t->same(0, $run("('home','https://home.test','no',1,'u17')", 'excluded.option_value <> wp_options.option_value')['changes']),
    'upsert returning sql where is not null updates' => static fn (TestRunner $t) => $t->same(1, $run("('siteurl','not-null','yes',1,'u18')", 'excluded.option_value IS NOT NULL')['changes']),
    'upsert returning sql where is not null rejects null' => static fn (TestRunner $t) => $t->same(0, $run("('siteurl',NULL,'yes',1,'u19')", 'excluded.option_value IS NOT NULL')['changes']),
    'upsert returning sql where coalesce current update' => static fn (TestRunner $t) => $t->same(1, $run("('blogname','coalesced','yes',1,'u20')", "coalesce(wp_options.autoload, '') = 'no'")['changes']),
    'upsert returning sql where coalesce current skip' => static fn (TestRunner $t) => $t->same(0, $run("('siteurl','coalesced','yes',1,'u21')", "coalesce(wp_options.autoload, '') = 'no'")['changes']),
    'upsert returning sql where arithmetic updates' => static fn (TestRunner $t) => $t->same(1, $run("('home','home-new','yes',3,'u22')", 'wp_options.hits + excluded.hits >= 5')['changes']),
    'upsert returning sql where arithmetic skips' => static fn (TestRunner $t) => $t->same(0, $run("('home','home-new','yes',1,'u23')", 'wp_options.hits + excluded.hits >= 5')['changes']),

    'upsert returning sql mixed insert and update changes both' => static fn (TestRunner $t) => $t->same(2, $run("('siteurl','site-new','yes',1,'u24'),('new_plugin','enabled','no',1,'i24')", 'wp_options.hits >= 5')['changes']),
    'upsert returning sql mixed insert and skipped update changes insert only' => static fn (TestRunner $t) => $t->same(1, $run("('home','home-new','yes',1,'u25'),('new_plugin','enabled','no',1,'i25')", 'wp_options.hits >= 5')['changes']),
    'upsert returning sql repeated incoming second sees updated current' => static fn (TestRunner $t) => $t->same([6, 10], array_column($run("('home','home-1','yes',4,'u26a'),('home','home-2','yes',4,'u26b')", 'wp_options.hits < 10')['returning'], 'hits')),
    'upsert returning sql repeated incoming second can skip updated current' => static fn (TestRunner $t) => $t->same([10], array_column($run("('home','home-1','yes',8,'u27a'),('home','home-2','yes',5,'u27b')", 'wp_options.hits < 10')['returning'], 'hits')),
    'upsert returning sql inserted then conflicts in same statement' => static fn (TestRunner $t) => $t->same([1, 3], array_column($run("('transient_x','one','no',1,'i28a'),('transient_x','two','yes',2,'u28b')", "excluded.autoload = 'yes'")['returning'], 'hits')),
    'upsert returning sql inserted then skipped conflict returns first insert only' => static fn (TestRunner $t) => $t->same(['one'], array_column($run("('transient_y','one','no',1,'i29a'),('transient_y','two','no',2,'u29b')", "excluded.autoload = 'yes'")['returning'], 'option_value')),
    'upsert returning sql multiple conflicts mixed returns changed rows' => static fn (TestRunner $t) => $t->same(['siteurl', 'blogname'], array_column($run("('siteurl','site-new','yes',1,'u30'),('home','home-new','yes',1,'u30'),('blogname','blog-new','yes',1,'u30')", 'wp_options.hits >= 5')['returning'], 'option_name')),
    'upsert returning sql returning alias projection' => static fn (TestRunner $t) => $t->same([['name' => 'siteurl', 'total' => 6]], $run("('siteurl','site-new','yes',1,'u31')", '1', 'option_name AS name, hits AS total')['returning']),
    'upsert returning sql returning wildcard projection' => static fn (TestRunner $t) => $t->same('u32', $run("('siteurl','site-new','yes',1,'u32')", '1', '*')['returning'][0]['touched']),
    'upsert returning sql returns final after row value' => static fn (TestRunner $t) => $t->same('site-new', $run("('siteurl','site-new','yes',1,'u33')")['after'][0]['option_value']),
    'upsert returning sql reports updated rows separately from inserts' => static fn (TestRunner $t) => $t->same([1, 1], [count($run("('siteurl','site-new','yes',1,'u34'),('z','z','no',1,'i34')")['updated_rows']), count($run("('siteurl','site-new','yes',1,'u34'),('z','z','no',1,'i34')")['inserted_rows'])]),

    'upsert returning sql rejects missing table' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute($sql("('x','v','yes',1,'n')"), [])),
    'upsert returning sql rejects missing returning' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute("INSERT INTO wp_options(option_name, option_value, autoload, hits, touched) VALUES ('x','v','yes',1,'n') ON CONFLICT(option_name) DO UPDATE SET hits = excluded.hits", $tables())),
    'upsert returning sql rejects malformed values count' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute("INSERT INTO wp_options(option_name, option_value) VALUES ('x') ON CONFLICT(option_name) DO UPDATE SET option_value = excluded.option_value RETURNING option_name", $tables())),
    'upsert returning sql rejects unsupported select input' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertReturningSql::execute("INSERT INTO wp_options(option_name) SELECT 'x' ON CONFLICT(option_name) DO UPDATE SET option_name = excluded.option_name RETURNING option_name", $tables())),
    'upsert returning sql rejects unsupported where predicate' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run("('siteurl','site-new','yes',1,'u35')", 'wp_options.hits BETWEEN 1 AND 9')),
    'upsert returning sql rejects missing returning column' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run("('siteurl','site-new','yes',1,'u36')", '1', 'missing_column')),
];

return $tests;

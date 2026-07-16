<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'blog_id' => 1],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'option_value' => 'a:1:{}', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 6, 'option_name' => 'widget_text', 'option_value' => 'a:2:{}', 'autoload' => 'no', 'blog_id' => 2],
    ],
    'import_option_meta' => [
        ['option_id' => 1, 'meta_key' => 'source_url', 'meta_value' => 'https://legacy.example'],
        ['option_id' => 2, 'meta_key' => 'source_url', 'meta_value' => 'https://legacy.example'],
        ['option_id' => 3, 'meta_key' => 'source_title', 'meta_value' => 'Legacy Title'],
        ['option_id' => 5, 'meta_key' => 'skip_import', 'meta_value' => '1'],
        ['option_id' => 6, 'meta_key' => 'site_scope', 'meta_value' => 'network'],
    ],
];

$column = static function (string $sql, string $column, array $source = null) use ($tables): array {
    return array_column(SQLiteSelectSql::execute($sql, $source ?? $tables), $column);
};

$scalar = static function (string $sql, string $column, array $source = null) use ($tables) {
    $rows = SQLiteSelectSql::execute($sql, $source ?? $tables);
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected exactly one derived-table SELECT row');
    }

    return $rows[0][$column];
};

$cases = [
    'materializes simple derived table' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home', 'blogname', '_transient_feed', 'rewrite_rules', 'widget_text'],
        $column('SELECT option_name FROM (SELECT option_name FROM wp_options ORDER BY option_id) AS staged', 'option_name'),
    ),
    'filters outer rows after derived table materialization' => static fn (TestRunner $t) => $t->same(
        ['blogname', 'home', 'rewrite_rules', 'siteurl'],
        $column("SELECT option_name FROM (SELECT option_name, autoload FROM wp_options) AS staged WHERE autoload = 'yes' ORDER BY option_name", 'option_name'),
    ),
    'preserves derived aliases for outer projection' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home'],
        $column("SELECT migrated_name FROM (SELECT option_name AS migrated_name, option_id FROM wp_options WHERE option_id <= 2 ORDER BY option_id) AS staged", 'migrated_name'),
    ),
    'orders by derived expression alias' => static fn (TestRunner $t) => $t->same(
        ['_transient_feed', 'rewrite_rules', 'widget_text'],
        $column("SELECT option_name FROM (SELECT option_name, length(option_value) AS bytes FROM wp_options) AS staged WHERE bytes >= 5 ORDER BY bytes, option_name LIMIT 3", 'option_name'),
    ),
    'applies outer limit to derived rows' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home'],
        $column('SELECT option_name FROM (SELECT option_name FROM wp_options ORDER BY option_id) AS staged LIMIT 2', 'option_name'),
    ),
    'applies outer offset to derived rows' => static fn (TestRunner $t) => $t->same(
        ['blogname', '_transient_feed'],
        $column('SELECT option_name FROM (SELECT option_name FROM wp_options ORDER BY option_id) AS staged LIMIT 2 OFFSET 2', 'option_name'),
    ),
    'keeps inner limit before outer filter' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home', 'blogname'],
        $column("SELECT option_name FROM (SELECT option_name, autoload FROM wp_options ORDER BY option_id LIMIT 3) AS staged WHERE autoload = 'yes'", 'option_name'),
    ),
    'keeps inner offset before outer filter' => static fn (TestRunner $t) => $t->same(
        ['blogname', 'rewrite_rules'],
        $column("SELECT option_name FROM (SELECT option_name, autoload FROM wp_options ORDER BY option_id LIMIT 3 OFFSET 2) AS staged WHERE autoload = 'yes'", 'option_name'),
    ),
    'supports derived DISTINCT rows' => static fn (TestRunner $t) => $t->same(
        ['yes', 'no'],
        $column('SELECT autoload FROM (SELECT DISTINCT autoload FROM wp_options ORDER BY autoload DESC) AS staged', 'autoload'),
    ),
    'supports derived ALL rows' => static fn (TestRunner $t) => $t->same(
        ['yes', 'yes', 'yes'],
        $column("SELECT autoload FROM (SELECT ALL autoload FROM wp_options WHERE autoload = 'yes') AS staged LIMIT 3", 'autoload'),
    ),
    'supports derived VALUES CTE' => static fn (TestRunner $t) => $t->same(
        [1, 2, 3],
        $column('SELECT id FROM (WITH wanted(id) AS (VALUES (1), (2), (3)) SELECT id FROM wanted ORDER BY id) AS staged', 'id'),
    ),
    'supports derived compound UNION' => static fn (TestRunner $t) => $t->same(
        ['home', 'siteurl'],
        $column("SELECT option_name FROM (SELECT 'siteurl' AS option_name UNION SELECT 'home' AS option_name) AS staged ORDER BY option_name", 'option_name'),
    ),
    'supports derived compound UNION ALL' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'siteurl'],
        $column("SELECT option_name FROM (SELECT 'siteurl' AS option_name UNION ALL SELECT 'siteurl' AS option_name) AS staged", 'option_name'),
    ),
    'supports derived compound INTERSECT' => static fn (TestRunner $t) => $t->same(
        ['home'],
        $column("SELECT option_name FROM (SELECT option_name FROM wp_options WHERE option_id <= 2 INTERSECT SELECT option_name FROM wp_options WHERE option_name = 'home') AS staged", 'option_name'),
    ),
    'supports derived compound EXCEPT' => static fn (TestRunner $t) => $t->same(
        ['siteurl'],
        $column("SELECT option_name FROM (SELECT option_name FROM wp_options WHERE option_id <= 2 EXCEPT SELECT option_name FROM wp_options WHERE option_name = 'home') AS staged", 'option_name'),
    ),
    'supports derived aggregate grouping' => static fn (TestRunner $t) => $t->same(
        ['no', 'yes'],
        $column('SELECT autoload FROM (SELECT autoload, count(option_id) AS total FROM wp_options GROUP BY autoload ORDER BY autoload) AS staged', 'autoload'),
    ),
    'supports outer filter over derived aggregate' => static fn (TestRunner $t) => $t->same(
        ['yes'],
        $column("SELECT autoload FROM (SELECT autoload, count(option_id) AS total FROM wp_options GROUP BY autoload) AS staged WHERE total > 2", 'autoload'),
    ),
    'supports derived HAVING aggregate' => static fn (TestRunner $t) => $t->same(
        ['yes'],
        $column("SELECT autoload FROM (SELECT autoload, count(option_id) AS total FROM wp_options GROUP BY autoload HAVING count(option_id) > 2) AS staged", 'autoload'),
    ),
    'joins derived table to import metadata' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
        $column('SELECT staged.option_name AS name FROM (SELECT option_id, option_name FROM wp_options) AS staged JOIN import_option_meta AS m ON m.option_id = staged.option_id ORDER BY staged.option_id', 'name'),
    ),
    'left joins derived table to import metadata' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home', 'blogname', '_transient_feed', 'rewrite_rules', 'widget_text'],
        $column('SELECT staged.option_name AS name FROM (SELECT option_id, option_name FROM wp_options ORDER BY option_id) AS staged LEFT JOIN import_option_meta AS m ON m.option_id = staged.option_id ORDER BY staged.option_id', 'name'),
    ),
    'left join exposes null metadata for missing derived row match' => static fn (TestRunner $t) => $t->same(
        [null],
        $column("SELECT m.meta_key AS meta_key FROM (SELECT option_id, option_name FROM wp_options WHERE option_name = '_transient_feed') AS staged LEFT JOIN import_option_meta AS m ON m.option_id = staged.option_id", 'meta_key'),
    ),
    'derived table can be right side of join' => static fn (TestRunner $t) => $t->same(
        ['source_url:siteurl', 'source_url:home', 'source_title:blogname'],
        $column("SELECT m.meta_key || ':' || staged.option_name AS pair FROM import_option_meta AS m JOIN (SELECT option_id, option_name FROM wp_options WHERE blog_id = 1) AS staged ON staged.option_id = m.option_id WHERE m.meta_key LIKE 'source_%' ORDER BY staged.option_id", 'pair'),
    ),
    'derived alias without AS is accepted' => static fn (TestRunner $t) => $t->same(
        ['siteurl'],
        $column("SELECT option_name FROM (SELECT option_name FROM wp_options WHERE option_id = 1) staged", 'option_name'),
    ),
    'outer predicate can reference derived column in BETWEEN' => static fn (TestRunner $t) => $t->same(
        ['home', 'blogname', '_transient_feed'],
        $column('SELECT option_name FROM (SELECT option_id, option_name FROM wp_options) AS staged WHERE option_id BETWEEN 2 AND 4 ORDER BY option_id', 'option_name'),
    ),
    'outer predicate can reference derived column in IN list' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'rewrite_rules'],
        $column("SELECT option_name FROM (SELECT option_id, option_name FROM wp_options) AS staged WHERE option_id IN (1, 5) ORDER BY option_id", 'option_name'),
    ),
    'outer predicate can reference derived column with NOT IN null semantics' => static fn (TestRunner $t) => $t->same(
        [],
        $column("SELECT option_name FROM (SELECT option_name FROM wp_options) AS staged WHERE option_name NOT IN ('siteurl', NULL)", 'option_name'),
    ),
    'outer predicate can reference derived column with LIKE' => static fn (TestRunner $t) => $t->same(
        ['_transient_feed'],
        $column("SELECT option_name FROM (SELECT option_name FROM wp_options) AS staged WHERE option_name LIKE '!_transient%' ESCAPE '!' ORDER BY option_name", 'option_name'),
    ),
    'outer predicate can reference derived column with GLOB' => static fn (TestRunner $t) => $t->same(
        ['widget_text'],
        $column("SELECT option_name FROM (SELECT option_name FROM wp_options) AS staged WHERE option_name GLOB 'widget_*'", 'option_name'),
    ),
    'outer predicate can reference derived column with IS NULL' => static fn (TestRunner $t) => $t->same(
        [],
        $column('SELECT option_name FROM (SELECT option_name FROM wp_options) AS staged WHERE option_name IS NULL', 'option_name'),
    ),
    'outer predicate can reference derived column with IS NOT NULL' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home', 'blogname', '_transient_feed', 'rewrite_rules', 'widget_text'],
        $column('SELECT option_name FROM (SELECT option_id, option_name FROM wp_options) AS staged WHERE option_name IS NOT NULL ORDER BY option_id', 'option_name'),
    ),
    'outer expression projection composes derived columns' => static fn (TestRunner $t) => $t->same(
        ['siteurl-copy', 'home-copy'],
        $column("SELECT option_name || '-copy' AS label FROM (SELECT option_id, option_name FROM wp_options ORDER BY option_id LIMIT 2) AS staged", 'label'),
    ),
    'outer CASE projection composes derived columns' => static fn (TestRunner $t) => $t->same(
        ['yes', 'no', 'yes'],
        $column('SELECT autoload FROM (SELECT autoload FROM wp_options ORDER BY option_id LIMIT 3 OFFSET 2) AS staged', 'autoload'),
    ),
    'outer scalar function projection composes derived columns' => static fn (TestRunner $t) => $t->same(
        ['SITEURL', 'HOME'],
        $column('SELECT upper(option_name) AS name FROM (SELECT option_name FROM wp_options ORDER BY option_id LIMIT 2) AS staged', 'name'),
    ),
    'outer ORDER BY expression composes derived columns' => static fn (TestRunner $t) => $t->same(
        ['home', 'siteurl', 'blogname'],
        $column('SELECT option_name FROM (SELECT option_name FROM wp_options) AS staged ORDER BY length(option_name) LIMIT 3', 'option_name'),
    ),
    'outer DISTINCT removes derived duplicates' => static fn (TestRunner $t) => $t->same(
        ['yes', 'no'],
        $column('SELECT DISTINCT autoload FROM (SELECT autoload FROM wp_options ORDER BY autoload DESC) AS staged', 'autoload'),
    ),
    'outer GROUP BY groups derived rows' => static fn (TestRunner $t) => $t->same(
        ['no', 'yes'],
        $column('SELECT autoload, count(option_id) AS total FROM (SELECT option_id, autoload FROM wp_options) AS staged GROUP BY autoload ORDER BY autoload', 'autoload'),
    ),
    'outer HAVING filters derived groups' => static fn (TestRunner $t) => $t->same(
        ['yes'],
        $column('SELECT autoload, count(option_id) AS total FROM (SELECT option_id, autoload FROM wp_options) AS staged GROUP BY autoload HAVING count(option_id) > 3', 'autoload'),
    ),
    'outer EXISTS can correlate against derived rows' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
        $column('SELECT staged.option_name AS name FROM (SELECT option_id, option_name FROM wp_options) AS staged JOIN import_option_meta AS m ON m.option_id = staged.option_id ORDER BY staged.option_id', 'name'),
    ),
    'outer NOT EXISTS can correlate against derived rows' => static fn (TestRunner $t) => $t->same(
        ['_transient_feed'],
        $column('SELECT staged.option_name AS name FROM (SELECT option_id, option_name FROM wp_options) AS staged LEFT JOIN import_option_meta AS m ON m.option_id = staged.option_id WHERE m.meta_key IS NULL ORDER BY staged.option_id', 'name'),
    ),
    'outer IN subquery can reference derived rows' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home'],
        $column("SELECT option_name FROM (SELECT option_id, option_name FROM wp_options) AS staged WHERE option_id IN (SELECT option_id FROM import_option_meta WHERE meta_key = 'source_url') ORDER BY option_id", 'option_name'),
    ),
    'outer NOT IN empty subquery keeps derived rows' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home', 'blogname', '_transient_feed', 'rewrite_rules', 'widget_text'],
        $column("SELECT option_name FROM (SELECT option_id, option_name FROM wp_options) AS staged WHERE option_id NOT IN (SELECT option_id FROM import_option_meta WHERE meta_key = 'missing') ORDER BY option_id", 'option_name'),
    ),
    'derived table supports selected source columns after wildcard expansion' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home'],
        $column('SELECT option_name FROM (SELECT * FROM wp_options ORDER BY option_id LIMIT 2) AS staged', 'option_name'),
    ),
    'derived table supports table wildcard in inner query' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home'],
        $column('SELECT option_name FROM (SELECT * FROM wp_options ORDER BY option_id LIMIT 2) AS staged', 'option_name'),
    ),
    'derived table supports json extraction in inner query' => static fn (TestRunner $t) => $t->same(
        ['publish'],
        $column("SELECT status FROM (SELECT json_extract(option_value, '$.status') AS status FROM wp_options WHERE option_name = 'import_json') AS staged", 'status', [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'import_json', 'option_value' => '{"status":"publish"}', 'autoload' => 'no'],
            ],
        ]),
    ),
    'derived table supports json predicate in outer query' => static fn (TestRunner $t) => $t->same(
        ['import_json'],
        $column("SELECT option_name FROM (SELECT option_name, option_value FROM wp_options) AS staged WHERE json_extract(option_value, '$.status') = 'publish'", 'option_name', [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'import_json', 'option_value' => '{"status":"publish"}', 'autoload' => 'no'],
                ['option_id' => 11, 'option_name' => 'draft_json', 'option_value' => '{"status":"draft"}', 'autoload' => 'no'],
            ],
        ]),
    ),
    'nested derived tables materialize inward first' => static fn (TestRunner $t) => $t->same(
        ['home'],
        $column("SELECT option_name FROM (SELECT option_name FROM (SELECT option_name, option_id FROM wp_options WHERE option_id <= 2) AS inner_stage WHERE option_id = 2) AS outer_stage", 'option_name'),
    ),
    'nested derived table with CTE remains scoped' => static fn (TestRunner $t) => $t->same(
        ['siteurl'],
        $column("SELECT option_name FROM (WITH ids(id) AS (VALUES (1)) SELECT option_name FROM wp_options WHERE option_id IN (SELECT id FROM ids)) AS staged", 'option_name'),
    ),
    'derived table does not mutate input tables' => static function (TestRunner $t) use ($tables): void {
        SQLiteSelectSql::execute("SELECT option_name FROM (SELECT option_name FROM wp_options WHERE autoload = 'yes') AS staged", $tables);
        $t->same(6, count($tables['wp_options']));
    },
    'derived table plan records materialized rows' => static function (TestRunner $t) use ($tables): void {
        $plan = SQLiteSelectSql::plan('SELECT option_name FROM (SELECT option_name FROM wp_options WHERE option_id <= 2 ORDER BY option_id) AS staged', $tables);
        $t->same([['option_name' => 'siteurl'], ['option_name' => 'home']], $plan['from']);
    },
    'derived table join plan qualifies materialized alias rows' => static function (TestRunner $t) use ($tables): void {
        $plan = SQLiteSelectSql::plan('SELECT staged.option_name AS name FROM (SELECT option_id, option_name FROM wp_options WHERE option_id = 1) AS staged JOIN import_option_meta AS m ON m.option_id = staged.option_id', $tables);
        $t->same('staged.option_id', array_key_first($plan['from'][0]));
    },
    'derived table accepts missing alias for unqualified outer columns' => static fn (TestRunner $t) => $t->same(
        ['siteurl', 'home'],
        $column('SELECT option_name FROM (SELECT option_name FROM wp_options ORDER BY option_id LIMIT 2)', 'option_name'),
    ),
    'derived table rejects malformed alias' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute('SELECT option_name FROM (SELECT option_name FROM wp_options) AS staged extra', $tables),
    ),
    'derived table rejects invalid alias identifier' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute('SELECT option_name FROM (SELECT option_name FROM wp_options) AS 1stage', $tables),
    ),
    'derived table propagates inner missing source error' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute('SELECT option_name FROM (SELECT option_name FROM missing_options) AS staged', $tables),
    ),
    'derived table propagates inner malformed select error' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute('SELECT option_name FROM (SELECT FROM wp_options) AS staged', $tables),
    ),
    'derived table propagates outer missing column error' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute('SELECT missing FROM (SELECT option_name FROM wp_options) AS staged', $tables),
    ),
    'derived table propagates ambiguous outer column after join' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute('SELECT option_id FROM (SELECT option_id FROM wp_options) AS staged JOIN import_option_meta AS m ON m.option_id = staged.option_id', $tables),
    ),
];

foreach ($cases as $name => $case) {
    $tests['select derived table current next23 ' . $name] = $case;
}

return $tests;

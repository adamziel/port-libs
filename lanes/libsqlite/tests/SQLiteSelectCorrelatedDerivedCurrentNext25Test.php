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
    'import_stage' => [
        ['option_id' => 1, 'target_blog' => 1, 'status' => 'ready'],
        ['option_id' => 2, 'target_blog' => 1, 'status' => 'ready'],
        ['option_id' => 3, 'target_blog' => 1, 'status' => 'review'],
        ['option_id' => 5, 'target_blog' => 1, 'status' => 'skip'],
        ['option_id' => 6, 'target_blog' => 2, 'status' => 'ready'],
    ],
];

$column = static function (string $sql, string $column) use ($tables): array {
    return array_column(SQLiteSelectSql::execute($sql, $tables), $column);
};

$scalar = static function (string $sql, string $column) use ($tables) {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected exactly one correlated derived SELECT row');
    }

    return $rows[0][$column];
};

$cases = [
    'exists qualified derived alias matches outer option id' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'not exists qualified derived alias finds missing transient metadata' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['_transient_feed'],
    ],
    'exists derived alias after inner where filter' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, meta_key FROM import_option_meta WHERE meta_key = 'source_url') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home'],
    ],
    'not exists derived alias after inner where filter' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT 1 FROM (SELECT option_id, meta_key FROM import_option_meta WHERE meta_key = 'source_url') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['blogname', '_transient_feed', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived expression alias correlates by transformed key' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id + 1 AS next_id FROM import_option_meta) AS d WHERE d.next_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['home', 'blogname', '_transient_feed', 'widget_text'],
    ],
    'exists derived aggregate alias correlates by count' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, count(meta_key) AS meta_count FROM import_option_meta GROUP BY option_id) AS d WHERE d.option_id = wp_options.option_id AND d.meta_count = 1) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived having alias correlates by count' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, count(meta_key) AS meta_count FROM import_option_meta GROUP BY option_id HAVING count(meta_key) = 1) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived distinct alias correlates once per option' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT DISTINCT option_id FROM import_option_meta) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived limit alias applies before correlation' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta ORDER BY option_id LIMIT 2) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home'],
    ],
    'exists derived offset alias applies before correlation' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta ORDER BY option_id LIMIT 2 OFFSET 2) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['blogname', 'rewrite_rules'],
    ],
    'exists derived comma limit alias applies before correlation' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta ORDER BY option_id LIMIT 1, 2) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['home', 'blogname'],
    ],
    'exists derived compound union correlates by alias' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 1 AS option_id UNION SELECT 3 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'blogname'],
    ],
    'exists derived compound union all correlates by alias' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 1 AS option_id UNION ALL SELECT 1 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl'],
    ],
    'exists derived compound intersect correlates by alias' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta INTERSECT SELECT option_id FROM import_stage WHERE status = 'ready') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'widget_text'],
    ],
    'exists derived compound except correlates by alias' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta EXCEPT SELECT option_id FROM import_stage WHERE status = 'ready') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['blogname', 'rewrite_rules'],
    ],
    'in derived subquery returns qualified alias column' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT d.option_id FROM (SELECT option_id FROM import_option_meta) AS d) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'not in derived empty subquery keeps all outer rows' => [
        "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT d.option_id FROM (SELECT option_id FROM import_option_meta WHERE meta_key = 'missing') AS d) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', '_transient_feed', 'rewrite_rules', 'widget_text'],
    ],
    'in derived filtered subquery returns source urls' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT d.option_id FROM (SELECT option_id, meta_key FROM import_option_meta WHERE meta_key = 'source_url') AS d) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home'],
    ],
    'not in derived filtered subquery excludes source urls' => [
        "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT d.option_id FROM (SELECT option_id, meta_key FROM import_option_meta WHERE meta_key = 'source_url') AS d) ORDER BY option_id",
        'option_name',
        ['blogname', '_transient_feed', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived CTE correlates by alias' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (WITH wanted(id) AS (VALUES (1), (5)) SELECT id AS option_id FROM wanted) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'rewrite_rules'],
    ],
    'exists nested derived correlates outer alias' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM (SELECT option_id FROM import_option_meta) AS inner_d) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived alias without as correlates' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta) d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived alias with outer between' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta) AS d WHERE wp_options.option_id BETWEEN d.option_id AND d.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived alias with outer like' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 'Old%' AS pattern FROM import_option_meta WHERE meta_key = 'source_title') AS d WHERE wp_options.option_value LIKE d.pattern) ORDER BY option_id",
        'option_name',
        ['blogname'],
    ],
    'exists derived alias with outer glob' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 'widget_*' AS pattern) AS d WHERE wp_options.option_name GLOB d.pattern) ORDER BY option_id",
        'option_name',
        ['widget_text'],
    ],
    'exists derived alias with outer is comparison' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 'yes' AS autoload) AS d WHERE wp_options.autoload IS d.autoload) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules'],
    ],
    'exists derived alias with outer is not comparison' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 'yes' AS autoload) AS d WHERE wp_options.autoload IS NOT d.autoload) ORDER BY option_id",
        'option_name',
        ['_transient_feed', 'widget_text'],
    ],
    'exists derived alias with outer not distinct comparison' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 'no' AS autoload) AS d WHERE wp_options.autoload IS NOT DISTINCT FROM d.autoload) ORDER BY option_id",
        'option_name',
        ['_transient_feed', 'widget_text'],
    ],
    'exists derived alias with outer distinct comparison' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 'no' AS autoload) AS d WHERE wp_options.autoload IS DISTINCT FROM d.autoload) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules'],
    ],
    'exists derived alias with arithmetic expression' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id * 2 AS doubled FROM import_option_meta) AS d WHERE d.doubled = wp_options.option_id * 2) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived alias with cast expression' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT CAST(option_id AS TEXT) AS id_text FROM import_option_meta) AS d WHERE d.id_text = CAST(wp_options.option_id AS TEXT)) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived alias with scalar function expression' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT upper(meta_key) AS key_name FROM import_option_meta WHERE option_id = wp_options.option_id) AS d WHERE d.key_name = 'SOURCE_URL') ORDER BY option_id",
        'option_name',
        ['siteurl', 'home'],
    ],
    'exists derived alias with case expression' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, CASE status WHEN 'ready' THEN 1 ELSE 0 END AS ready FROM import_stage) AS d WHERE d.option_id = wp_options.option_id AND d.ready = 1) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'widget_text'],
    ],
    'exists derived alias with outer and inner predicates' => [
        "SELECT option_name FROM wp_options WHERE autoload = 'yes' AND EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE meta_key != 'skip_import') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname'],
    ],
    'not exists derived alias with outer and inner predicates' => [
        "SELECT option_name FROM wp_options WHERE autoload = 'yes' AND NOT EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE meta_key != 'skip_import') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['rewrite_rules'],
    ],
    'exists derived alias with subquery order limit' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_stage ORDER BY status DESC, option_id LIMIT 3) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'blogname', 'rewrite_rules'],
    ],
    'exists derived alias with subquery order offset' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_stage ORDER BY status DESC, option_id LIMIT 3 OFFSET 2) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'widget_text'],
    ],
    'in derived subquery with aggregate count' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT d.option_id FROM (SELECT option_id, count(option_id) AS n FROM import_option_meta GROUP BY option_id) AS d WHERE d.n = 1) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'in derived subquery with having count' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT d.option_id FROM (SELECT option_id, count(option_id) AS n FROM import_option_meta GROUP BY option_id HAVING count(*) = 1) AS d) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'in derived subquery with distinct autoload correlation' => [
        "SELECT option_name FROM wp_options WHERE autoload IN (SELECT d.autoload FROM (SELECT DISTINCT autoload FROM wp_options WHERE blog_id = 2) AS d) ORDER BY option_id",
        'option_name',
        ['_transient_feed', 'widget_text'],
    ],
    'exists derived alias with joined source inside subquery' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT s.option_id AS option_id, m.meta_key AS meta_key FROM import_stage AS s JOIN import_option_meta AS m ON m.option_id = s.option_id) AS d WHERE d.option_id = wp_options.option_id AND d.meta_key = 'source_title') ORDER BY option_id",
        'option_name',
        ['blogname'],
    ],
    'exists derived alias with left joined source inside subquery' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT s.option_id AS option_id, m.meta_key AS meta_key FROM import_stage AS s LEFT JOIN import_option_meta AS m ON m.option_id = s.option_id) AS d WHERE d.option_id = wp_options.option_id AND d.meta_key IS NULL) ORDER BY option_id",
        'option_name',
        [],
    ],
    'exists derived alias with inner json expression' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, json_extract('{\"status\":\"ready\"}', '$.status') AS status FROM import_stage) AS d WHERE d.option_id = wp_options.option_id AND d.status = 'ready') ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived alias with outer json expression' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 'ready' AS status) AS d WHERE d.status = json_extract('{\"status\":\"ready\"}', '$.status')) ORDER BY option_id LIMIT 2",
        'option_name',
        ['siteurl', 'home'],
    ],
    'exists derived alias with qualified outer and unqualified inner columns' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_stage WHERE status = 'ready') AS d WHERE option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'widget_text'],
    ],
    'exists derived alias with correlated inner where before derived materialization' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, meta_key FROM import_option_meta WHERE option_id = wp_options.option_id) AS d WHERE d.meta_key LIKE 'source_%') ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname'],
    ],
    'exists derived alias with correlated inner aggregate' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, count(option_id) AS n FROM import_option_meta WHERE option_id = wp_options.option_id GROUP BY option_id) AS d WHERE d.n = 1) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'not exists derived alias with correlated inner aggregate' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT 1 FROM (SELECT option_id, count(option_id) AS n FROM import_option_meta WHERE option_id = wp_options.option_id GROUP BY option_id) AS d WHERE d.n = 1) ORDER BY option_id",
        'option_name',
        ['_transient_feed'],
    ],
    'exists derived alias with correlated inner limit' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id >= wp_options.option_id ORDER BY option_id LIMIT 1) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists derived alias with correlated inner offset' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id >= wp_options.option_id ORDER BY option_id LIMIT 1 OFFSET 1) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        'option_name',
        [],
    ],
];

foreach ($cases as $name => [$sql, $field, $expected]) {
    $tests['select correlated derived current next25 ' . $name] = static function (TestRunner $t) use ($column, $sql, $field, $expected): void {
        $t->same($expected, $column($sql, $field));
    };
}

$tests['select correlated derived current next25 missing derived alias does not expose synthetic qualifier'] = static function (TestRunner $t) use ($column): void {
    $t->throws(InvalidArgumentException::class, static function () use ($column): void {
        $column("SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta) WHERE derived.option_id = wp_options.option_id) ORDER BY option_id", 'option_name');
    });
};

$tests['select correlated derived current next25 scalar subquery reads qualified derived alias'] = static function (TestRunner $t) use ($scalar): void {
    $t->same('source_url', $scalar("SELECT (SELECT d.meta_key FROM (SELECT option_id, meta_key FROM import_option_meta) AS d WHERE d.option_id = wp_options.option_id ORDER BY d.meta_key LIMIT 1) AS first_meta FROM wp_options WHERE option_id = 1", 'first_meta'));
};

$tests['select correlated derived current next25 scalar subquery returns null for missing derived match'] = static function (TestRunner $t) use ($scalar): void {
    $t->same(null, $scalar("SELECT (SELECT d.meta_key FROM (SELECT option_id, meta_key FROM import_option_meta) AS d WHERE d.option_id = wp_options.option_id ORDER BY d.meta_key LIMIT 1) AS first_meta FROM wp_options WHERE option_id = 4", 'first_meta'));
};

$tests['select correlated derived current next25 plan keeps source alias for correlated expansion'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan('SELECT d.option_id FROM (SELECT option_id FROM import_option_meta) AS d', $tables);
    $t->same('d', $plan['sourceAlias'] ?? null);
};

return $tests;

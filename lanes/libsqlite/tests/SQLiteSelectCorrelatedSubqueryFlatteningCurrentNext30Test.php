<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'blog_id' => 1, 'bytes' => 18],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'blog_id' => 1, 'bytes' => 16],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'blog_id' => 1, 'bytes' => 8],
        ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'blog_id' => 1, 'bytes' => 120],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'blog_id' => 1, 'bytes' => 64],
        ['option_id' => 6, 'option_name' => 'widget_text', 'autoload' => 'no', 'blog_id' => 2, 'bytes' => 44],
    ],
    'import_option_meta' => [
        ['option_id' => 1, 'meta_key' => 'source_url', 'meta_value' => 'https://legacy.example', 'rank' => 10],
        ['option_id' => 2, 'meta_key' => 'source_url', 'meta_value' => 'https://legacy.example', 'rank' => 10],
        ['option_id' => 3, 'meta_key' => 'source_title', 'meta_value' => 'Legacy Title', 'rank' => 20],
        ['option_id' => 5, 'meta_key' => 'skip_import', 'meta_value' => '1', 'rank' => 30],
        ['option_id' => 6, 'meta_key' => 'site_scope', 'meta_value' => 'network', 'rank' => 40],
    ],
    'import_stage' => [
        ['option_id' => 1, 'status' => 'ready', 'target_blog' => 1],
        ['option_id' => 2, 'status' => 'ready', 'target_blog' => 1],
        ['option_id' => 3, 'status' => 'review', 'target_blog' => 1],
        ['option_id' => 5, 'status' => 'skip', 'target_blog' => 1],
        ['option_id' => 6, 'status' => 'ready', 'target_blog' => 2],
    ],
];

$column = static function (string $sql, string $column) use ($tables): array {
    return array_column(SQLiteSelectSql::execute($sql, $tables), $column);
};

$scalar = static function (string $sql, string $column) use ($tables) {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    if (count($rows) !== 1) {
        throw new RuntimeException('Expected one scalar row');
    }

    return $rows[0][$column];
};

$cases = [
    'exists compound union arm sees qualified outer column' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists compound union arm false when current row has no metadata' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['_transient_feed'],
    ],
    'exists compound second arm sees qualified outer column' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 99 AS option_id UNION SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND status = 'ready') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'widget_text'],
    ],
    'exists compound union all keeps current-row duplicate harmless' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists compound intersect applies outer row to both arms' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id INTERSECT SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND status = 'ready') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'widget_text'],
    ],
    'exists compound except removes ready rows after correlation' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id EXCEPT SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND status = 'ready') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['blogname', 'rewrite_rules'],
    ],
    'exists compound except leaves transient without correlated metadata false' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id EXCEPT SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND status = 'ready') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', '_transient_feed', 'widget_text'],
    ],
    'exists compound order limit executes per outer row' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id >= wp_options.option_id UNION SELECT option_id FROM import_stage WHERE option_id >= wp_options.option_id ORDER BY option_id LIMIT 1) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists compound order offset executes per outer row' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id >= wp_options.option_id UNION SELECT option_id FROM import_stage WHERE option_id >= wp_options.option_id ORDER BY option_id LIMIT 1 OFFSET 1) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        [],
    ],
    'exists compound comma limit executes per outer row' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id >= wp_options.option_id UNION SELECT option_id FROM import_stage WHERE option_id >= wp_options.option_id ORDER BY option_id LIMIT 0, 1) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists compound derived column list renames correlated arm' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS d(import_id) WHERE d.import_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'exists compound derived default alias is visible to outer predicate' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) WHERE subquery.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'in compound derived returns current row ids' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT d.option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS d) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'not in compound derived preserves missing metadata row' => [
        "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT d.option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS d) ORDER BY option_id",
        ['_transient_feed'],
    ],
    'in compound intersect derived returns ready metadata rows' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT d.option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id INTERSECT SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND status = 'ready') AS d) ORDER BY option_id",
        ['siteurl', 'home', 'widget_text'],
    ],
    'not in compound intersect derived excludes ready metadata rows' => [
        "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT d.option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id INTERSECT SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND status = 'ready') AS d) ORDER BY option_id",
        ['blogname', '_transient_feed', 'rewrite_rules'],
    ],
    'compound derived correlated expression alias matches doubled id' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id * 2 AS doubled FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 0 AS doubled) AS d WHERE d.doubled = wp_options.option_id * 2) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'compound derived correlated cast alias matches text id' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT CAST(option_id AS TEXT) AS id_text FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 'missing' AS id_text) AS d WHERE d.id_text = CAST(wp_options.option_id AS TEXT)) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'compound derived correlated scalar function alias matches option prefix' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT upper(meta_key) AS key_name FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 'NOPE' AS key_name) AS d WHERE d.key_name LIKE 'SOURCE_%') ORDER BY option_id",
        ['siteurl', 'home', 'blogname'],
    ],
    'compound derived correlated case alias matches ready status' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, CASE status WHEN 'ready' THEN 1 ELSE 0 END AS ready FROM import_stage WHERE option_id = wp_options.option_id UNION SELECT 0 AS option_id, 0 AS ready) AS d WHERE d.option_id = wp_options.option_id AND d.ready = 1) ORDER BY option_id",
        ['siteurl', 'home', 'widget_text'],
    ],
    'compound derived correlated left arm can read outer autoload' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND wp_options.autoload = 'yes' UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules'],
    ],
    'compound derived correlated right arm can read outer autoload' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT 99 AS option_id UNION SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND wp_options.autoload = 'no') AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['widget_text'],
    ],
    'compound derived correlated arm with outer like guard' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id AND wp_options.option_name LIKE 'site%' UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl'],
    ],
    'compound derived correlated arm with outer glob guard' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id AND wp_options.option_name GLOB 'widget_*' UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['widget_text'],
    ],
    'compound derived correlated arm with outer between guard' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id AND wp_options.bytes BETWEEN rank AND 100 UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'rewrite_rules', 'widget_text'],
    ],
    'compound derived correlated arm with outer not between guard' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id AND wp_options.bytes NOT BETWEEN rank AND 100 UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['blogname'],
    ],
    'compound derived correlated arm with outer is comparison' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND wp_options.autoload IS 'yes' UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules'],
    ],
    'compound derived correlated arm with outer is not comparison' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND wp_options.autoload IS NOT 'yes' UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['widget_text'],
    ],
    'compound derived correlated arm with outer distinct comparison' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND wp_options.autoload IS DISTINCT FROM 'no' UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules'],
    ],
    'compound derived correlated arm with outer not distinct comparison' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id AND wp_options.autoload IS NOT DISTINCT FROM 'no' UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['widget_text'],
    ],
    'compound derived with joined arm sees outer row' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT m.option_id AS option_id FROM import_option_meta AS m JOIN import_stage AS s ON s.option_id = m.option_id WHERE m.option_id = wp_options.option_id AND s.status = 'review' UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['blogname'],
    ],
    'compound derived with left joined arm sees outer row' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT s.option_id AS option_id FROM import_stage AS s LEFT JOIN import_option_meta AS m ON m.option_id = s.option_id WHERE s.option_id = wp_options.option_id AND m.option_id IS NULL UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        [],
    ],
    'compound derived chained through scalar subquery returns first correlated meta key' => [
        "SELECT (SELECT d.meta_key FROM (SELECT meta_key FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 'zz_missing' AS meta_key ORDER BY meta_key LIMIT 1) AS d) AS first_meta FROM wp_options WHERE option_id = 1",
        ['source_url'],
    ],
    'compound derived chained through scalar subquery returns fallback for no metadata' => [
        "SELECT (SELECT d.meta_key FROM (SELECT meta_key FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 'zz_missing' AS meta_key ORDER BY meta_key LIMIT 1) AS d) AS first_meta FROM wp_options WHERE option_id = 4",
        ['zz_missing'],
    ],
    'compound derived nested inside exists retains outer row' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS inner_d) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'compound derived nested inside in retains outer row' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT d.option_id FROM (SELECT option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS inner_d) AS d) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text'],
    ],
    'compound derived under outer and preserves current row' => [
        "SELECT option_name FROM wp_options WHERE autoload = 'yes' AND EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'rewrite_rules'],
    ],
    'compound derived under outer or preserves current row' => [
        "SELECT option_name FROM wp_options WHERE option_name = '_transient_feed' OR EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id AND meta_key = 'source_title' UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['blogname', '_transient_feed'],
    ],
    'compound derived current row respects outer final limit' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_name LIMIT 3",
        ['blogname', 'home', 'rewrite_rules'],
    ],
    'compound derived current row respects outer final offset' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_name LIMIT 2 OFFSET 3",
        ['siteurl', 'widget_text'],
    ],
];

foreach ($cases as $name => [$sql, $expected]) {
    $tests['select correlated subquery flattening current next30 ' . $name] = static function (TestRunner $t) use ($column, $sql, $expected): void {
        $field = str_contains($sql, ' AS first_meta ') ? 'first_meta' : 'option_name';
        $t->same($expected, $column($sql, $field));
    };
}

$tests['select correlated subquery flattening current next30 scalar compound derived first row'] = static function (TestRunner $t) use ($scalar): void {
    $t->same('source_url', $scalar("SELECT (SELECT d.meta_key FROM (SELECT meta_key FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 'zz_missing' AS meta_key ORDER BY meta_key LIMIT 1) AS d) AS first_meta FROM wp_options WHERE option_id = 1", 'first_meta'));
};

$tests['select correlated subquery flattening current next30 compound arm plan records compound arms'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan("SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT 99 AS option_id) AS d WHERE d.option_id = wp_options.option_id)", $tables);
    $t->same('option_name', $plan['select'][0]['name'] ?? null);
};

return $tests;

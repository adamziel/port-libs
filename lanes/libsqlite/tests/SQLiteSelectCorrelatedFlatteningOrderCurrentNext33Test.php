<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'blog_id' => 1],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'blog_id' => 1],
        ['option_id' => 6, 'option_name' => 'widget_text', 'autoload' => 'no', 'blog_id' => 2],
        ['option_id' => 7, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'blog_id' => 2],
    ],
    'import_option_meta' => [
        ['option_id' => 1, 'meta_key' => 'source_url', 'rank' => 20, 'status' => 'ready'],
        ['option_id' => 1, 'meta_key' => 'checksum', 'rank' => 10, 'status' => 'ready'],
        ['option_id' => 2, 'meta_key' => 'source_url', 'rank' => 30, 'status' => 'ready'],
        ['option_id' => 2, 'meta_key' => 'redirect', 'rank' => 40, 'status' => 'review'],
        ['option_id' => 3, 'meta_key' => 'source_title', 'rank' => 15, 'status' => 'review'],
        ['option_id' => 5, 'meta_key' => 'skip_import', 'rank' => 50, 'status' => 'skip'],
        ['option_id' => 6, 'meta_key' => 'widget_area', 'rank' => 25, 'status' => 'ready'],
        ['option_id' => 7, 'meta_key' => 'theme_json', 'rank' => 5, 'status' => 'ready'],
        ['option_id' => 7, 'meta_key' => 'theme_mod', 'rank' => 35, 'status' => 'review'],
    ],
    'import_stage' => [
        ['option_id' => 1, 'stage_key' => 'primary', 'priority' => 5, 'status' => 'ready'],
        ['option_id' => 1, 'stage_key' => 'archive', 'priority' => 25, 'status' => 'ready'],
        ['option_id' => 2, 'stage_key' => 'primary', 'priority' => 15, 'status' => 'ready'],
        ['option_id' => 3, 'stage_key' => 'review', 'priority' => 45, 'status' => 'review'],
        ['option_id' => 4, 'stage_key' => 'cache', 'priority' => 60, 'status' => 'skip'],
        ['option_id' => 6, 'stage_key' => 'widget', 'priority' => 22, 'status' => 'ready'],
        ['option_id' => 7, 'stage_key' => 'theme', 'priority' => 12, 'status' => 'ready'],
    ],
];

$column = static function (string $sql, string $field = 'option_name') use ($tables): array {
    return array_column(SQLiteSelectSql::execute($sql, $tables), $field);
};

$cases = [
    'exists union all lower ranked metadata first' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, meta_key, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, stage_key AS meta_key, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1) AS d WHERE d.option_id = wp_options.option_id AND d.rank < 30) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'widget_text', 'theme_mods'],
    ],
    'exists union all offset sees next row per current option' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, meta_key, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, stage_key AS meta_key, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1 OFFSET 1) AS d WHERE d.option_id = wp_options.option_id AND d.rank < 30) ORDER BY option_id",
        ['siteurl', 'widget_text', 'theme_mods'],
    ],
    'not exists union all offset isolates missing second current row' => [
        "SELECT option_name FROM wp_options WHERE NOT EXISTS (SELECT 1 FROM (SELECT option_id, meta_key, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, stage_key AS meta_key, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1 OFFSET 1) AS d WHERE d.option_id = wp_options.option_id AND d.rank < 30) ORDER BY option_id",
        ['home', 'blogname', '_transient_feed', 'rewrite_rules'],
    ],
    'scalar union all first key observes current order' => [
        "SELECT option_name, (SELECT d.meta_key FROM (SELECT option_id, meta_key, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, stage_key AS meta_key, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1) AS d WHERE d.option_id = wp_options.option_id) AS picked FROM wp_options ORDER BY option_id",
        ['primary', 'primary', 'source_title', 'cache', 'skip_import', 'widget', 'theme_json'],
        'picked',
    ],
    'scalar union all next key observes current offset' => [
        "SELECT option_name, (SELECT d.meta_key FROM (SELECT option_id, meta_key, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, stage_key AS meta_key, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1 OFFSET 1) AS d WHERE d.option_id = wp_options.option_id) AS picked FROM wp_options ORDER BY option_id",
        ['checksum', 'source_url', 'review', null, null, 'widget_area', 'theme'],
        'picked',
    ],
    'in union all current first ids' => [
        "SELECT option_name FROM wp_options WHERE option_id IN (SELECT d.option_id FROM (SELECT option_id, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1) AS d WHERE d.rank <= 15) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'theme_mods'],
    ],
    'not in union all current first ids' => [
        "SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT d.option_id FROM (SELECT option_id, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1) AS d WHERE d.rank <= 15) ORDER BY option_id",
        ['_transient_feed', 'rewrite_rules', 'widget_text'],
    ],
    'exists union distinct collapses duplicate current option' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, status FROM import_option_meta WHERE option_id = wp_options.option_id UNION SELECT option_id, status FROM import_stage WHERE option_id = wp_options.option_id ORDER BY status LIMIT 1) AS d WHERE d.option_id = wp_options.option_id AND d.status = 'ready') ORDER BY option_id",
        ['siteurl', 'home', 'widget_text', 'theme_mods'],
    ],
    'exists union all descending picks review before ready' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, status, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, status, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY status DESC, rank LIMIT 1) AS d WHERE d.option_id = wp_options.option_id AND d.status = 'review') ORDER BY option_id",
        ['home', 'blogname', 'theme_mods'],
    ],
    'exists except removes staged current option' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id EXCEPT SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['rewrite_rules'],
    ],
    'exists intersect keeps metadata and stage current option' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id INTERSECT SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id) AS d WHERE d.option_id = wp_options.option_id) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'widget_text', 'theme_mods'],
    ],
    'scalar except returns null for staged current option' => [
        "SELECT option_name, (SELECT d.option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id EXCEPT SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id) AS d WHERE d.option_id = wp_options.option_id) AS orphan_meta FROM wp_options ORDER BY option_id",
        [null, null, null, null, 5, null, null],
        'orphan_meta',
    ],
    'scalar intersect returns current ids only when both sources match' => [
        "SELECT option_name, (SELECT d.option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id INTERSECT SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id) AS d WHERE d.option_id = wp_options.option_id) AS matched FROM wp_options ORDER BY option_id",
        [1, 2, 3, null, null, 6, 7],
        'matched',
    ],
    'exists union all current expression in arm predicate' => [
        "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, rank FROM import_option_meta WHERE option_id + 0 = wp_options.option_id UNION ALL SELECT option_id, priority AS rank FROM import_stage WHERE option_id + 0 = wp_options.option_id ORDER BY rank LIMIT 1) AS d WHERE d.option_id = wp_options.option_id AND d.rank < 25) ORDER BY option_id",
        ['siteurl', 'home', 'blogname', 'widget_text', 'theme_mods'],
    ],
    'scalar union all outer order desc current first row' => [
        "SELECT option_name, (SELECT d.meta_key FROM (SELECT option_id, meta_key, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, stage_key AS meta_key, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank DESC LIMIT 1) AS d WHERE d.option_id = wp_options.option_id) AS picked FROM wp_options ORDER BY option_id",
        ['archive', 'redirect', 'review', 'cache', 'skip_import', 'widget_area', 'theme_mod'],
        'picked',
    ],
];

foreach ($cases as $name => $case) {
    [$sql, $expected] = $case;
    $field = $case[2] ?? 'option_name';
    $tests['select correlated flattening order current next33 ' . $name] = static function (TestRunner $t) use ($column, $sql, $expected, $field): void {
        $t->same($expected, $column($sql, $field));
    };
}

foreach (range(1, 7) as $optionId) {
    $tests['select correlated flattening order current next33 per row first rank option ' . $optionId] = static function (TestRunner $t) use ($tables, $optionId): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT option_name, (SELECT d.rank FROM (SELECT option_id, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1) AS d WHERE d.option_id = wp_options.option_id) AS picked_rank FROM wp_options WHERE option_id = {$optionId}",
            $tables
        );
        $expected = [1 => 5, 2 => 15, 3 => 15, 4 => 60, 5 => 50, 6 => 22, 7 => 5][$optionId];
        $t->same($expected, $rows[0]['picked_rank']);
    };
}

$firstKeys = [1 => 'primary', 2 => 'primary', 3 => 'source_title', 4 => 'cache', 5 => 'skip_import', 6 => 'widget', 7 => 'theme_json'];
$secondKeys = [1 => 'checksum', 2 => 'source_url', 3 => 'review', 4 => null, 5 => null, 6 => 'widget_area', 7 => 'theme'];
$lastKeys = [1 => 'archive', 2 => 'redirect', 3 => 'review', 4 => 'cache', 5 => 'skip_import', 6 => 'widget_area', 7 => 'theme_mod'];

foreach ($firstKeys as $optionId => $expectedKey) {
    $tests['select correlated flattening order current next33 per row first key option ' . $optionId] = static function (TestRunner $t) use ($tables, $optionId, $expectedKey): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT (SELECT d.import_key FROM (SELECT option_id, meta_key AS import_key, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, stage_key AS import_key, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1) AS d WHERE d.option_id = wp_options.option_id) AS picked FROM wp_options WHERE option_id = {$optionId}",
            $tables
        );
        $t->same($expectedKey, $rows[0]['picked']);
    };
}

foreach ($secondKeys as $optionId => $expectedKey) {
    $tests['select correlated flattening order current next33 per row next key option ' . $optionId] = static function (TestRunner $t) use ($tables, $optionId, $expectedKey): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT (SELECT d.import_key FROM (SELECT option_id, meta_key AS import_key, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, stage_key AS import_key, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1 OFFSET 1) AS d WHERE d.option_id = wp_options.option_id) AS picked FROM wp_options WHERE option_id = {$optionId}",
            $tables
        );
        $t->same($expectedKey, $rows[0]['picked']);
    };
}

foreach ($lastKeys as $optionId => $expectedKey) {
    $tests['select correlated flattening order current next33 per row desc key option ' . $optionId] = static function (TestRunner $t) use ($tables, $optionId, $expectedKey): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT (SELECT d.import_key FROM (SELECT option_id, meta_key AS import_key, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, stage_key AS import_key, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank DESC LIMIT 1) AS d WHERE d.option_id = wp_options.option_id) AS picked FROM wp_options WHERE option_id = {$optionId}",
            $tables
        );
        $t->same($expectedKey, $rows[0]['picked']);
    };
}

$thresholds = [
    10 => ['siteurl', 'theme_mods'],
    15 => ['siteurl', 'home', 'blogname', 'theme_mods'],
    20 => ['siteurl', 'home', 'blogname', 'theme_mods'],
    25 => ['siteurl', 'home', 'blogname', 'widget_text', 'theme_mods'],
    30 => ['siteurl', 'home', 'blogname', 'widget_text', 'theme_mods'],
    50 => ['siteurl', 'home', 'blogname', 'rewrite_rules', 'widget_text', 'theme_mods'],
];

foreach ($thresholds as $threshold => $expected) {
    $tests['select correlated flattening order current next33 threshold first rank ' . $threshold] = static function (TestRunner $t) use ($column, $threshold, $expected): void {
        $t->same($expected, $column("SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, rank FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id, priority AS rank FROM import_stage WHERE option_id = wp_options.option_id ORDER BY rank LIMIT 1) AS d WHERE d.option_id = wp_options.option_id AND d.rank <= {$threshold}) ORDER BY option_id"));
    };
}

$tests['select correlated flattening order current next33 compound plan carries arms'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan(
        'SELECT d.option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = 1 UNION ALL SELECT option_id FROM import_stage WHERE option_id = 1 ORDER BY option_id LIMIT 1) AS d',
        $tables
    );
    $t->same('d', $plan['sourceAlias'] ?? null);
};

$tests['select correlated flattening order current next33 rejects missing outer row in correlated arm'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
        'SELECT d.option_id FROM (SELECT option_id FROM import_option_meta WHERE option_id = wp_options.option_id UNION ALL SELECT option_id FROM import_stage WHERE option_id = wp_options.option_id) AS d',
        $tables
    ));
};

return $tests;

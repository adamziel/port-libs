<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'kind' => 'url', 'priority' => 10],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'kind' => 'url', 'priority' => 20],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'kind' => 'text', 'priority' => 30],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'kind' => 'cache', 'priority' => 40],
    ['option_id' => 5, 'option_name' => 'widget_recent', 'autoload' => 'no', 'kind' => 'widget', 'priority' => 50],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => null, 'kind' => 'theme', 'priority' => 60],
    ['option_id' => 7, 'option_name' => 'orphaned', 'autoload' => null, 'kind' => null, 'priority' => 70],
];

$metadata = [
    ['meta_option_id' => 1, 'meta_key' => 'load', 'meta_value' => 'yes', 'site_id' => 1, 'rank' => 10],
    ['meta_option_id' => 2, 'meta_key' => 'load', 'meta_value' => 'yes', 'site_id' => 1, 'rank' => 20],
    ['meta_option_id' => 3, 'meta_key' => 'load', 'meta_value' => 'yes', 'site_id' => 2, 'rank' => 30],
    ['meta_option_id' => 4, 'meta_key' => 'load', 'meta_value' => 'no', 'site_id' => 1, 'rank' => 40],
    ['meta_option_id' => 5, 'meta_key' => 'load', 'meta_value' => 'no', 'site_id' => 2, 'rank' => 50],
    ['meta_option_id' => 6, 'meta_key' => 'load', 'meta_value' => null, 'site_id' => 2, 'rank' => 60],
    ['meta_option_id' => 8, 'meta_key' => 'load', 'meta_value' => 'missing', 'site_id' => 9, 'rank' => 80],
    ['meta_option_id' => null, 'meta_key' => 'load', 'meta_value' => 'yes', 'site_id' => 3, 'rank' => 90],
    ['meta_option_id' => 3, 'meta_key' => 'kind', 'meta_value' => 'text', 'site_id' => 2, 'rank' => 31],
    ['meta_option_id' => 6, 'meta_key' => 'kind', 'meta_value' => 'theme', 'site_id' => 2, 'rank' => 61],
];

$visibility = [
    ['site_id' => 1, 'option_id' => 1, 'visibility' => 'front'],
    ['site_id' => 1, 'option_id' => 2, 'visibility' => 'front'],
    ['site_id' => 1, 'option_id' => 4, 'visibility' => 'cron'],
    ['site_id' => 2, 'option_id' => 3, 'visibility' => 'front'],
    ['site_id' => 2, 'option_id' => 5, 'visibility' => 'admin'],
    ['site_id' => 2, 'option_id' => 6, 'visibility' => 'theme'],
];

$tables = [
    'wp_options' => $options,
    'option_meta' => $metadata,
    'site_visibility' => $visibility,
];

$cases = [
    'matches id and autoload from metadata' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load') ORDER BY id",
        ['siteurl', 'home', 'blogname', '_transient_feed', 'widget_recent'],
    ],
    'not in excludes known metadata pairs and null unknown rows' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load') ORDER BY id",
        [],
    ],
    'not in ignores null row when earlier column mismatches decisively' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_option_id > 20 OR meta_option_id IS NULL) ORDER BY id",
        ['_transient_feed', 'widget_recent'],
    ],
    'in with null candidate yields unknown for null-equivalent row' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_option_id = 6) ORDER BY id",
        [],
    ],
    'not in with null candidate yields unknown for null-equivalent row' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_option_id = 6) ORDER BY id",
        ['siteurl', 'home', 'blogname', '_transient_feed', 'widget_recent', 'orphaned'],
    ],
    'empty in subquery filters all rows' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'missing') ORDER BY id",
        [],
    ],
    'empty not in subquery preserves all rows including null tuples' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'missing') ORDER BY id",
        ['siteurl', 'home', 'blogname', '_transient_feed', 'widget_recent', 'theme_mods', 'orphaned'],
    ],
    'values cte drives row-value in subquery' => [
        "WITH wanted(id, load_state) AS (VALUES (1, 'yes'), (4, 'no'), (6, NULL)) SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT id, load_state FROM wanted) ORDER BY id",
        ['siteurl', '_transient_feed'],
    ],
    'values cte drives row-value not in subquery' => [
        "WITH wanted(id, load_state) AS (VALUES (1, 'yes'), (4, 'no')) SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT id, load_state FROM wanted) ORDER BY id",
        ['home', 'blogname', 'widget_recent', 'theme_mods', 'orphaned'],
    ],
    'correlated in uses outer autoload' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options AS o WHERE (option_id, autoload) IN (SELECT meta_option_id, autoload FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'load') ORDER BY id",
        ['siteurl', 'home', 'blogname', '_transient_feed', 'widget_recent'],
    ],
    'correlated not in removes matching outer tuple' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options AS o WHERE (option_id, autoload) NOT IN (SELECT meta_option_id, autoload FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'load') ORDER BY id",
        ['orphaned'],
    ],
    'correlated in can compare expression tuple' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options AS o WHERE (option_id + 10, priority / 10) IN (SELECT meta_option_id + 10, rank / 10 FROM option_meta WHERE meta_key = 'load') ORDER BY id",
        ['siteurl', 'home', 'blogname', '_transient_feed', 'widget_recent', 'theme_mods'],
    ],
    'subquery order by limit keeps first tuple only' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load' AND meta_option_id IS NOT NULL ORDER BY meta_option_id LIMIT 1) ORDER BY id",
        ['siteurl'],
    ],
    'subquery order by limit offset keeps later tuple' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load' AND meta_option_id IS NOT NULL ORDER BY meta_option_id LIMIT 1 OFFSET 2) ORDER BY id",
        ['blogname'],
    ],
    'subquery comma limit keeps later tuple' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load' AND meta_option_id IS NOT NULL ORDER BY meta_option_id LIMIT 3, 1) ORDER BY id",
        ['_transient_feed'],
    ],
    'joined subquery source returns tuple candidates' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT v.option_id, m.meta_value FROM site_visibility AS v JOIN option_meta AS m ON v.option_id = m.meta_option_id WHERE m.meta_key = 'load' AND v.site_id = 1) ORDER BY id",
        ['siteurl', 'home', '_transient_feed'],
    ],
    'left join subquery source returns tuple candidates' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT v.option_id, m.meta_value FROM site_visibility AS v LEFT JOIN option_meta AS m ON v.option_id = m.meta_option_id AND m.meta_key = 'load' WHERE v.site_id = 2) ORDER BY id",
        ['blogname', 'widget_recent'],
    ],
    'row-value in under and predicate' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE kind IS NOT NULL AND (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load') ORDER BY id",
        ['siteurl', 'home', 'blogname', '_transient_feed', 'widget_recent'],
    ],
    'row-value in under or predicate' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE option_name = 'orphaned' OR (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE site_id = 1) ORDER BY id",
        ['siteurl', 'home', '_transient_feed', 'orphaned'],
    ],
    'row-value not in under and predicate' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE priority > 30 AND (option_id, autoload) NOT IN (SELECT meta_option_id, meta_value FROM option_meta WHERE site_id = 1) ORDER BY id",
        ['widget_recent', 'theme_mods', 'orphaned'],
    ],
    'row-value in with text tuple' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name, kind) IN (SELECT 'blogname', 'text' FROM option_meta WHERE meta_option_id = 3) ORDER BY id",
        ['blogname'],
    ],
    'row-value in with concatenated text tuple' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_name || ':' || autoload, kind) IN (SELECT 'siteurl:yes', 'url' FROM option_meta WHERE meta_option_id = 1) ORDER BY id",
        ['siteurl'],
    ],
    'row-value in with scalar function tuple' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (upper(option_name), lower(kind)) IN (SELECT 'HOME', 'url' FROM option_meta WHERE meta_option_id = 2) ORDER BY id",
        ['home'],
    ],
    'row-value in with blob-compatible text misses different type' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, option_name) IN (SELECT meta_option_id, X'686f6d65' FROM option_meta WHERE meta_option_id = 2) ORDER BY id",
        [],
    ],
    'row-value in with numeric text does not match numeric tuple' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, priority) IN (SELECT meta_option_id, '20' FROM option_meta WHERE meta_option_id = 2) ORDER BY id",
        [],
    ],
    'row-value in with arithmetic numeric tuple matches' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, priority) IN (SELECT meta_option_id, rank FROM option_meta WHERE meta_option_id = 2) ORDER BY id",
        ['home'],
    ],
    'row-value in final order by expression' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load') ORDER BY id DESC LIMIT 2",
        ['widget_recent', '_transient_feed'],
    ],
    'row-value in final offset' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load') ORDER BY id LIMIT 2 OFFSET 2",
        ['blogname', '_transient_feed'],
    ],
    'row-value in with distinct subquery tuples' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (autoload, kind) IN (SELECT DISTINCT meta_value, 'url' FROM option_meta WHERE meta_key = 'load') ORDER BY id",
        ['siteurl', 'home'],
    ],
    'row-value in with grouped subquery tuples' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (autoload, kind) IN (SELECT meta_value, 'url' FROM option_meta WHERE meta_key = 'load' GROUP BY meta_value) ORDER BY id",
        InvalidArgumentException::class,
    ],
    'row-value in with having subquery tuples' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (autoload, kind) IN (SELECT meta_value, 'url' FROM option_meta WHERE meta_key = 'load' GROUP BY meta_value HAVING count(*) > 1) ORDER BY id",
        ['siteurl', 'home'],
    ],
    'row-value in against cte select projection aliases' => [
        "WITH pairs AS (SELECT meta_option_id AS id, meta_value AS load_state FROM option_meta WHERE meta_key = 'load') SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT id, load_state FROM pairs) ORDER BY id",
        ['siteurl', 'home', 'blogname', '_transient_feed', 'widget_recent'],
    ],
    'row-value in inside cte consumer' => [
        "WITH picked AS (SELECT option_id, option_name, autoload FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE site_id = 1)) SELECT option_id AS id, option_name AS name FROM picked ORDER BY id",
        ['siteurl', 'home', '_transient_feed'],
    ],
    'row-value not in inside cte consumer' => [
        "WITH picked AS (SELECT option_id, option_name, autoload FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT meta_option_id, meta_value FROM option_meta WHERE site_id = 1)) SELECT option_id AS id, option_name AS name FROM picked ORDER BY id",
        ['blogname', 'widget_recent', 'theme_mods', 'orphaned'],
    ],
    'row-value in joined outer query' => [
        "SELECT o.option_id AS id, o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE (o.option_id, v.site_id) IN (SELECT meta_option_id, site_id FROM option_meta WHERE meta_key = 'load') ORDER BY name",
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods', 'widget_recent'],
    ],
    'row-value not in joined outer query' => [
        "SELECT o.option_id AS id, o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE (o.option_id, v.site_id) NOT IN (SELECT meta_option_id, site_id FROM option_meta WHERE meta_key = 'load') ORDER BY name",
        [],
    ],
    'row-value in left join outer query keeps unmatched through explicit tuple' => [
        "SELECT o.option_id AS id, o.option_name AS name FROM wp_options AS o LEFT JOIN site_visibility AS v ON o.option_id = v.option_id WHERE (o.option_id, ifnull(v.site_id, 0)) IN (SELECT 7, 0 FROM option_meta WHERE meta_key = 'load') ORDER BY name",
        ['orphaned'],
    ],
    'row-value in correlated joined subquery' => [
        "SELECT o.option_id AS id, o.option_name AS name FROM wp_options AS o JOIN site_visibility AS v ON o.option_id = v.option_id WHERE (o.option_id, v.visibility) IN (SELECT option_id, visibility FROM site_visibility WHERE site_id = v.site_id) ORDER BY name",
        ['_transient_feed', 'blogname', 'home', 'siteurl', 'theme_mods', 'widget_recent'],
    ],
    'row-value in correlated subquery with null outer value is unknown' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options AS o WHERE (option_id, kind) IN (SELECT meta_option_id, kind FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'kind') ORDER BY id",
        ['blogname', 'theme_mods'],
    ],
    'row-value not in correlated subquery with null outer value is unknown' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options AS o WHERE (option_id, kind) NOT IN (SELECT meta_option_id, kind FROM option_meta WHERE meta_option_id = option_id AND meta_key = 'kind') ORDER BY id",
        ['siteurl', 'home', '_transient_feed', 'widget_recent', 'orphaned'],
    ],
    'row-value in subquery can feed select distinct' => [
        "SELECT DISTINCT autoload AS load_state FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load') ORDER BY load_state",
        [],
    ],
    'row-value in subquery can feed aggregate group' => [
        "SELECT autoload AS load_state, count(*) AS total FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE meta_key = 'load') GROUP BY autoload ORDER BY load_state",
        ['no:2', 'yes:3'],
        static fn (array $rows): array => array_map(static fn (array $row): string => $row['load_state'] . ':' . $row['total'], $rows),
    ],
    'row-value in subquery can feed compound arm' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE site_id = 1) UNION SELECT option_id AS id, option_name AS name FROM wp_options WHERE option_name = 'theme_mods' ORDER BY name",
        ['_transient_feed', 'home', 'siteurl', 'theme_mods'],
    ],
    'row-value in subquery with bind parameters' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE site_id = :site AND meta_key = :key) ORDER BY id",
        ['siteurl', 'home', '_transient_feed'],
        null,
        ['site' => 1, 'key' => 'load'],
    ],
    'row-value in subquery with positional bind parameters' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT meta_option_id, meta_value FROM option_meta WHERE site_id = ? AND meta_key = ?) ORDER BY id",
        ['blogname', 'widget_recent'],
        null,
        [2, 'load'],
    ],
    'row-value in with duplicate subquery tuples still matches once' => [
        "WITH pairs(id, load_state) AS (VALUES (1, 'yes'), (1, 'yes'), (2, 'yes')) SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) IN (SELECT id, load_state FROM pairs) ORDER BY id",
        ['siteurl', 'home'],
    ],
    'row-value not in with duplicate subquery tuples excludes once' => [
        "WITH pairs(id, load_state) AS (VALUES (1, 'yes'), (1, 'yes'), (2, 'yes')) SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload) NOT IN (SELECT id, load_state FROM pairs) ORDER BY id",
        ['blogname', '_transient_feed', 'widget_recent', 'theme_mods', 'orphaned'],
    ],
    'scalar in subquery still rejects multi-column result' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE option_id IN (SELECT meta_option_id, meta_value FROM option_meta)",
        InvalidArgumentException::class,
    ],
    'row-value in subquery rejects mismatched tuple width' => [
        "SELECT option_id AS id, option_name AS name FROM wp_options WHERE (option_id, autoload, kind) IN (SELECT meta_option_id, meta_value FROM option_meta)",
        InvalidArgumentException::class,
    ],
    'manual predicate row-value in subquery callable matches' => [
        null,
        true,
        static fn (): mixed => SQLiteSelectPredicate::evaluate(
            ['option_id' => 1, 'autoload' => 'yes'],
            [
                'operator' => 'IN',
                'left' => ['type' => 'row', 'values' => [
                    ['type' => 'column', 'name' => 'option_id'],
                    ['type' => 'column', 'name' => 'autoload'],
                ]],
                'valuesSubquery' => static fn (): array => [[1, 'yes'], [2, 'no']],
            ],
        ),
    ],
    'manual predicate row-value not in subquery callable sees null candidate' => [
        null,
        null,
        static fn (): mixed => SQLiteSelectPredicate::evaluate(
            ['option_id' => 6, 'autoload' => null],
            [
                'operator' => 'NOT IN',
                'left' => ['type' => 'row', 'values' => [
                    ['type' => 'column', 'name' => 'option_id'],
                    ['type' => 'column', 'name' => 'autoload'],
                ]],
                'valuesSubquery' => static fn (): array => [[6, null]],
            ],
        ),
    ],
];

foreach ($cases as $name => $case) {
    [$sql, $expected] = $case;
    $projector = $case[2] ?? null;
    $parameters = $case[3] ?? [];
    $tests['upstream row-value in-subquery corpus ' . $name] = static function (TestRunner $t) use ($sql, $expected, $projector, $parameters, $tables): void {
        if ($sql === null) {
            $t->same($expected, $projector());
            return;
        }
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, static fn () => SQLiteSelectSql::execute($sql, $tables, $parameters ?? []));
            return;
        }

        $rows = SQLiteSelectSql::execute($sql, $tables, $parameters ?? []);
        $actual = is_callable($projector) ? $projector($rows) : array_column($rows, 'name');
        $t->same($expected, $actual);
    };
}

return $tests;

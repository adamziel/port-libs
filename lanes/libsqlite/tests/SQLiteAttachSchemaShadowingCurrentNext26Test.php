<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'main.wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://main.test', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'template', 'option_value' => 'twentytwentyfive', 'autoload' => 'yes'],
    ],
    'temp.wp_options' => [
        ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://temp.test', 'autoload' => 'no'],
        ['option_id' => 102, 'option_name' => 'scratch', 'option_value' => 'temp-only', 'autoload' => 'yes'],
    ],
    'site.wp_options' => [
        ['blog_id' => 7, 'option_id' => 201, 'option_name' => 'siteurl', 'option_value' => 'https://site.test', 'autoload' => 'yes'],
        ['blog_id' => 7, 'option_id' => 202, 'option_name' => 'stylesheet', 'option_value' => 'child-theme', 'autoload' => 'yes'],
    ],
    'archive.wp_options' => [
        ['option_id' => 301, 'option_name' => 'siteurl', 'option_value' => 'https://archive.test', 'autoload' => 'no'],
        ['option_id' => 302, 'option_name' => 'template', 'option_value' => 'old-theme', 'autoload' => 'yes'],
    ],
    'main.wp_sitemeta' => [
        ['meta_key' => 'network_name', 'meta_value' => 'Main Network'],
    ],
    'site.wp_sitemeta' => [
        ['meta_key' => 'network_name', 'meta_value' => 'Attached Network'],
    ],
];

$value = static fn (string $sql, string $column, array $sourceTables = null): mixed => SQLiteSelectSql::execute($sql, $sourceTables ?? $tables)[0][$column];
$rows = static fn (string $sql, array $sourceTables = null): array => SQLiteSelectSql::execute($sql, $sourceTables ?? $tables);

$tests = [];

foreach ([
    'unqualified table resolves temp before main' => ['SELECT option_value FROM wp_options WHERE option_name = \'siteurl\'', 'option_value', 'https://temp.test'],
    'main qualifier bypasses temp shadow' => ['SELECT option_value FROM main.wp_options WHERE option_name = \'siteurl\'', 'option_value', 'https://main.test'],
    'temp qualifier pins temp schema' => ['SELECT option_value FROM temp.wp_options WHERE option_name = \'siteurl\'', 'option_value', 'https://temp.test'],
    'site qualifier pins first attached schema' => ['SELECT option_value FROM site.wp_options WHERE option_name = \'siteurl\'', 'option_value', 'https://site.test'],
    'archive qualifier pins later attached schema' => ['SELECT option_value FROM archive.wp_options WHERE option_name = \'siteurl\'', 'option_value', 'https://archive.test'],
    'attached unqualified fallback follows table insertion order after main/temp' => ['SELECT meta_value FROM wp_sitemeta WHERE meta_key = \'network_name\'', 'meta_value', 'Main Network'],
    'attached explicit qualifier bypasses main fallback' => ['SELECT meta_value FROM site.wp_sitemeta WHERE meta_key = \'network_name\'', 'meta_value', 'Attached Network'],
    'qualified table projects unqualified columns' => ['SELECT option_value AS value FROM main.wp_options WHERE option_name = \'template\'', 'value', 'twentytwentyfive'],
    'qualified attached table feeds predicates' => ['SELECT option_value AS value FROM site.wp_options WHERE option_name = \'stylesheet\'', 'value', 'child-theme'],
    'qualified archive table feeds order by' => ['SELECT option_name FROM archive.wp_options ORDER BY option_value LIMIT 1', 'option_name', 'siteurl'],
    'temp source participates in scalar expressions' => ['SELECT option_name || \':\' || option_value AS pair FROM temp.wp_options WHERE option_id = 102', 'pair', 'scratch:temp-only'],
    'main schema participates in limit ordering' => ['SELECT option_name FROM main.wp_options ORDER BY option_id DESC LIMIT 1', 'option_name', 'template'],
    'site schema projects integer columns' => ['SELECT blog_id FROM site.wp_options WHERE option_name = \'stylesheet\'', 'blog_id', 7],
] as $name => [$sql, $column, $expected]) {
    $tests['attach schema shadowing current next26 ' . $name] = static function (TestRunner $t) use ($value, $sql, $column, $expected): void {
        $t->same($expected, $value($sql, $column));
    };
}

foreach ([
    'unqualified temp row count' => ['SELECT option_name FROM wp_options', 2],
    'main qualified row count' => ['SELECT option_name FROM main.wp_options', 2],
    'site qualified row count' => ['SELECT option_name FROM site.wp_options', 2],
    'archive qualified row count' => ['SELECT option_name FROM archive.wp_options', 2],
    'main filtered empty ignores temp rows' => ['SELECT option_name FROM main.wp_options WHERE autoload = \'no\'', 0],
    'temp filtered one ignores main rows' => ['SELECT option_name FROM temp.wp_options WHERE autoload = \'no\'', 1],
    'site filtered empty ignores archive rows' => ['SELECT option_name FROM site.wp_options WHERE option_name = \'template\'', 0],
    'archive filtered one ignores main rows' => ['SELECT option_name FROM archive.wp_options WHERE option_name = \'template\'', 1],
] as $name => [$sql, $expected]) {
    $tests['attach schema shadowing current next26 ' . $name] = static function (TestRunner $t) use ($rows, $sql, $expected): void {
        $t->same($expected, count($rows($sql)));
    };
}

foreach ([
    'main to temp left join keeps explicit schemas' => ['SELECT m.option_value AS main_value, t.option_value AS temp_value FROM main.wp_options AS m LEFT JOIN temp.wp_options AS t ON t.option_name = m.option_name WHERE m.option_name = \'template\'', ['main_value' => 'twentytwentyfive', 'temp_value' => null]],
    'temp to site inner join keeps temp as left source' => ['SELECT t.option_value AS temp_value, s.option_value AS site_value FROM temp.wp_options AS t JOIN site.wp_options AS s ON s.option_name = t.option_name WHERE t.option_name = \'siteurl\'', ['temp_value' => 'https://temp.test', 'site_value' => 'https://site.test']],
    'site to archive join preserves attached schemas' => ['SELECT s.option_value AS site_value, a.option_value AS archive_value FROM site.wp_options AS s JOIN archive.wp_options AS a ON a.option_name = s.option_name WHERE s.option_name = \'siteurl\'', ['site_value' => 'https://site.test', 'archive_value' => 'https://archive.test']],
    'unqualified to main join shows temp shadow on left' => ['SELECT w.option_value AS shadow_value, m.option_value AS main_value FROM wp_options AS w JOIN main.wp_options AS m ON m.option_name = w.option_name WHERE w.option_name = \'siteurl\'', ['shadow_value' => 'https://temp.test', 'main_value' => 'https://main.test']],
] as $name => [$sql, $expected]) {
    $tests['attach schema shadowing current next26 ' . $name] = static function (TestRunner $t) use ($rows, $sql, $expected): void {
        $t->same($expected, $rows($sql)[0]);
    };
}

foreach ([
    'lowercase explicit schema key can resolve uppercase SQL schema' => ['SELECT option_value FROM MAIN.wp_options WHERE option_name = \'siteurl\'', 'option_value', 'https://main.test'],
    'uppercase temp schema key resolves case-insensitively' => ['SELECT option_value FROM TEMP.wp_options WHERE option_name = \'siteurl\'', 'option_value', 'https://temp.test'],
    'uppercase attached schema key resolves case-insensitively' => ['SELECT option_value FROM SITE.wp_options WHERE option_name = \'siteurl\'', 'option_value', 'https://site.test'],
    'uppercase table name resolves case-insensitively for qualified key' => ['SELECT option_value FROM site.WP_OPTIONS WHERE option_name = \'siteurl\'', 'option_value', 'https://site.test'],
] as $name => [$sql, $column, $expected]) {
    $tests['attach schema shadowing current next26 ' . $name] = static function (TestRunner $t) use ($value, $sql, $column, $expected): void {
        $t->same($expected, $value($sql, $column));
    };
}

$tests['attach schema shadowing current next26 bare legacy table arrays still resolve'] = static function (TestRunner $t) use ($value): void {
    $legacy = ['wp_options' => [['option_name' => 'siteurl', 'option_value' => 'legacy']]];

    $t->same('legacy', $value('SELECT option_value FROM wp_options WHERE option_name = \'siteurl\'', 'option_value', $legacy));
};

$tests['attach schema shadowing current next26 temp qualified rows outrank legacy bare key for unqualified lookup'] = static function (TestRunner $t) use ($value): void {
    $mixed = [
        'wp_options' => [['option_name' => 'siteurl', 'option_value' => 'legacy']],
        'temp.wp_options' => [['option_name' => 'siteurl', 'option_value' => 'temp']],
    ];

    $t->same('temp', $value('SELECT option_value FROM wp_options WHERE option_name = \'siteurl\'', 'option_value', $mixed));
};

$tests['attach schema shadowing current next26 main qualified rows outrank legacy bare key for explicit lookup'] = static function (TestRunner $t) use ($value): void {
    $mixed = [
        'wp_options' => [['option_name' => 'siteurl', 'option_value' => 'legacy']],
        'main.wp_options' => [['option_name' => 'siteurl', 'option_value' => 'main']],
    ];

    $t->same('main', $value('SELECT option_value FROM main.wp_options WHERE option_name = \'siteurl\'', 'option_value', $mixed));
};

$tests['attach schema shadowing current next26 attached fallback is used when temp and main lack table'] = static function (TestRunner $t) use ($value): void {
    $attachedOnly = ['site.wp_options' => [['option_name' => 'siteurl', 'option_value' => 'site']]];

    $t->same('site', $value('SELECT option_value FROM wp_options WHERE option_name = \'siteurl\'', 'option_value', $attachedOnly));
};

$tests['attach schema shadowing current next26 attached fallback follows provided attach order'] = static function (TestRunner $t) use ($value): void {
    $attachedOnly = [
        'archive.wp_options' => [['option_name' => 'siteurl', 'option_value' => 'archive']],
        'site.wp_options' => [['option_name' => 'siteurl', 'option_value' => 'site']],
    ];

    $t->same('archive', $value('SELECT option_value FROM wp_options WHERE option_name = \'siteurl\'', 'option_value', $attachedOnly));
};

foreach ([
    'unqualified ordering stays on temp shadow rows' => ['SELECT option_name FROM wp_options ORDER BY option_name LIMIT 1', 'option_name', 'scratch'],
    'main ordering stays on main rows' => ['SELECT option_name FROM main.wp_options ORDER BY option_name LIMIT 1', 'option_name', 'siteurl'],
    'site ordering stays on attached rows' => ['SELECT option_name FROM site.wp_options ORDER BY option_name LIMIT 1', 'option_name', 'siteurl'],
    'archive ordering stays on archive rows' => ['SELECT option_value FROM archive.wp_options ORDER BY option_name LIMIT 1', 'option_value', 'https://archive.test'],
    'temp offset stays on temp rows' => ['SELECT option_name FROM temp.wp_options ORDER BY option_id LIMIT 1 OFFSET 1', 'option_name', 'scratch'],
    'main offset stays on main rows' => ['SELECT option_name FROM main.wp_options ORDER BY option_id LIMIT 1 OFFSET 1', 'option_name', 'template'],
    'site offset stays on attached rows' => ['SELECT option_name FROM site.wp_options ORDER BY option_id LIMIT 1 OFFSET 1', 'option_name', 'stylesheet'],
    'archive offset stays on attached rows' => ['SELECT option_name FROM archive.wp_options ORDER BY option_id LIMIT 1 OFFSET 1', 'option_name', 'template'],
] as $name => [$sql, $column, $expected]) {
    $tests['attach schema shadowing current next26 ' . $name] = static function (TestRunner $t) use ($value, $sql, $column, $expected): void {
        $t->same($expected, $value($sql, $column));
    };
}

$tests['attach schema shadowing current next26 missing explicit schema table raises'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $rows('SELECT option_value FROM missing.wp_options'));
};

$tests['attach schema shadowing current next26 unavailable unqualified table raises'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $rows('SELECT option_value FROM wp_posts'));
};

$tests['attach schema shadowing current next26 malformed triple-qualified table raises'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $rows('SELECT option_value FROM site.extra.wp_options'));
};

return $tests;

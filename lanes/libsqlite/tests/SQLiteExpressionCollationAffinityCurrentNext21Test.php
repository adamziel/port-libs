<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '10', 'autoload' => 'yes', 'label' => 'core   '],
    ['option_id' => 2, 'option_name' => 'SiteURL', 'option_value' => '010', 'autoload' => 'no', 'label' => 'Core'],
    ['option_id' => 3, 'option_name' => 'HOME   ', 'option_value' => '10.0', 'autoload' => 'yes', 'label' => 'home'],
    ['option_id' => 4, 'option_name' => 'home', 'option_value' => '2', 'autoload' => 'no', 'label' => 'Home   '],
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'option_value' => 'not-a-number', 'autoload' => null, 'label' => 'plugin'],
    ['option_id' => 6, 'option_name' => 'PLUGIN_ALPHA', 'option_value' => '10e0', 'autoload' => null, 'label' => 'Plugin   '],
];

$tables = ['wp_options' => $options];
$column = static fn (string $sql, string $name): array => array_column(SQLiteSelectSql::execute($sql, $tables), $name);
$first = static fn (string $sql, string $name): mixed => SQLiteSelectSql::execute($sql, $tables)[0][$name];

$cases = [
    'simple case base nocase matches upper literal' => [
        "SELECT CASE option_name COLLATE NOCASE WHEN 'SITEURL' THEN 'match' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 1",
        'bucket',
        'match',
    ],
    'simple case when nocase matches mixed case base' => [
        "SELECT CASE option_name WHEN 'SITEURL' COLLATE NOCASE THEN 'match' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 1",
        'bucket',
        'match',
    ],
    'simple case binary remains case sensitive' => [
        "SELECT CASE option_name COLLATE BINARY WHEN 'SITEURL' THEN 'match' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 1",
        'bucket',
        'miss',
    ],
    'simple case rtrim base ignores trailing spaces' => [
        "SELECT CASE option_name COLLATE RTRIM WHEN 'HOME' THEN 'match' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 3",
        'bucket',
        'match',
    ],
    'simple case rtrim when ignores trailing spaces' => [
        "SELECT CASE option_name WHEN 'HOME' COLLATE RTRIM THEN 'match' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 3",
        'bucket',
        'match',
    ],
    'simple case rtrim does not fold case' => [
        "SELECT CASE option_name COLLATE RTRIM WHEN 'home' THEN 'match' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 3",
        'bucket',
        'miss',
    ],
    'simple case nocase does not trim trailing spaces' => [
        "SELECT CASE option_name COLLATE NOCASE WHEN 'home' THEN 'match' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 3",
        'bucket',
        'miss',
    ],
    'simple case cast numeric compares integer text' => [
        "SELECT CASE CAST(option_value AS NUMERIC) WHEN 10 THEN 'ten' ELSE 'other' END AS bucket FROM wp_options WHERE option_id = 1",
        'bucket',
        'ten',
    ],
    'simple case cast numeric compares leading zero text' => [
        "SELECT CASE CAST(option_value AS NUMERIC) WHEN 10 THEN 'ten' ELSE 'other' END AS bucket FROM wp_options WHERE option_id = 2",
        'bucket',
        'ten',
    ],
    'simple case cast numeric compares real text' => [
        "SELECT CASE CAST(option_value AS NUMERIC) WHEN 10 THEN 'ten' ELSE 'other' END AS bucket FROM wp_options WHERE option_id = 3",
        'bucket',
        'ten',
    ],
    'simple case cast numeric compares exponent text' => [
        "SELECT CASE CAST(option_value AS NUMERIC) WHEN 10 THEN 'ten' ELSE 'other' END AS bucket FROM wp_options WHERE option_id = 6",
        'bucket',
        'ten',
    ],
    'simple case text numeric does not coerce without cast' => [
        "SELECT CASE option_value WHEN 10 THEN 'ten' ELSE 'other' END AS bucket FROM wp_options WHERE option_id = 1",
        'bucket',
        'other',
    ],
    'simple case cast text compares integer branch as text' => [
        "SELECT CASE CAST(10 AS TEXT) WHEN '10' THEN 'text-ten' ELSE 'other' END AS bucket FROM wp_options LIMIT 1",
        'bucket',
        'text-ten',
    ],
    'simple case cast blob does not equal text branch' => [
        "SELECT CASE CAST('siteurl' AS BLOB) WHEN 'siteurl' THEN 'text' ELSE 'blob' END AS bucket FROM wp_options LIMIT 1",
        'bucket',
        'blob',
    ],
    'simple case blob matches blob branch' => [
        "SELECT CASE CAST('siteurl' AS BLOB) WHEN X'7369746575726c' THEN 'blob' ELSE 'other' END AS bucket FROM wp_options LIMIT 1",
        'bucket',
        'blob',
    ],
    'simple case nocase labels copied option names' => [
        "SELECT option_id, CASE option_name COLLATE NOCASE WHEN 'siteurl' THEN 'url' WHEN 'plugin_alpha' THEN 'plugin' ELSE 'other' END AS bucket FROM wp_options ORDER BY option_id",
        'bucket',
        ['url', 'url', 'other', 'other', 'plugin', 'plugin'],
    ],
    'simple case rtrim labels copied option names' => [
        "SELECT option_id, CASE option_name COLLATE RTRIM WHEN 'HOME' THEN 'home-upper' WHEN 'home' THEN 'home-lower' ELSE 'other' END AS bucket FROM wp_options ORDER BY option_id",
        'bucket',
        ['other', 'other', 'home-upper', 'home-lower', 'other', 'other'],
    ],
    'simple case collate feeds where predicate' => [
        "SELECT option_id FROM wp_options WHERE CASE option_name COLLATE NOCASE WHEN 'SITEURL' THEN 1 ELSE 0 END = 1 ORDER BY option_id",
        'option_id',
        [1, 2],
    ],
    'simple case collate feeds projection before final order' => [
        "SELECT CASE option_name COLLATE NOCASE WHEN 'PLUGIN_ALPHA' THEN 'plugin' ELSE 'core' END AS bucket FROM wp_options ORDER BY option_id",
        'bucket',
        ['core', 'core', 'core', 'core', 'plugin', 'plugin'],
    ],
    'simple case rtrim base works through function result' => [
        "SELECT CASE upper(label) COLLATE RTRIM WHEN 'CORE' THEN 'core' WHEN 'PLUGIN' THEN 'plugin' ELSE 'other' END AS bucket FROM wp_options ORDER BY option_id",
        'bucket',
        ['core', 'core', 'other', 'other', 'plugin', 'plugin'],
    ],
    'simple case nocase base works through concatenation' => [
        "SELECT CASE option_name || '' COLLATE NOCASE WHEN 'siteurl' THEN 'url' ELSE 'other' END AS bucket FROM wp_options ORDER BY option_id LIMIT 2",
        'bucket',
        ['url', 'url'],
    ],
    'simple case numeric compare keeps large integer below overflow real' => [
        "SELECT CASE 9223372036854775807 WHEN 9223372036854775808.0 THEN 'overflow' ELSE 'safe' END AS bucket FROM wp_options LIMIT 1",
        'bucket',
        'safe',
    ],
    'simple case null base never matches null when' => [
        "SELECT CASE NULL WHEN NULL THEN 'null' ELSE 'miss' END AS bucket FROM wp_options LIMIT 1",
        'bucket',
        'miss',
    ],
    'simple case null when remains non match' => [
        "SELECT CASE option_name COLLATE NOCASE WHEN NULL THEN 'null' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 1",
        'bucket',
        'miss',
    ],
    'simple case collate survives nested case base' => [
        "SELECT CASE (CASE autoload WHEN 'yes' THEN option_name ELSE upper(option_name) END) COLLATE NOCASE WHEN 'SITEURL' THEN 'url' ELSE 'other' END AS bucket FROM wp_options ORDER BY option_id LIMIT 2",
        'bucket',
        ['url', 'url'],
    ],
    'simple case collate survives nested when expression' => [
        "SELECT CASE option_name WHEN (CASE autoload WHEN 'yes' THEN 'SITEURL' ELSE 'siteurl' END) COLLATE NOCASE THEN 'url' ELSE 'other' END AS bucket FROM wp_options ORDER BY option_id LIMIT 2",
        'bucket',
        ['url', 'url'],
    ],
    'simple case later collated branch is reached after binary miss' => [
        "SELECT CASE option_name WHEN 'SITEURL' THEN 'binary' WHEN 'SITEURL' COLLATE NOCASE THEN 'nocase' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 1",
        'bucket',
        'nocase',
    ],
    'simple case first matching collated branch wins' => [
        "SELECT CASE option_name COLLATE NOCASE WHEN 'SITEURL' THEN 'first' WHEN 'siteurl' THEN 'second' ELSE 'miss' END AS bucket FROM wp_options WHERE option_id = 1",
        'bucket',
        'first',
    ],
    'simple case rejects unsupported base collation' => [
        "SELECT CASE option_name COLLATE WPCASE WHEN 'siteurl' THEN 1 ELSE 0 END AS bucket FROM wp_options",
        'error',
        InvalidArgumentException::class,
    ],
    'simple case rejects unsupported when collation' => [
        "SELECT CASE option_name WHEN 'siteurl' COLLATE WPCASE THEN 1 ELSE 0 END AS bucket FROM wp_options",
        'error',
        InvalidArgumentException::class,
    ],
];

foreach ($cases as $name => [$sql, $field, $expected]) {
    $tests['expression collation affinity current next21 ' . $name] = static function (TestRunner $t) use ($sql, $field, $expected, $column, $first, $tables): void {
        if ($field === 'error') {
            $t->throws($expected, static fn () => SQLiteSelectSql::execute($sql, $tables));
            return;
        }

        if (is_array($expected)) {
            $t->same($expected, $column($sql, $field));
            return;
        }

        $t->same($expected, $first($sql, $field));
    };
}

return $tests;

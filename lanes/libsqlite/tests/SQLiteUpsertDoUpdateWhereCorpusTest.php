<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$baseRows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'hits' => 5, 'touched' => 'old'],
    ['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 2, 'touched' => 'old'],
    ['option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'hits' => 7, 'touched' => 'old'],
    ['option_name' => null, 'option_value' => 'anonymous', 'autoload' => 'no', 'hits' => 1, 'touched' => 'old'],
];

$quote = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . SQLite3::escapeString((string) $value) . "'";
};

$rowsForOracle = static function (array $rows, string $valuesSql, string $whereSql) use ($quote): array {
    $db = new SQLite3(':memory:');
    $db->exec('CREATE TABLE wp_options(option_name TEXT UNIQUE, option_value TEXT, autoload TEXT, hits INTEGER, touched TEXT)');
    foreach ($rows as $row) {
        $db->exec(sprintf(
            'INSERT INTO wp_options(option_name, option_value, autoload, hits, touched) VALUES(%s, %s, %s, %s, %s)',
            $quote($row['option_name']),
            $quote($row['option_value']),
            $quote($row['autoload']),
            $quote($row['hits']),
            $quote($row['touched']),
        ));
    }
    $db->exec(
        'INSERT INTO wp_options(option_name, option_value, autoload, hits, touched) VALUES ' . $valuesSql
        . ' ON CONFLICT(option_name) DO UPDATE SET '
        . 'option_value = excluded.option_value, autoload = excluded.autoload, hits = wp_options.hits + excluded.hits, touched = excluded.touched '
        . 'WHERE ' . $whereSql
    );
    $changes = (int) $db->querySingle('SELECT changes()');
    $result = $db->query('SELECT option_name, option_value, autoload, hits, touched FROM wp_options ORDER BY option_name IS NOT NULL, option_name');
    $after = [];
    while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
        $row['hits'] = (int) $row['hits'];
        $after[] = $row;
    }

    return ['after' => $after, 'changes' => $changes];
};

$incoming = static function (array $rows): string {
    $tuples = [];
    foreach ($rows as $row) {
        $values = [];
        foreach (['option_name', 'option_value', 'autoload', 'hits', 'touched'] as $column) {
            $value = $row[$column];
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_int($value) || is_float($value)) {
                $values[] = (string) $value;
            } else {
                $values[] = "'" . SQLite3::escapeString((string) $value) . "'";
            }
        }
        $tuples[] = '(' . implode(', ', $values) . ')';
    }

    return implode(', ', $tuples);
};

$runNative = static function (array $rows, array $incomingRows, callable $where): array {
    return SQLiteUpsertDoUpdateWherePlan::execute(
        $rows,
        $incomingRows,
        ['option_name'],
        [
            'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
            'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
            'hits' => static fn (array $current, array $excluded): int => (int) $current['hits'] + (int) $excluded['hits'],
            'touched' => static fn (array $current, array $excluded): mixed => $excluded['touched'],
        ],
        $where,
    );
};

$sortRows = static function (array $rows): array {
    usort($rows, static function (array $left, array $right): int {
        if ($left['option_name'] === null && $right['option_name'] !== null) {
            return -1;
        }
        if ($left['option_name'] !== null && $right['option_name'] === null) {
            return 1;
        }

        return strcmp((string) $left['option_name'], (string) $right['option_name']);
    });

    return array_values($rows);
};

$cases = [
    'where true updates conflict' => [
        [['option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'no', 'hits' => 3, 'touched' => 'u1']],
        '1',
        static fn (array $current, array $excluded): bool => true,
    ],
    'where false skips conflict' => [
        [['option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'no', 'hits' => 3, 'touched' => 'u2']],
        '0',
        static fn (array $current, array $excluded): bool => false,
    ],
    'where current autoload matches' => [
        [['option_name' => 'home', 'option_value' => 'home-new', 'autoload' => 'no', 'hits' => 4, 'touched' => 'u3']],
        "wp_options.autoload = 'yes'",
        static fn (array $current, array $excluded): bool => $current['autoload'] === 'yes',
    ],
    'where current autoload rejects' => [
        [['option_name' => 'blogname', 'option_value' => 'New Blog', 'autoload' => 'yes', 'hits' => 4, 'touched' => 'u4']],
        "wp_options.autoload = 'yes'",
        static fn (array $current, array $excluded): bool => $current['autoload'] === 'yes',
    ],
    'where excluded autoload matches' => [
        [['option_name' => 'blogname', 'option_value' => 'New Blog', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u5']],
        "excluded.autoload = 'yes'",
        static fn (array $current, array $excluded): bool => $excluded['autoload'] === 'yes',
    ],
    'where excluded autoload rejects' => [
        [['option_name' => 'siteurl', 'option_value' => 'new', 'autoload' => 'no', 'hits' => 1, 'touched' => 'u6']],
        "excluded.autoload = 'yes'",
        static fn (array $current, array $excluded): bool => $excluded['autoload'] === 'yes',
    ],
    'where current hits greater' => [
        [['option_name' => 'blogname', 'option_value' => 'New Blog', 'autoload' => 'yes', 'hits' => 8, 'touched' => 'u7']],
        'wp_options.hits > 5',
        static fn (array $current, array $excluded): bool => $current['hits'] > 5,
    ],
    'where current hits lower rejects' => [
        [['option_name' => 'home', 'option_value' => 'home-new', 'autoload' => 'yes', 'hits' => 8, 'touched' => 'u8']],
        'wp_options.hits > 5',
        static fn (array $current, array $excluded): bool => $current['hits'] > 5,
    ],
    'where excluded hits greater' => [
        [['option_name' => 'home', 'option_value' => 'home-new', 'autoload' => 'yes', 'hits' => 9, 'touched' => 'u9']],
        'excluded.hits > wp_options.hits',
        static fn (array $current, array $excluded): bool => $excluded['hits'] > $current['hits'],
    ],
    'where excluded hits lower rejects' => [
        [['option_name' => 'blogname', 'option_value' => 'New Blog', 'autoload' => 'yes', 'hits' => 2, 'touched' => 'u10']],
        'excluded.hits > wp_options.hits',
        static fn (array $current, array $excluded): bool => $excluded['hits'] > $current['hits'],
    ],
    'where like current value' => [
        [['option_name' => 'siteurl', 'option_value' => 'https://fresh.test', 'autoload' => 'yes', 'hits' => 2, 'touched' => 'u11']],
        "wp_options.option_value LIKE 'https://%'",
        static fn (array $current, array $excluded): bool => str_starts_with((string) $current['option_value'], 'https://'),
    ],
    'where like rejects text' => [
        [['option_name' => 'blogname', 'option_value' => 'New Blog', 'autoload' => 'yes', 'hits' => 2, 'touched' => 'u12']],
        "wp_options.option_value LIKE 'https://%'",
        static fn (array $current, array $excluded): bool => str_starts_with((string) $current['option_value'], 'https://'),
    ],
    'where excluded value differs' => [
        [['option_name' => 'home', 'option_value' => 'https://alt.test', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u13']],
        'excluded.option_value <> wp_options.option_value',
        static fn (array $current, array $excluded): bool => $excluded['option_value'] !== $current['option_value'],
    ],
    'where excluded value same rejects' => [
        [['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'no', 'hits' => 1, 'touched' => 'u14']],
        'excluded.option_value <> wp_options.option_value',
        static fn (array $current, array $excluded): bool => $excluded['option_value'] !== $current['option_value'],
    ],
    'where conflict plus insert' => [
        [
            ['option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'no', 'hits' => 3, 'touched' => 'u15'],
            ['option_name' => 'new_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'hits' => 1, 'touched' => 'i15'],
        ],
        'wp_options.hits >= 5',
        static fn (array $current, array $excluded): bool => $current['hits'] >= 5,
    ],
    'where skip conflict plus insert' => [
        [
            ['option_name' => 'home', 'option_value' => 'home-new', 'autoload' => 'no', 'hits' => 3, 'touched' => 'u16'],
            ['option_name' => 'new_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'hits' => 1, 'touched' => 'i16'],
        ],
        'wp_options.hits >= 5',
        static fn (array $current, array $excluded): bool => $current['hits'] >= 5,
    ],
    'where null unique inserts' => [
        [['option_name' => null, 'option_value' => 'second-null', 'autoload' => 'yes', 'hits' => 6, 'touched' => 'i17']],
        '1',
        static fn (array $current, array $excluded): bool => true,
    ],
    'where repeated incoming uses updated current' => [
        [
            ['option_name' => 'home', 'option_value' => 'home-1', 'autoload' => 'yes', 'hits' => 4, 'touched' => 'u18a'],
            ['option_name' => 'home', 'option_value' => 'home-2', 'autoload' => 'yes', 'hits' => 5, 'touched' => 'u18b'],
        ],
        'wp_options.hits < 10',
        static fn (array $current, array $excluded): bool => $current['hits'] < 10,
    ],
    'where repeated incoming second skip sees updated current' => [
        [
            ['option_name' => 'home', 'option_value' => 'home-1', 'autoload' => 'yes', 'hits' => 8, 'touched' => 'u19a'],
            ['option_name' => 'home', 'option_value' => 'home-2', 'autoload' => 'yes', 'hits' => 5, 'touched' => 'u19b'],
        ],
        'wp_options.hits < 10',
        static fn (array $current, array $excluded): bool => $current['hits'] < 10,
    ],
    'where inserted then conflicts in same statement' => [
        [
            ['option_name' => 'transient_x', 'option_value' => 'one', 'autoload' => 'no', 'hits' => 1, 'touched' => 'i20a'],
            ['option_name' => 'transient_x', 'option_value' => 'two', 'autoload' => 'yes', 'hits' => 2, 'touched' => 'u20b'],
        ],
        "excluded.autoload = 'yes'",
        static fn (array $current, array $excluded): bool => $excluded['autoload'] === 'yes',
    ],
    'where inserted then skip in same statement' => [
        [
            ['option_name' => 'transient_y', 'option_value' => 'one', 'autoload' => 'no', 'hits' => 1, 'touched' => 'i21a'],
            ['option_name' => 'transient_y', 'option_value' => 'two', 'autoload' => 'no', 'hits' => 2, 'touched' => 'u21b'],
        ],
        "excluded.autoload = 'yes'",
        static fn (array $current, array $excluded): bool => $excluded['autoload'] === 'yes',
    ],
    'where is not null updates' => [
        [['option_name' => 'siteurl', 'option_value' => 'not-null', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u22']],
        'excluded.option_value IS NOT NULL',
        static fn (array $current, array $excluded): bool => $excluded['option_value'] !== null,
    ],
    'where is not null rejects' => [
        [['option_name' => 'siteurl', 'option_value' => null, 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u23']],
        'excluded.option_value IS NOT NULL',
        static fn (array $current, array $excluded): bool => $excluded['option_value'] !== null,
    ],
    'where coalesce current update' => [
        [['option_name' => 'blogname', 'option_value' => 'Coalesced', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u24']],
        "coalesce(wp_options.autoload, '') = 'no'",
        static fn (array $current, array $excluded): bool => ($current['autoload'] ?? '') === 'no',
    ],
    'where coalesce current skip' => [
        [['option_name' => 'siteurl', 'option_value' => 'Coalesced', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u25']],
        "coalesce(wp_options.autoload, '') = 'no'",
        static fn (array $current, array $excluded): bool => ($current['autoload'] ?? '') === 'no',
    ],
    'where arithmetic update' => [
        [['option_name' => 'home', 'option_value' => 'home-new', 'autoload' => 'yes', 'hits' => 3, 'touched' => 'u26']],
        'wp_options.hits + excluded.hits >= 5',
        static fn (array $current, array $excluded): bool => $current['hits'] + $excluded['hits'] >= 5,
    ],
    'where arithmetic skip' => [
        [['option_name' => 'home', 'option_value' => 'home-new', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u27']],
        'wp_options.hits + excluded.hits >= 5',
        static fn (array $current, array $excluded): bool => $current['hits'] + $excluded['hits'] >= 5,
    ],
    'where multiple conflicts mixed' => [
        [
            ['option_name' => 'siteurl', 'option_value' => 'site-new', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u28'],
            ['option_name' => 'home', 'option_value' => 'home-new', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u28'],
            ['option_name' => 'blogname', 'option_value' => 'blog-new', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u28'],
        ],
        'wp_options.hits >= 5',
        static fn (array $current, array $excluded): bool => $current['hits'] >= 5,
    ],
    'where multiple inserts and conflicts mixed' => [
        [
            ['option_name' => 'alpha_plugin', 'option_value' => 'a', 'autoload' => 'no', 'hits' => 1, 'touched' => 'i29'],
            ['option_name' => 'siteurl', 'option_value' => 'site-new', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u29'],
            ['option_name' => 'beta_plugin', 'option_value' => 'b', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'i29'],
        ],
        'wp_options.hits >= 5',
        static fn (array $current, array $excluded): bool => $current['hits'] >= 5,
    ],
    'where same values still counts update' => [
        [['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 0, 'touched' => 'old']],
        '1',
        static fn (array $current, array $excluded): bool => true,
    ],
    'where null expression skips like sqlite' => [
        [['option_name' => 'siteurl', 'option_value' => 'null-skip', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'u31']],
        'NULL',
        static fn (array $current, array $excluded): bool => false,
    ],
];

$tests = [];

foreach ($cases as $name => [$incomingRows, $whereSql, $where]) {
    $tests['upsert do update where corpus rows ' . $name] = static function (TestRunner $t) use ($baseRows, $rowsForOracle, $incoming, $runNative, $sortRows, $incomingRows, $whereSql, $where): void {
        $expected = $rowsForOracle($baseRows, $incoming($incomingRows), $whereSql);
        $actual = $runNative($baseRows, $incomingRows, $where);
        $t->same($expected['after'], $sortRows($actual['after']));
    };

    $tests['upsert do update where corpus changes ' . $name] = static function (TestRunner $t) use ($baseRows, $rowsForOracle, $incoming, $runNative, $incomingRows, $whereSql, $where): void {
        $expected = $rowsForOracle($baseRows, $incoming($incomingRows), $whereSql);
        $actual = $runNative($baseRows, $incomingRows, $where);
        $t->same($expected['changes'], $actual['changes']);
    };
}

$tests['upsert do update where corpus records skipped row'] = static function (TestRunner $t) use ($baseRows, $runNative): void {
    $result = $runNative(
        $baseRows,
        [['option_name' => 'siteurl', 'option_value' => 'skip', 'autoload' => 'yes', 'hits' => 1, 'touched' => 'skip']],
        static fn (array $current, array $excluded): bool => false,
    );
    $t->same(['siteurl'], array_column($result['skipped_rows'], 'option_name'));
};

$tests['upsert do update where corpus rejects update unique conflict'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::execute(
        [
            ['option_name' => 'siteurl', 'option_value' => 'old'],
            ['option_name' => 'home', 'option_value' => 'old'],
        ],
        [['option_name' => 'siteurl', 'option_value' => 'new']],
        ['option_name'],
        ['option_name' => static fn (array $current, array $excluded): string => 'home'],
        static fn (array $current, array $excluded): bool => true,
    ));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions166 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 18],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 16],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 12],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 40],
];
$nextOptions166 = [
    ...$currentOptions166,
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 24],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 13],
];
$currentTables166 = ['wp_options' => $currentOptions166];
$nextTables166 = ['wp_options' => $nextOptions166];

$sql166 = <<<'SQL'
WITH RECURSIVE staged(id, label, weight) AS (
    VALUES (1, 'seed-empty', 30)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 1
      FROM staged
     WHERE id < 5
     ORDER BY weight DESC
     LIMIT 0
)
SELECT id,
       label,
       row_number() OVER (ORDER BY weight DESC, id) AS rank
  FROM staged
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY weight DESC, option_id) AS rank
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rank, id
 LIMIT 4 OFFSET 1
SQL;

$summary166 = static fn (): array => SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan::compareNext166($sql166, $currentTables166, $nextTables166);
$tests = [];

$tests['compound exhausted recursive window limit next166 status dependencies'] = static function (TestRunner $t) use ($summary166): void {
    $plan = $summary166();
    $t->same('compound-exhausted-recursive-window-limit-current-source-next166-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-limit-zero-exhaustion',
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-compound-final-limit',
        'sqlite-current-source-next166',
    ], $plan['dependencies']);
};

$tests['compound exhausted recursive window limit next166 compound metadata'] = static function (TestRunner $t) use ($summary166): void {
    $compound = $summary166()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['rank', 'id'], $compound['orderColumns']);
    $t->same(4, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound exhausted recursive window limit next166 recursive queue stays empty'] = static function (TestRunner $t) use ($summary166): void {
    $recursive = $summary166()['recursive'];
    $t->same('staged', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same([], $recursive['currentRows']);
    $t->same([], $recursive['nextRows']);
    $t->same(1, $recursive['currentTraceCount']);
    $t->same(false, $recursive['currentFirstTrace']['emitted']);
    $t->same(0, $recursive['currentLimitRemaining']);
};

$tests['compound exhausted recursive window limit next166 current rows'] = static function (TestRunner $t) use ($summary166): void {
    $rows = $summary166()['currentRows'];
    $t->same([2, 3], array_column($rows, 'id'));
    $t->same(['home', 'blogname'], array_column($rows, 'label'));
    $t->same([2, 3], array_column($rows, 'rank'));
};

$tests['compound exhausted recursive window limit next166 next rows'] = static function (TestRunner $t) use ($summary166): void {
    $rows = $summary166()['nextRows'];
    $t->same([1, 2, 6, 3], array_column($rows, 'id'));
    $t->same(['siteurl', 'home', 'theme_mods', 'blogname'], array_column($rows, 'label'));
    $t->same([2, 3, 4, 5], array_column($rows, 'rank'));
};

$tests['compound exhausted recursive window limit next166 limit trace'] = static function (TestRunner $t) use ($summary166): void {
    $trace = $summary166()['limitTrace'];
    $t->same(3, $trace['current']['preLimitCount']);
    $t->same(5, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['rewrite_rules'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same([], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound exhausted recursive window limit next166 window metadata'] = static function (TestRunner $t) use ($summary166): void {
    $windows = $summary166()['windows']['current'];
    $t->same(['row_number', 'row_number'], array_column($windows, 'function'));
    $t->same(['rank', 'rank'], array_column($windows, 'alias'));
    $t->same([0, 0], array_column($windows, 'partitionCount'));
    $t->same([2, 2], array_column($windows, 'orderCount'));
};

$tests['compound exhausted recursive window limit next166 changed reasons'] = static function (TestRunner $t) use ($summary166): void {
    $plan = $summary166();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"theme_mods"', $changed);
    $t->contains('"label":"blogname"', $changed);
    $t->true(in_array('recursive-limit-zero-exhausted-before-window-arm', $plan['replanReasons'], true));
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-arm-empty-in-current-and-next', $plan['replanReasons'], true));
};

$tests['compound exhausted recursive window limit next166 rejects missing limit zero'] = static function (TestRunner $t) use ($currentTables166): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan::compareNext166(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 30) UNION ALL SELECT id + 1, label, weight - 1 FROM staged WHERE id < 5 LIMIT 2) SELECT id, label, row_number() OVER (ORDER BY weight) AS rank FROM staged UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight) FROM wp_options LIMIT 2",
        $currentTables166,
        $currentTables166,
    ));
};

$tests['compound exhausted recursive window limit next166 rejects missing window'] = static function (TestRunner $t) use ($currentTables166): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan::compareNext166(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 30) UNION ALL SELECT id + 1, label, weight - 1 FROM staged WHERE id < 5 LIMIT 0) SELECT id, label, weight FROM staged UNION ALL SELECT option_id, option_name, weight FROM wp_options ORDER BY weight LIMIT 2",
        $currentTables166,
        $currentTables166,
    ));
};

$tests['compound exhausted recursive window limit next166 rejects missing final limit'] = static function (TestRunner $t) use ($currentTables166): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundExhaustedRecursiveWindowLimitCurrentSourceNextPlan::compareNext166(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 30) UNION ALL SELECT id + 1, label, weight - 1 FROM staged WHERE id < 5 LIMIT 0) SELECT id, label, row_number() OVER (ORDER BY weight) AS rank FROM staged UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight) FROM wp_options ORDER BY rank",
        $currentTables166,
        $currentTables166,
    ));
};

foreach (range(1, 46) as $case) {
    $tests['compound exhausted recursive window limit next166 generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $limit = 2 + ($case % 3);
        $offset = $case % 2;
        $tables = [
            'wp_options' => [
                ['option_id' => 10, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 50 + $case],
                ['option_id' => 11, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 40 + $case],
                ['option_id' => 12, 'option_name' => 'skip_' . $case, 'autoload' => 'no', 'weight' => 99 + $case],
                ['option_id' => 13, 'option_name' => 'theme_' . $case, 'autoload' => 'yes', 'weight' => 30 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed_empty_{$case}', " . (60 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 1 FROM staged WHERE id < 5 ORDER BY weight DESC LIMIT 0) SELECT id, label, row_number() OVER (ORDER BY weight DESC, id) AS rank FROM staged UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY weight DESC, option_id) AS rank FROM wp_options WHERE autoload = 'yes' ORDER BY rank, id LIMIT {$limit} OFFSET {$offset}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(min($limit, 3 - $offset), count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['rank']));
        $t->same(false, str_starts_with((string) $rows[0]['label'], 'seed_empty_'));
    };
}

return $tests;

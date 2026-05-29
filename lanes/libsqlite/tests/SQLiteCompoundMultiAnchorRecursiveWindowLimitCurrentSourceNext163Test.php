<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions163 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 18],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 16],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 12],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 10],
];
$nextOptions163 = [
    ...$currentOptions163,
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 24],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 13],
];
$currentTables163 = ['wp_options' => $currentOptions163];
$nextTables163 = ['wp_options' => $nextOptions163];

$sql163 = <<<'SQL'
WITH RECURSIVE staged(id, label, weight) AS (
    VALUES (1, 'seed-a', 23)
    UNION
    VALUES (1, 'seed-a', 23), (2, 'seed-b', 19), (9, 'skip-me', 1)
    EXCEPT
    VALUES (9, 'skip-me', 1)
    UNION
    SELECT id + 2, label || ':' || (id + 2), weight - 5
      FROM staged
     WHERE id < 7
     ORDER BY weight DESC
     LIMIT 5 OFFSET 1
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
 LIMIT 6 OFFSET 1
SQL;

$summary163 = static fn (): array => SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceNextPlan::compareNext163($sql163, $currentTables163, $nextTables163);
$tests = [];

$tests['compound multi anchor recursive window limit next163 status dependencies'] = static function (TestRunner $t) use ($summary163): void {
    $plan = $summary163();
    $t->same('compound-multi-anchor-recursive-window-limit-current-source-next163-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-compound-anchor-arms',
        'sqlite-recursive-cte-order-limit-queue',
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-compound-final-limit',
        'sqlite-current-source-next163',
    ], $plan['dependencies']);
};

$tests['compound multi anchor recursive window limit next163 compound metadata'] = static function (TestRunner $t) use ($summary163): void {
    $compound = $summary163()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['rank', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound multi anchor recursive window limit next163 current rows'] = static function (TestRunner $t) use ($summary163): void {
    $rows = $summary163()['currentRows'];
    $t->same([2, 2, 3, 3, 4, 5], array_column($rows, 'id'));
    $t->same(['seed-b', 'home', 'seed-a:3', 'blogname', 'seed-b:4', 'seed-a:3:5'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3, 3, 4], array_column($rows, 'rank'));
};

$tests['compound multi anchor recursive window limit next163 next rows'] = static function (TestRunner $t) use ($summary163): void {
    $rows = $summary163()['nextRows'];
    $t->same([5, 1, 3, 2, 4, 5], array_column($rows, 'id'));
    $t->same(['rewrite_rules', 'siteurl', 'seed-a:3', 'home', 'seed-b:4', 'seed-a:3:5'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3, 3, 4], array_column($rows, 'rank'));
};

$tests['compound multi anchor recursive window limit next163 recursive trace'] = static function (TestRunner $t) use ($summary163): void {
    $recursive = $summary163()['recursive'];
    $t->same('staged', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION', $recursive['operator']);
    $t->same(['seed-b', 'seed-a:3', 'seed-b:4', 'seed-a:3:5', 'seed-b:4:6'], array_column($recursive['currentRows'], 'label'));
    $t->same(['seed-a:3', 'seed-b:4', 'seed-a:3:5', 'seed-b:4:6', 'seed-a:3:5:7'], $recursive['currentGeneratedLabels']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['currentOffsetRemaining']);
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound multi anchor recursive window limit next163 window metadata'] = static function (TestRunner $t) use ($summary163): void {
    $windows = $summary163()['windows']['current'];
    $t->same(['row_number', 'row_number'], array_column($windows, 'function'));
    $t->same(['rank', 'rank'], array_column($windows, 'alias'));
    $t->same([0, 0], array_column($windows, 'partitionCount'));
    $t->same([2, 2], array_column($windows, 'orderCount'));
};

$tests['compound multi anchor recursive window limit next163 limit trace'] = static function (TestRunner $t) use ($summary163): void {
    $trace = $summary163()['limitTrace'];
    $t->same(8, $trace['current']['preLimitCount']);
    $t->same(10, $trace['next']['preLimitCount']);
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed-b'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed-b:4:6'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['theme_mods', 'blogname', 'seed-b:4:6'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound multi anchor recursive window limit next163 changed reasons'] = static function (TestRunner $t) use ($summary163): void {
    $plan = $summary163();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"rewrite_rules"', $changed);
    $t->contains('"label":"blogname"', $changed);
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('compound-anchor-before-recursive-arm', $plan['replanReasons'], true));
    $t->true(in_array('window-before-compound-final-limit', $plan['replanReasons'], true));
};

$tests['compound multi anchor recursive window limit next163 rejects single anchor'] = static function (TestRunner $t) use ($currentTables163): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceNextPlan::compareNext163(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 23) UNION SELECT id + 1, label, weight - 1 FROM staged WHERE id < 3 LIMIT 2 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY weight) AS rank FROM staged UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight) FROM wp_options LIMIT 2",
        $currentTables163,
        $currentTables163,
    ));
};

$tests['compound multi anchor recursive window limit next163 rejects missing window'] = static function (TestRunner $t) use ($currentTables163): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceNextPlan::compareNext163(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed-a', 23) UNION VALUES (2, 'seed-b', 19) EXCEPT VALUES (9, 'skip', 1) UNION SELECT id + 2, label, weight - 5 FROM staged WHERE id < 7 LIMIT 5 OFFSET 1) SELECT id, label, weight FROM staged UNION ALL SELECT option_id, option_name, weight FROM wp_options ORDER BY weight LIMIT 2",
        $currentTables163,
        $currentTables163,
    ));
};

$tests['compound multi anchor recursive window limit next163 rejects missing final limit'] = static function (TestRunner $t) use ($currentTables163): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundMultiAnchorRecursiveWindowLimitCurrentSourceNextPlan::compareNext163(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed-a', 23) UNION VALUES (2, 'seed-b', 19) EXCEPT VALUES (9, 'skip', 1) UNION SELECT id + 2, label, weight - 5 FROM staged WHERE id < 7 LIMIT 5 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY weight) AS rank FROM staged UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight) FROM wp_options ORDER BY rank",
        $currentTables163,
        $currentTables163,
    ));
};

foreach (range(1, 56) as $case) {
    $tests['compound multi anchor recursive window limit next163 generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $offset = $case % 2;
        $tables = [
            'wp_options' => [
                ['option_id' => 20, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 50 + $case],
                ['option_id' => 21, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 40 + $case],
                ['option_id' => 22, 'option_name' => 'skip_' . $case, 'autoload' => 'no', 'weight' => 80 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed_a_{$case}', " . (30 + $case) . ") UNION VALUES (1, 'seed_a_{$case}', " . (30 + $case) . "), (2, 'seed_b_{$case}', " . (25 + $case) . "), (99, 'discard_{$case}', 1) EXCEPT VALUES (99, 'discard_{$case}', 1) UNION SELECT id + 2, label || ':' || (id + 2), weight - 3 FROM staged WHERE id < 7 ORDER BY weight DESC LIMIT 5 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY weight DESC, id) AS rank FROM staged UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY weight DESC, option_id) AS rank FROM wp_options WHERE autoload = 'yes' ORDER BY rank, id LIMIT {$finalLimit} OFFSET {$offset}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['rank']));
        $t->true($rows[0]['rank'] <= $rows[count($rows) - 1]['rank']);
    };
}

return $tests;

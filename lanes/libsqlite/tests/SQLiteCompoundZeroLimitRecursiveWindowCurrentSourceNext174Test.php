<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions174 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 45],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 40],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 30],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 38],
];
$nextOptions174 = [
    ...$currentOptions174,
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 43],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 28],
];
$currentTables174 = ['wp_options' => $currentOptions174];
$nextTables174 = ['wp_options' => $nextOptions174];

$sql174 = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 50)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 5
      FROM q
     WHERE id < 5
     LIMIT 4
)
SELECT id,
       label,
       row_number() OVER (ORDER BY weight DESC) AS bucket
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (ORDER BY weight DESC, option_id) AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY bucket, id
 LIMIT 0 OFFSET 2
SQL;

$summary174 = static fn (): array => SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan::compareNext174($sql174, $currentTables174, $nextTables174);
$tests = [];

$tests['compound zero limit recursive window current source next174 status dependencies'] = static function (TestRunner $t) use ($summary174): void {
    $plan = $summary174();
    $t->same('compound-zero-limit-recursive-window-current-source-next174-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-cte-limit-next174',
        'sqlite-select-sql-window-before-compound-limit-zero-next174',
        'sqlite-select-sql-compound-final-limit-zero-next174',
        'sqlite-current-source-next174',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound zero limit recursive window current source next174 compound metadata'] = static function (TestRunner $t) use ($summary174): void {
    $compound = $summary174()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['bucket', 'id'], $compound['orderColumns']);
    $t->same(0, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->same(true, $compound['zeroLimitSuppressesRows']);
};

$tests['compound zero limit recursive window current source next174 visible rows suppressed'] = static function (TestRunner $t) use ($summary174): void {
    $plan = $summary174();
    $t->same([], $plan['currentRows']);
    $t->same([], $plan['nextRows']);
    $t->same(7, count($plan['currentPreLimitRows']));
    $t->same(9, count($plan['nextPreLimitRows']));
};

$tests['compound zero limit recursive window current source next174 current prelimit rows'] = static function (TestRunner $t) use ($summary174): void {
    $rows = $summary174()['currentPreLimitRows'];
    $t->same([1, 1, 2, 2, 3, 3, 4], array_column($rows, 'id'));
    $t->same(['seed', 'siteurl', 'seed:2', 'home', 'seed:2:3', 'blogname', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([1, 1, 2, 2, 3, 3, 4], array_column($rows, 'bucket'));
};

$tests['compound zero limit recursive window current source next174 next prelimit rows'] = static function (TestRunner $t) use ($summary174): void {
    $rows = $summary174()['nextPreLimitRows'];
    $t->same([1, 1, 2, 5, 2, 3, 3, 4, 6], array_column($rows, 'id'));
    $t->same(['seed', 'siteurl', 'seed:2', 'rewrite_rules', 'home', 'seed:2:3', 'blogname', 'seed:2:3:4', 'theme_mods'], array_column($rows, 'label'));
    $t->same([1, 1, 2, 2, 3, 3, 4, 4, 5], array_column($rows, 'bucket'));
};

$tests['compound zero limit recursive window current source next174 window metadata'] = static function (TestRunner $t) use ($summary174): void {
    $windows = $summary174()['windows']['current'];
    $t->same(['row_number', 'dense_rank'], array_column($windows, 'function'));
    $t->same(['bucket', 'bucket'], array_column($windows, 'alias'));
    $t->same([0, 0], array_column($windows, 'partitionCount'));
    $t->same([1, 2], array_column($windows, 'orderCount'));
};

$tests['compound zero limit recursive window current source next174 recursive trace'] = static function (TestRunner $t) use ($summary174): void {
    $recursive = $summary174()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(4, $recursive['currentTraceCount']);
    $t->same(4, $recursive['nextTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['nextLimitRemaining']);
    $t->same(['seed', 'seed:2', 'seed:2:3', 'seed:2:3:4'], array_column($recursive['currentRows'], 'label'));
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound zero limit recursive window current source next174 limit trace'] = static function (TestRunner $t) use ($summary174): void {
    $trace = $summary174()['limitTrace'];
    $t->same(7, $trace['current']['preLimitCount']);
    $t->same(9, $trace['next']['preLimitCount']);
    $t->same(0, $trace['current']['acceptedCount']);
    $t->same(0, $trace['next']['acceptedCount']);
    $t->same(7, $trace['current']['suppressedCount']);
    $t->same(9, $trace['next']['suppressedCount']);
    $t->same(2, $trace['current']['offsetIgnoredByZeroLimit']);
    $t->same('seed', $trace['current']['firstSuppressed']['label']);
    $t->same('theme_mods', $trace['next']['lastSuppressed']['label']);
};

$tests['compound zero limit recursive window current source next174 source delta'] = static function (TestRunner $t) use ($summary174): void {
    $delta = $summary174()['sourceDelta'];
    $t->same(['rewrite_rules', 'theme_mods'], $delta['suppressedAddedLabels']);
    $t->same([], $delta['suppressedRemovedLabels']);
    $t->true(in_array('siteurl', $delta['currentLabels'], true));
    $t->true(in_array('rewrite_rules', $delta['nextLabels'], true));
};

$tests['compound zero limit recursive window current source next174 changed suppressed signatures'] = static function (TestRunner $t) use ($summary174): void {
    $plan = $summary174();
    $changed = implode("\n", $plan['changedSuppressedSignatures']);
    $t->contains('"label":"rewrite_rules"', $changed);
    $t->contains('"label":"theme_mods"', $changed);
    $t->contains('"label":"home"', $changed);
    $t->true(count($plan['changedSuppressedSignatures']) > count($plan['sourceDelta']['suppressedAddedLabels']));
};

$tests['compound zero limit recursive window current source next174 replan reasons'] = static function (TestRunner $t) use ($summary174): void {
    $reasons = $summary174()['replanReasons'];
    $t->true(in_array('compound-final-limit-zero-suppressed-output', $reasons, true));
    $t->true(in_array('current-next-visible-rowset-empty', $reasons, true));
    $t->true(in_array('suppressed-prelimit-rowset-changed', $reasons, true));
    $t->true(in_array('window-evaluated-before-final-limit-zero', $reasons, true));
};

$tests['compound zero limit recursive window current source next174 rejects missing final zero limit'] = static function (TestRunner $t) use ($currentTables174): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan::compareNext174(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 50) UNION ALL SELECT id + 1, label, weight - 5 FROM q WHERE id < 5 LIMIT 4) SELECT id, label, row_number() OVER (ORDER BY weight DESC) AS bucket FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY weight DESC) FROM wp_options ORDER BY bucket LIMIT 1",
        $currentTables174,
        $currentTables174,
    ));
};

$tests['compound zero limit recursive window current source next174 rejects missing window'] = static function (TestRunner $t) use ($currentTables174): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan::compareNext174(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 50) UNION ALL SELECT id + 1, label, weight - 5 FROM q WHERE id < 5 LIMIT 4) SELECT id, label, weight AS bucket FROM q UNION ALL SELECT option_id, option_name, weight FROM wp_options ORDER BY bucket LIMIT 0",
        $currentTables174,
        $currentTables174,
    ));
};

$tests['compound zero limit recursive window current source next174 rejects non compound'] = static function (TestRunner $t) use ($currentTables174): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundZeroLimitRecursiveWindowCurrentSourceNextPlan::compareNext174(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 50)) SELECT id, label, row_number() OVER (ORDER BY weight DESC) AS bucket FROM q LIMIT 0",
        $currentTables174,
        $currentTables174,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound zero limit recursive window current source next174 generated suppressed boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 60 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 50 + $case],
                ['option_id' => 3, 'option_name' => 'plugin_' . $case, 'autoload' => 'no', 'weight' => 40 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (70 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 5 FROM q WHERE id < 5 LIMIT 4) SELECT id, label, row_number() OVER (ORDER BY weight DESC) AS bucket FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (ORDER BY weight DESC, option_id) AS bucket FROM wp_options WHERE autoload = 'yes' ORDER BY bucket, id LIMIT 0 OFFSET 1";
        $rows = SQLiteSelectSql::execute($sql, $tables);
        $preLimit = SQLiteSelectSql::execute(preg_replace('/\s+LIMIT\s+0\s+OFFSET\s+1$/', '', $sql) ?? $sql, $tables);

        $t->same([], $rows);
        $t->same(6, count($preLimit));
        $t->same('seed_' . $case, $preLimit[0]['label'] ?? null);
        $t->true(in_array('autoload_' . $case, array_column($preLimit, 'label'), true));
    };
}

return $tests;

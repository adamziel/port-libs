<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'seq' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'seq' => 2],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'seq' => 4],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'seq' => 3],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'seq' => 3],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE wanted(pos, name) AS (
    VALUES (1, 'siteurl')
    UNION ALL
    SELECT pos + 1,
           CASE pos + 1
                WHEN 2 THEN 'home'
                WHEN 3 THEN 'rewrite_rules'
                WHEN 4 THEN 'blogname'
           END
      FROM wanted
     WHERE pos < 4
     LIMIT 4
)
SELECT name,
       pos,
       row_number() OVER (ORDER BY pos) AS rank
  FROM wanted
INTERSECT
SELECT option_name AS name,
       seq AS pos,
       row_number() OVER (ORDER BY seq) AS rank
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rank, name
 LIMIT 2 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan::compareIntersectRecursiveWindowLimit($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound intersect recursive window limit current source status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-intersect-recursive-window-limit-current-source-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-cte-queue-limit',
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-compound-intersect',
        'sqlite-select-sql-compound-final-limit',
        'sqlite-current-source',
    ], $plan['dependencies']);
};

$tests['compound intersect recursive window limit current source compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['INTERSECT'], $compound['operators']);
    $t->same([1], $compound['intersectArmIndexes']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['rank', 'name'], $compound['orderColumns']);
    $t->same(2, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound intersect recursive window limit current source current limited rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['home'], array_column($rows, 'name'));
    $t->same([2], array_column($rows, 'pos'));
    $t->same([2], array_column($rows, 'rank'));
};

$tests['compound intersect recursive window limit current source next limited rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['home', 'rewrite_rules'], array_column($rows, 'name'));
    $t->same([2, 3], array_column($rows, 'pos'));
    $t->same([2, 3], array_column($rows, 'rank'));
};

$tests['compound intersect recursive window limit current source recursive queue trace'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('wanted', $recursive['name']);
    $t->same(['pos', 'name'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(4, $recursive['currentTraceCount']);
    $t->same(4, $recursive['nextTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['nextLimitRemaining']);
    $t->same(['siteurl', 'home', 'rewrite_rules', 'blogname'], array_column($recursive['currentRows'], 'name'));
};

$tests['compound intersect recursive window limit current source window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['row_number', 'row_number'], array_column($windows, 'function'));
    $t->same(['rank', 'rank'], array_column($windows, 'alias'));
    $t->same([0, 0], array_column($windows, 'partitionCount'));
    $t->same([1, 1], array_column($windows, 'orderCount'));
};

$tests['compound intersect recursive window limit current source intersect retention trace'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['intersectTrace'];
    $t->same(['siteurl', 'home'], $trace['currentRetainedNames']);
    $t->same(['siteurl', 'home', 'rewrite_rules', 'blogname'], $trace['nextRetainedNames']);
    $t->same(['rewrite_rules', 'blogname'], $trace['currentRemovedNames']);
    $t->same([], $trace['nextRemovedNames']);
    $t->same([4], array_column($trace['current'], 'beforeCount'));
    $t->same([2], array_column($trace['current'], 'afterCount'));
    $t->same([4], array_column($trace['next'], 'beforeCount'));
    $t->same([4], array_column($trace['next'], 'afterCount'));
};

$tests['compound intersect recursive window limit current source prelimit and final limit trace'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['siteurl', 'home'], array_column($plan['currentPreLimitRows'], 'name'));
    $t->same(['siteurl', 'home', 'rewrite_rules', 'blogname'], array_column($plan['nextPreLimitRows'], 'name'));
    $t->same(['siteurl'], array_column($plan['limitTrace']['current']['skippedBeforeOffset'], 'name'));
    $t->same(['siteurl'], array_column($plan['limitTrace']['next']['skippedBeforeOffset'], 'name'));
    $t->same([], array_column($plan['limitTrace']['current']['truncatedAfterLimit'], 'name'));
    $t->same(['blogname'], array_column($plan['limitTrace']['next']['truncatedAfterLimit'], 'name'));
};

$tests['compound intersect recursive window limit current source boundary and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"name":"rewrite_rules"', $changed);
    $t->same('home', $plan['boundary']['currentFirst']['name']);
    $t->same('home', $plan['boundary']['nextFirst']['name']);
    $t->same('home', $plan['boundary']['currentLast']['name']);
    $t->same('rewrite_rules', $plan['boundary']['nextLast']['name']);
    $t->same(['rewrite_rules'], $plan['boundary']['admittedNamesChanged']);
    $t->true(in_array('limited-intersect-recursive-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-intersect-recursive-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('intersect-retention-trace-changed', $plan['replanReasons'], true));
    $t->true(in_array('window-before-intersect', $plan['replanReasons'], true));
};

$tests['compound intersect recursive window limit current source rejects non intersect'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan::compareIntersectRecursiveWindowLimit(
        "WITH RECURSIVE wanted(pos, name) AS (VALUES (1, 'siteurl') UNION ALL SELECT pos + 1, 'home' FROM wanted WHERE pos < 2 LIMIT 2) SELECT name, pos, row_number() OVER (ORDER BY pos) AS rank FROM wanted UNION ALL SELECT option_name, seq, row_number() OVER (ORDER BY seq) FROM wp_options LIMIT 1",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound intersect recursive window limit current source rejects missing limit'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan::compareIntersectRecursiveWindowLimit(
        "WITH RECURSIVE wanted(pos, name) AS (VALUES (1, 'siteurl') UNION ALL SELECT pos + 1, 'home' FROM wanted WHERE pos < 2 LIMIT 2) SELECT name, pos, row_number() OVER (ORDER BY pos) AS rank FROM wanted INTERSECT SELECT option_name, seq, row_number() OVER (ORDER BY seq) FROM wp_options ORDER BY rank",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound intersect recursive window limit current source rejects missing recursive cte'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundIntersectRecursiveWindowLimitCurrentSourceNextPlan::compareIntersectRecursiveWindowLimit(
        'SELECT option_name AS name, seq AS pos, row_number() OVER (ORDER BY seq) AS rank FROM wp_options INTERSECT SELECT option_name, seq, row_number() OVER (ORDER BY seq) FROM wp_options LIMIT 1',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound intersect recursive window limit current source generated recursive intersect boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'seq' => 1],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'seq' => 2],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'seq' => 3],
                ['option_id' => 4, 'option_name' => 'skip_' . $case, 'autoload' => 'no', 'seq' => 4],
            ],
        ];
        $limit = 3 + ($case % 2);
        $sql = "WITH RECURSIVE wanted(pos, name) AS (VALUES (1, 'autoload_{$case}') UNION ALL SELECT pos + 1, CASE pos + 1 WHEN 2 THEN 'home_{$case}' WHEN 3 THEN 'rewrite_{$case}' WHEN 4 THEN 'skip_{$case}' END FROM wanted WHERE pos < 4 LIMIT {$limit}) SELECT name, pos, row_number() OVER (ORDER BY pos) AS rank FROM wanted INTERSECT SELECT option_name AS name, seq AS pos, row_number() OVER (ORDER BY seq) AS rank FROM wp_options WHERE autoload = 'yes' ORDER BY rank, name LIMIT 2 OFFSET 1";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(['home_' . $case, 'rewrite_' . $case], array_column($rows, 'name'));
        $t->same([2, 3], array_column($rows, 'pos'));
        $t->same([2, 3], array_column($rows, 'rank'));
    };
}

return $tests;

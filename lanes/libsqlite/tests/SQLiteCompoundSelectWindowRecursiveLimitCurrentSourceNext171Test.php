<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions171 = [
    ['option_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 30],
    ['option_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 20],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'weight' => 15],
];
$nextOptions171 = [
    ...$currentOptions171,
    ['option_id' => 4, 'option_name' => 'plugin_cache', 'autoload' => 'yes', 'weight' => 12],
];
$currentTables171 = ['wp_options' => $currentOptions171];
$nextTables171 = ['wp_options' => $nextOptions171];

$sql171 = <<<'SQL'
WITH RECURSIVE wanted(pos, label, weight) AS (
    VALUES (0, 'skip_anchor', 99)
    UNION ALL
    SELECT pos + 1,
           CASE pos + 1
                WHEN 1 THEN 'home'
                WHEN 2 THEN 'siteurl'
                WHEN 3 THEN 'rewrite_rules'
                WHEN 4 THEN 'plugin_cache'
           END,
           weight - 20
      FROM wanted
     WHERE pos < 4
     LIMIT 4 OFFSET 1
)
SELECT label,
       pos,
       row_number() OVER (ORDER BY pos) AS rn
  FROM wanted
UNION
SELECT option_name AS label,
       option_id AS pos,
       row_number() OVER (ORDER BY option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rn, label
 LIMIT 4 OFFSET 1
SQL;

$summary171 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext171($sql171, $currentTables171, $nextTables171);
$tests = [];

$tests['compound select window recursive limit current source next171 status dependencies'] = static function (TestRunner $t) use ($summary171): void {
    $plan = $summary171();
    $t->same('compound-select-window-recursive-limit-current-source-next171-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-cte-limit-offset-next171',
        'sqlite-select-sql-window-arm-evaluation',
        'sqlite-select-sql-compound-union-distinct-next171',
        'sqlite-select-sql-compound-final-limit-offset-next171',
        'sqlite-current-source-next171',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next171 compound metadata'] = static function (TestRunner $t) use ($summary171): void {
    $compound = $summary171()['compound'];
    $t->same(['UNION'], $compound['operators']);
    $t->true($compound['usesDistinctUnion']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['rn', 'label'], $compound['orderColumns']);
    $t->same(4, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound select window recursive limit current source next171 current limited rows'] = static function (TestRunner $t) use ($summary171): void {
    $rows = $summary171()['currentRows'];
    $t->same(['siteurl', 'rewrite_rules', 'plugin_cache'], array_column($rows, 'label'));
    $t->same([2, 3, 4], array_column($rows, 'pos'));
    $t->same([2, 3, 4], array_column($rows, 'rn'));
};

$tests['compound select window recursive limit current source next171 next limited rows'] = static function (TestRunner $t) use ($summary171): void {
    $rows = $summary171()['nextRows'];
    $t->same(['siteurl', 'plugin_cache', 'rewrite_rules', 'plugin_cache'], array_column($rows, 'label'));
    $t->same([2, 4, 3, 4], array_column($rows, 'pos'));
    $t->same([2, 3, 3, 4], array_column($rows, 'rn'));
};

$tests['compound select window recursive limit current source next171 recursive offset trace'] = static function (TestRunner $t) use ($summary171): void {
    $recursive = $summary171()['recursive'];
    $t->same('wanted', $recursive['name']);
    $t->same(['pos', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(5, $recursive['currentTraceCount']);
    $t->same(5, $recursive['nextTraceCount']);
    $t->same(['skip_anchor'], $recursive['currentSkippedLabels']);
    $t->same(['home', 'siteurl', 'rewrite_rules', 'plugin_cache'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit current source next171 window metadata'] = static function (TestRunner $t) use ($summary171): void {
    $windows = $summary171()['windows']['current'];
    $t->same(['row_number', 'row_number'], array_column($windows, 'function'));
    $t->same(['rn', 'rn'], array_column($windows, 'alias'));
    $t->same([0, 0], array_column($windows, 'partitionCount'));
    $t->same([1, 1], array_column($windows, 'orderCount'));
};

$tests['compound select window recursive limit current source next171 union distinct trace'] = static function (TestRunner $t) use ($summary171): void {
    $trace = $summary171()['unionTrace'];
    $t->same(['home', 'siteurl'], $trace['currentDuplicateLabels']);
    $t->same(['home', 'siteurl', 'plugin_cache'], $trace['nextDuplicateLabels']);
    $t->same(4, $trace['currentPreLimitCount']);
    $t->same(5, $trace['nextPreLimitCount']);
};

$tests['compound select window recursive limit current source next171 prelimit and final limit trace'] = static function (TestRunner $t) use ($summary171): void {
    $plan = $summary171();
    $t->same(['home', 'siteurl', 'rewrite_rules', 'plugin_cache'], array_column($plan['currentPreLimitRows'], 'label'));
    $t->same(['home', 'siteurl', 'plugin_cache', 'rewrite_rules', 'plugin_cache'], array_column($plan['nextPreLimitRows'], 'label'));
    $t->same(['home'], array_column($plan['limitTrace']['current']['skippedBeforeOffset'], 'label'));
    $t->same(['home'], array_column($plan['limitTrace']['next']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($plan['limitTrace']['current']['truncatedAfterLimit'], 'label'));
    $t->same([], array_column($plan['limitTrace']['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit current source next171 boundary and reasons'] = static function (TestRunner $t) use ($summary171): void {
    $plan = $summary171();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_cache","pos":4,"rn":3', $changed);
    $t->same('siteurl', $plan['boundary']['currentFirst']['label']);
    $t->same('siteurl', $plan['boundary']['nextFirst']['label']);
    $t->same('plugin_cache', $plan['boundary']['currentLast']['label']);
    $t->same('plugin_cache', $plan['boundary']['nextLast']['label']);
    $t->true(in_array('recursive-limit-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('window-values-before-union-distinct', $plan['replanReasons'], true));
    $t->true(in_array('compound-union-distinct-before-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next171 rejects union all'] = static function (TestRunner $t) use ($currentTables171): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext171(
        "WITH RECURSIVE wanted(pos, label, weight) AS (VALUES (0, 'skip', 1) UNION ALL SELECT pos + 1, 'home', weight + 1 FROM wanted WHERE pos < 2 LIMIT 2 OFFSET 1) SELECT label, pos, row_number() OVER (ORDER BY pos) AS rn FROM wanted UNION ALL SELECT option_name, option_id, row_number() OVER (ORDER BY option_id) FROM wp_options ORDER BY rn, label LIMIT 1 OFFSET 0",
        $currentTables171,
        $currentTables171,
    ));
};

$tests['compound select window recursive limit current source next171 rejects missing recursive offset'] = static function (TestRunner $t) use ($currentTables171): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext171(
        "WITH RECURSIVE wanted(pos, label, weight) AS (VALUES (0, 'skip', 1) UNION ALL SELECT pos + 1, 'home', weight + 1 FROM wanted WHERE pos < 2 LIMIT 2) SELECT label, pos, row_number() OVER (ORDER BY pos) AS rn FROM wanted UNION SELECT option_name, option_id, row_number() OVER (ORDER BY option_id) FROM wp_options ORDER BY rn, label LIMIT 1 OFFSET 0",
        $currentTables171,
        $currentTables171,
    ));
};

$tests['compound select window recursive limit current source next171 rejects missing final offset'] = static function (TestRunner $t) use ($currentTables171): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext171(
        "WITH RECURSIVE wanted(pos, label, weight) AS (VALUES (0, 'skip', 1) UNION ALL SELECT pos + 1, 'home', weight + 1 FROM wanted WHERE pos < 2 LIMIT 2 OFFSET 1) SELECT label, pos, row_number() OVER (ORDER BY pos) AS rn FROM wanted UNION SELECT option_name, option_id, row_number() OVER (ORDER BY option_id) FROM wp_options ORDER BY rn, label LIMIT 1",
        $currentTables171,
        $currentTables171,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit current source next171 generated union distinct boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 30],
                ['option_id' => 2, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'weight' => 20],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'no', 'weight' => 15],
                ['option_id' => 4, 'option_name' => 'plugin_' . $case, 'autoload' => $case % 2 === 0 ? 'yes' : 'no', 'weight' => 12],
            ],
        ];
        $sql = "WITH RECURSIVE wanted(pos, label, weight) AS (VALUES (0, 'skip_{$case}', 99) UNION ALL SELECT pos + 1, CASE pos + 1 WHEN 1 THEN 'home_{$case}' WHEN 2 THEN 'siteurl_{$case}' WHEN 3 THEN 'rewrite_{$case}' WHEN 4 THEN 'plugin_{$case}' END, weight - 20 FROM wanted WHERE pos < 4 LIMIT 4 OFFSET 1) SELECT label, pos, row_number() OVER (ORDER BY pos) AS rn FROM wanted UNION SELECT option_name AS label, option_id AS pos, row_number() OVER (ORDER BY option_id) AS rn FROM wp_options WHERE autoload = 'yes' ORDER BY rn, label LIMIT 4 OFFSET 1";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $expectedLabels = $case % 2 === 0
            ? ['siteurl_' . $case, 'plugin_' . $case, 'rewrite_' . $case, 'plugin_' . $case]
            : ['siteurl_' . $case, 'rewrite_' . $case, 'plugin_' . $case];
        $expectedPositions = $case % 2 === 0 ? [2, 4, 3, 4] : [2, 3, 4];
        $expectedRanks = $case % 2 === 0 ? [2, 3, 3, 4] : [2, 3, 4];

        $t->same($expectedLabels, array_column($rows, 'label'));
        $t->same($expectedPositions, array_column($rows, 'pos'));
        $t->same($expectedRanks, array_column($rows, 'rn'));
    };
}

return $tests;

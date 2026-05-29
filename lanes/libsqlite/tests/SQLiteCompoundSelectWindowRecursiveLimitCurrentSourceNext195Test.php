<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentTables195 = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
        ['option_id' => 3, 'option_name' => 'transient_cleanup', 'autoload' => 'yes', 'score' => 80],
        ['option_id' => 9, 'option_name' => 'plugin_only', 'autoload' => 'yes', 'score' => 10],
    ],
];
$nextTables195 = [
    'wp_options' => [
        ...$currentTables195['wp_options'],
        ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
    ],
];

$sql195 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'siteurl', 100)
    UNION ALL
    SELECT id + 1,
           CASE id + 1
             WHEN 2 THEN 'home'
             WHEN 3 THEN 'transient_cleanup'
             WHEN 4 THEN 'theme_mods'
             ELSE 'rewrite_rules'
           END,
           score - 10
      FROM q
     WHERE id < 5
     ORDER BY 3 DESC
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC) AS pos
  FROM q
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY score DESC, option_id) AS pos
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT 3 AS id,
       'transient_cleanup' AS label,
       3 AS pos
 ORDER BY pos DESC, id
 LIMIT 3 OFFSET 1
SQL;

$summary195 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext195($sql195, $currentTables195, $nextTables195);
$tests = [];

$tests['compound select window recursive limit current source next195 status dependencies'] = static function (TestRunner $t) use ($summary195): void {
    $plan = $summary195();
    $t->same('compound-select-window-recursive-limit-current-source-next195-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-window-intersect-next195',
        'sqlite-select-sql-compound-except-antijoin-next195',
        'sqlite-current-source-compound-limit-boundary-next195',
        'sqlite-current-source-next195',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next195 compound metadata'] = static function (TestRunner $t) use ($summary195): void {
    $compound = $summary195()['compound'];
    $t->same(['INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['pos', 'id'], $compound['orderColumns']);
    $t->same(3, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['intersectBeforeExcept']);
};

$tests['compound select window recursive limit current source next195 current rows'] = static function (TestRunner $t) use ($summary195): void {
    $rows = $summary195()['currentRows'];
    $t->same([1], array_column($rows, 'id'));
    $t->same(['siteurl'], array_column($rows, 'label'));
    $t->same([1], array_column($rows, 'pos'));
};

$tests['compound select window recursive limit current source next195 next rows'] = static function (TestRunner $t) use ($summary195): void {
    $rows = $summary195()['nextRows'];
    $t->same([4, 2, 1], array_column($rows, 'id'));
    $t->same(['theme_mods', 'home', 'siteurl'], array_column($rows, 'label'));
    $t->same([4, 2, 1], array_column($rows, 'pos'));
};

$tests['compound select window recursive limit current source next195 prelimit rows'] = static function (TestRunner $t) use ($summary195): void {
    $plan = $summary195();
    $t->same(['home', 'siteurl'], array_column($plan['currentPreLimitRows'], 'label'));
    $t->same(['rewrite_rules', 'theme_mods', 'home', 'siteurl'], array_column($plan['nextPreLimitRows'], 'label'));
    $t->same([2, 1], array_column($plan['currentPreLimitRows'], 'pos'));
    $t->same([5, 4, 2, 1], array_column($plan['nextPreLimitRows'], 'pos'));
};

$tests['compound select window recursive limit current source next195 window metadata'] = static function (TestRunner $t) use ($summary195): void {
    $windows = $summary195()['windows'];
    $t->same(['row_number'], $windows['functions']);
    $t->same(['pos', 'pos'], array_column($windows['current'], 'alias'));
    $t->same([1, 2], array_column($windows['current'], 'orderCount'));
    $t->same('pos', $windows['positionColumn']);
};

$tests['compound select window recursive limit current source next195 recursive trace'] = static function (TestRunner $t) use ($summary195): void {
    $recursive = $summary195()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(5, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(['siteurl', 'home', 'transient_cleanup', 'theme_mods', 'rewrite_rules'], array_column($recursive['currentRows'], 'label'));
};

$tests['compound select window recursive limit current source next195 intersect except diagnostics'] = static function (TestRunner $t) use ($summary195): void {
    $diagnostics = $summary195()['intersectExcept'];
    $t->same(['transient_cleanup', 'theme_mods', 'rewrite_rules'], $diagnostics['currentRemovedLabels']);
    $t->same(['transient_cleanup'], $diagnostics['nextRemovedLabels']);
    $t->same(['theme_mods', 'home'], $diagnostics['gainedAfterNextSource']);
};

$tests['compound select window recursive limit current source next195 final limit trace'] = static function (TestRunner $t) use ($summary195): void {
    $trace = $summary195()['limitTrace'];
    $t->same(['home'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['rewrite_rules'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same([], array_column($trace['next']['truncatedAfterLimit'], 'label'));
    $t->same('siteurl', $trace['current']['firstAdmitted']['label']);
    $t->same('theme_mods', $trace['next']['firstAdmitted']['label']);
};

$tests['compound select window recursive limit current source next195 boundary delta'] = static function (TestRunner $t) use ($summary195): void {
    $boundary = $summary195()['boundary'];
    $t->same('siteurl', $boundary['currentFirst']['label']);
    $t->same('theme_mods', $boundary['nextFirst']['label']);
    $t->same('siteurl', $boundary['currentLast']['label']);
    $t->same('siteurl', $boundary['nextLast']['label']);
    $t->contains('"label":"theme_mods"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"home"', implode("\n", $boundary['gainedRows']));
};

$tests['compound select window recursive limit current source next195 replan reasons'] = static function (TestRunner $t) use ($summary195): void {
    $reasons = $summary195()['replanReasons'];
    $t->true(in_array('recursive-window-intersect-before-except', $reasons, true));
    $t->true(in_array('except-antijoin-before-final-limit', $reasons, true));
    $t->true(in_array('limited-intersect-except-rowset-changed', $reasons, true));
    $t->true(in_array('prelimit-intersect-except-rowset-changed', $reasons, true));
    $t->true(in_array('final-offset-boundary-shifted', $reasons, true));
};

$tests['compound select window recursive limit current source next195 rejects missing except'] = static function (TestRunner $t) use ($currentTables195): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext195(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 5 ORDER BY 3 DESC LIMIT 5) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS pos FROM q INTERSECT SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC, option_id) FROM wp_options ORDER BY pos LIMIT 2 OFFSET 1",
        $currentTables195,
        $currentTables195,
    ));
};

$tests['compound select window recursive limit current source next195 rejects missing recursive window'] = static function (TestRunner $t) use ($currentTables195): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext195(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 5 ORDER BY 3 DESC LIMIT 5) SELECT id, label, score AS pos FROM q INTERSECT SELECT option_id, option_name, score FROM wp_options EXCEPT SELECT 3, 'transient_cleanup', 80 ORDER BY pos LIMIT 2 OFFSET 1",
        $currentTables195,
        $currentTables195,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound select window recursive limit current source next195 generated intersect except ' . $case] = static function (TestRunner $t) use ($case): void {
        $extra = 3 + ($case % 3);
        $limit = 2 + ($case % 3);
        $offset = 1;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'transient_cleanup_' . $case, 'autoload' => 'yes', 'score' => 80 + $case],
                ['option_id' => 4, 'option_name' => 'theme_mods_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 5, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => 60 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl_{$case}', " . (100 + $case) . ") UNION ALL SELECT id + 1, CASE id + 1 WHEN 2 THEN 'home_{$case}' WHEN 3 THEN 'transient_cleanup_{$case}' WHEN 4 THEN 'theme_mods_{$case}' ELSE 'rewrite_rules_{$case}' END, score - 10 FROM q WHERE id < {$extra} ORDER BY 3 DESC LIMIT {$extra}) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS pos FROM q INTERSECT SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY score DESC, option_id) AS pos FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT 3 AS id, 'transient_cleanup_{$case}' AS label, 3 AS pos ORDER BY pos DESC, id LIMIT {$limit} OFFSET {$offset}";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext195($generatedSql, $tables, $tables);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same(min($limit, max(0, count($plan['currentPreLimitRows']) - $offset)), count($rows));
        $t->same(['INTERSECT', 'EXCEPT'], $plan['compound']['operators']);
        $t->same(['row_number'], $plan['windows']['functions']);
        $t->true(in_array('recursive-window-intersect-before-except', $plan['replanReasons'], true));
        $t->same(false, in_array('transient_cleanup_' . $case, array_column($rows, 'label'), true));
    };
}

return $tests;

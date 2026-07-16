<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions164 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 80],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 70],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 60],
    ['option_id' => 4, 'option_name' => 'cache', 'autoload' => 'no', 'weight' => 50],
];
$nextOptions164 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 80],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 70],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 60],
    ['option_id' => 4, 'option_name' => 'cache', 'autoload' => 'no', 'weight' => 50],
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 40],
];
$currentTables164 = ['wp_options' => $currentOptions164];
$nextTables164 = ['wp_options' => $nextOptions164];

$sql164 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'siteurl', 100)
    UNION ALL
    SELECT id + 1,
           CASE id + 1
             WHEN 2 THEN 'home'
             WHEN 3 THEN 'blogname'
             WHEN 4 THEN 'cache'
             WHEN 5 THEN 'plugin_alpha'
             ELSE 'extra'
           END,
           score - 10
      FROM q
     WHERE id < 6
     ORDER BY 3 DESC
     LIMIT 5
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS win
  FROM q
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY weight DESC, option_id) AS win
  FROM wp_options
 ORDER BY win, id
 LIMIT 3 OFFSET 2
SQL;

$summary164 = static fn (): array => SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan::compareIntersectWindowRecursiveLimit($sql164, $currentTables164, $nextTables164);
$tests = [];

$tests['compound intersect window recursive limit status dependencies'] = static function (TestRunner $t) use ($summary164): void {
    $plan = $summary164();
    $t->same('compound-intersect-window-recursive-limit-current-source-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-queue-order-limit-before-intersect',
        'sqlite-window-arm-before-compound-intersect',
        'sqlite-compound-intersect-final-limit-yield',
    ], $plan['dependencies']);
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
};

$tests['compound intersect window recursive limit compound metadata'] = static function (TestRunner $t) use ($summary164): void {
    $compound = $summary164()['compound'];
    $t->same(['INTERSECT'], $compound['operators']);
    $t->same(2, $compound['armCount']);
    $t->same(['win', 'id'], $compound['orderColumns']);
    $t->same(3, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->same(1, $compound['intersectArmIndex']);
};

$tests['compound intersect window recursive limit current rows'] = static function (TestRunner $t) use ($summary164): void {
    $rows = $summary164()['currentRows'];
    $t->same([3, 4], array_column($rows, 'id'));
    $t->same(['blogname', 'cache'], array_column($rows, 'label'));
    $t->same([3, 4], array_column($rows, 'win'));
};

$tests['compound intersect window recursive limit next rows'] = static function (TestRunner $t) use ($summary164): void {
    $rows = $summary164()['nextRows'];
    $t->same([3, 4, 5], array_column($rows, 'id'));
    $t->same(['blogname', 'cache', 'plugin_alpha'], array_column($rows, 'label'));
    $t->same([3, 4, 5], array_column($rows, 'win'));
};

$tests['compound intersect window recursive limit prelimit rows show intersect before final limit'] = static function (TestRunner $t) use ($summary164): void {
    $plan = $summary164();
    $t->same(['siteurl', 'home', 'blogname', 'cache'], array_column($plan['currentPreLimitRows'], 'label'));
    $t->same(['siteurl', 'home', 'blogname', 'cache', 'plugin_alpha'], array_column($plan['nextPreLimitRows'], 'label'));
};

$tests['compound intersect window recursive limit recursive queue order limit metadata'] = static function (TestRunner $t) use ($summary164): void {
    $recursive = $summary164()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same([1, 2, 3, 4, 5], array_column($recursive['currentRows'], 'id'));
    $t->same(5, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound intersect window recursive limit window metadata'] = static function (TestRunner $t) use ($summary164): void {
    $windows = $summary164()['windows'];
    $t->same(['row_number'], $windows['functions']);
    $t->same(['win', 'win'], array_column($windows['current'], 'alias'));
    $t->same([0, 0], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound intersect window recursive limit intersect diagnostics'] = static function (TestRunner $t) use ($summary164): void {
    $intersect = $summary164()['intersect'];
    $t->same(['siteurl', 'home', 'blogname', 'cache'], $intersect['currentMatchedLabels']);
    $t->same(['siteurl', 'home', 'blogname', 'cache', 'plugin_alpha'], $intersect['nextMatchedLabels']);
    $t->same(['plugin_alpha'], $intersect['changedMatchedLabels']);
    $t->same(['blogname', 'cache', 'plugin_alpha'], $intersect['admittedLabels']);
};

$tests['compound intersect window recursive limit yield boundary'] = static function (TestRunner $t) use ($summary164): void {
    $boundary = $summary164()['yieldBoundary'];
    $t->same(2, $boundary['current']['offset']);
    $t->same(3, $boundary['current']['limit']);
    $t->same(4, $boundary['current']['preLimitCount']);
    $t->same(['siteurl', 'home'], array_column($boundary['current']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($boundary['current']['truncatedAfterLimit'], 'label'));
    $t->same(5, $boundary['next']['preLimitCount']);
    $t->same(['siteurl', 'home'], array_column($boundary['next']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($boundary['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound intersect window recursive limit current next boundary delta'] = static function (TestRunner $t) use ($summary164): void {
    $boundary = $summary164()['boundary'];
    $t->same('blogname', $boundary['currentFirst']['label']);
    $t->same('blogname', $boundary['nextFirst']['label']);
    $t->same('cache', $boundary['currentLast']['label']);
    $t->same('plugin_alpha', $boundary['nextLast']['label']);
    $t->same(['plugin_alpha'], $boundary['gainedLabels']);
    $t->same([], $boundary['lostLabels']);
};

$tests['compound intersect window recursive limit changed signatures and reasons'] = static function (TestRunner $t) use ($summary164): void {
    $plan = $summary164();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->true(in_array('recursive-queue-order-limit-before-intersect', $plan['replanReasons'], true));
    $t->true(in_array('window-before-compound-intersect', $plan['replanReasons'], true));
    $t->true(in_array('compound-intersect-before-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('limited-compound-intersect-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-intersect-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-order-limit-exhausted-before-intersect', $plan['replanReasons'], true));
};

$tests['compound intersect window recursive limit rejects missing recursive'] = static function (TestRunner $t) use ($currentTables164): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan::compareIntersectWindowRecursiveLimit(
        "SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY weight) AS win FROM wp_options INTERSECT SELECT option_id, option_name, row_number() OVER (ORDER BY weight) AS win FROM wp_options ORDER BY win LIMIT 2 OFFSET 1",
        $currentTables164,
        $currentTables164,
    ));
};

$tests['compound intersect window recursive limit rejects missing recursive queue order'] = static function (TestRunner $t) use ($currentTables164): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan::compareIntersectWindowRecursiveLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 4 LIMIT 3) SELECT id, label, row_number() OVER (ORDER BY score) AS win FROM q INTERSECT SELECT option_id, option_name, row_number() OVER (ORDER BY weight) AS win FROM wp_options ORDER BY win LIMIT 2 OFFSET 1",
        $currentTables164,
        $currentTables164,
    ));
};

$tests['compound intersect window recursive limit rejects missing intersect'] = static function (TestRunner $t) use ($currentTables164): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan::compareIntersectWindowRecursiveLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 4 ORDER BY 3 DESC LIMIT 3) SELECT id, label, row_number() OVER (ORDER BY score) AS win FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight) AS win FROM wp_options ORDER BY win LIMIT 2 OFFSET 1",
        $currentTables164,
        $currentTables164,
    ));
};

$tests['compound intersect window recursive limit rejects missing final offset'] = static function (TestRunner $t) use ($currentTables164): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan::compareIntersectWindowRecursiveLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 4 ORDER BY 3 DESC LIMIT 3) SELECT id, label, row_number() OVER (ORDER BY score) AS win FROM q INTERSECT SELECT option_id, option_name, row_number() OVER (ORDER BY weight) AS win FROM wp_options ORDER BY win LIMIT 2",
        $currentTables164,
        $currentTables164,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound intersect window recursive limit generated intersect boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 4 + ($case % 4);
        $finalLimit = 2 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'weight' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'blogname_' . $case, 'autoload' => 'yes', 'weight' => 80 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'weight' => 70 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl_{$case}', " . (100 + $case) . ") UNION ALL SELECT id + 1, CASE id + 1 WHEN 2 THEN 'home_{$case}' WHEN 3 THEN 'blogname_{$case}' WHEN 4 THEN 'cache_{$case}' ELSE 'extra_{$case}' END, score - 10 FROM q WHERE id < 6 ORDER BY 3 DESC LIMIT {$recursiveLimit}) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS win FROM q INTERSECT SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY weight DESC, option_id) AS win FROM wp_options ORDER BY win, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(min($finalLimit, max(0, min($recursiveLimit, 4) - 1)), count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['win']));
        $t->true($rows[0]['win'] >= 2);
        $t->true($rows[0]['label'] !== '');
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions194 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 4, 'option_name' => 'transient_seed', 'autoload' => 'no', 'score' => 30],
];
$nextOptions194 = [
    ...$currentOptions194,
    ['option_id' => 5, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 112],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 86],
];
$currentTables194 = ['wp_options' => $currentOptions194];
$nextTables194 = ['wp_options' => $nextOptions194];

$sql194 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 118)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       1 AS metric
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT 2 AS id,
       'home' AS label,
       1 AS metric
 ORDER BY metric DESC, id
 LIMIT 4 OFFSET 0
SQL;

$summary194 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCursorGate($sql194, $currentTables194, $nextTables194, $cursor);
$tests = [];

$tests['compound select window recursive limit recursiveCursorGate status dependencies'] = static function (TestRunner $t) use ($summary194): void {
    $plan = $summary194();
    $t->same('compound-select-window-recursive-limit-current-source-recursiveCursorGate-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-compound-intersect-except-recursiveCursorGate',
        'sqlite-window-before-compound-membership-recursiveCursorGate',
        'sqlite-recursive-limit-current-source-recursiveCursorGate',
    ], $plan['dependencies']);
};

$tests['compound select window recursive limit recursiveCursorGate compound metadata'] = static function (TestRunner $t) use ($summary194): void {
    $compound = $summary194()['compound'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([4, 0], [$compound['limit'], $compound['offset']]);
};

$tests['compound select window recursive limit recursiveCursorGate recursive trace'] = static function (TestRunner $t) use ($summary194): void {
    $recursive = $summary194()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $recursive['currentEmittedLabels']);
    $t->same(7, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
};

$tests['compound select window recursive limit recursiveCursorGate windows'] = static function (TestRunner $t) use ($summary194): void {
    $windows = $summary194()['windows'];
    $t->same(['row_number', 'dense_rank'], $windows['functions']);
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit recursiveCursorGate current membership'] = static function (TestRunner $t) use ($summary194): void {
    $boundary = $summary194()['membershipBoundary'];
    $t->same(['siteurl'], $boundary['currentAdmittedLabels']);
    $t->same([], $boundary['currentSkippedByFinalOffset']);
    $t->same([], $boundary['currentTruncatedByFinalLimit']);
    $t->same($boundary['currentAdmittedLabels'], $boundary['currentPreLimitLabels']);
};

$tests['compound select window recursive limit recursiveCursorGate next membership shifts'] = static function (TestRunner $t) use ($summary194): void {
    $boundary = $summary194()['membershipBoundary'];
    $t->same(['plugin_loaded'], $boundary['nextAdmittedLabels']);
    $t->same(['plugin_loaded'], $boundary['gainedAdmittedLabels']);
    $t->same(['siteurl'], $boundary['lostAdmittedLabels']);
    $t->same(64, strlen($boundary['currentToken']));
    $t->same(64, strlen($boundary['nextToken']));
    $t->same(false, $boundary['currentToken'] === $boundary['nextToken']);
};

$tests['compound select window recursive limit recursiveCursorGate cursor accepts current token'] = static function (TestRunner $t) use ($summary194): void {
    $first = $summary194();
    $second = $summary194($first['cursor']);
    $t->same($first['membershipBoundary']['currentToken'], $second['membershipBoundary']['currentToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
};

$tests['compound select window recursive limit recursiveCursorGate rejects stale cursor'] = static function (TestRunner $t) use ($summary194): void {
    $cursor = $summary194()['cursor'];
    $cursor['currentToken'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary194($cursor));
};

$tests['compound select window recursive limit recursiveCursorGate replan reasons'] = static function (TestRunner $t) use ($summary194): void {
    $reasons = $summary194()['replanReasons'];
    $t->true(in_array('compound-intersect-except-window-recursive-limit-recursiveCursorGate', $reasons, true));
    $t->true(in_array('window-values-before-compound-membership-recursiveCursorGate', $reasons, true));
    $t->true(in_array('recursive-limit-current-source-token-recursiveCursorGate', $reasons, true));
    $t->true(in_array('wordpress-option-preview-stale-membership-fence-recursiveCursorGate', $reasons, true));
};

$tests['compound select window recursive limit recursiveCursorGate dependency closure and non overlap'] = static function (TestRunner $t) use ($summary194): void {
    $plan = $summary194();
    $t->contains('no new support component needed', $plan['dependency_closure']);
    $t->contains('avoids accepted source-token-fence', $plan['non_overlap']);
    $t->contains('INTERSECT/EXCEPT', $plan['non_overlap']);
};

$tests['compound select window recursive limit recursiveCursorGate rejects missing intersect'] = static function (TestRunner $t) use ($currentTables194): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCursorGate(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, score FROM wp_options EXCEPT SELECT 2, 'home', 1 ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables194,
        $currentTables194,
    ));
};

$tests['compound select window recursive limit recursiveCursorGate rejects missing except'] = static function (TestRunner $t) use ($currentTables194): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCursorGate(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, score FROM wp_options INTERSECT SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables194,
        $currentTables194,
    ));
};

$tests['compound select window recursive limit recursiveCursorGate rejects missing final limit'] = static function (TestRunner $t) use ($currentTables194): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCursorGate(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION ALL SELECT option_id, option_name, score FROM wp_options INTERSECT SELECT option_id, option_name, score FROM wp_options EXCEPT SELECT 2, 'home', 1 ORDER BY metric",
        $currentTables194,
        $currentTables194,
    ));
};

foreach (range(1, 54) as $case) {
    $tests['compound select window recursive limit recursiveCursorGate generated intersect except fence ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 30 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'queued_' . $case, 'autoload' => 'yes', 'score' => 111 + $case];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (120 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT option_id AS id, option_name AS label, 1 AS metric FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT 2 AS id, 'home_{$case}' AS label, 1 AS metric ORDER BY metric DESC, id LIMIT 4 OFFSET 0";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCursorGate($generatedSql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCursorGate($generatedSql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $plan['compound']['operators']);
        $t->same(count($rows), count($plan['currentRows']));
        $t->same($plan['membershipBoundary']['currentToken'], $again['membershipBoundary']['currentToken']);
        $t->true(in_array('queued_' . $case, $plan['membershipBoundary']['gainedAdmittedLabels'], true));
    };
}

return $tests;

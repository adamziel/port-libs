<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions224 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions224 = [
    ...$currentOptions224,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables224 = ['wp_options' => $currentOptions224];
$nextTables224 = ['wp_options' => $nextOptions224];

$sql224 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS rn
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE option_name IN ('siteurl')
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$summary224 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMixedCompoundRankFence($sql224, $currentTables224, $nextTables224, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next224 status dependencies'] = static function (TestRunner $t) use ($summary224): void {
    $plan = $summary224();
    $t->same('compound-select-window-recursive-limit-current-source-next224-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-limit-offset-next224',
        'sqlite-select-sql-row-number-window-intersect-except-next224',
        'sqlite-compound-current-source-token-fence-next224',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next224 compound metadata'] = static function (TestRunner $t) use ($summary224): void {
    $compound = $summary224()['compound'];
    $t->same(['UNION ALL', 'INTERSECT', 'EXCEPT'], $compound['operators']);
    $t->same([4, 4], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['rn', 'label'], $compound['orderColumns']);
    $t->same([3, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectMiddle']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next224 current rows'] = static function (TestRunner $t) use ($summary224): void {
    $rows = $summary224()['currentRows'];
    $t->same(['rewrite_rules', 'blogname'], array_column($rows, 'label'));
    $t->same([3, 4], array_column($rows, 'rn'));
};

$tests['compound select window recursive limit current source next224 next rows shift except page'] = static function (TestRunner $t) use ($summary224): void {
    $plan = $summary224();
    $t->same(['home', 'rewrite_rules', 'blogname'], array_column($plan['nextRows'], 'label'));
    $t->same([3, 4, 5], array_column($plan['nextRows'], 'rn'));
    $t->same(['plugin_prime'], $plan['sourceWindow']['nextSkippedLabels']);
    $t->same([], $plan['sourceWindow']['nextTruncatedLabels']);
    $t->same(['home'], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next224 recursive queue limit'] = static function (TestRunner $t) use ($summary224): void {
    $queue = $summary224()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_slice($queue['currentEmittedLabels'], 0, 4));
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next224 window metadata'] = static function (TestRunner $t) use ($summary224): void {
    $windows = $summary224()['windows'];
    $t->same(['row_number'], $windows['functions']);
    $t->same([0, 1, 1, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([2, 2, 2, 2], array_column($windows['current'], 'orderCount'));
    $t->same([2, 3, 4], array_map('intval', $windows['currentRanks']));
    $t->same([2, 3, 4, 5], array_map('intval', $windows['nextRanks']));
};

$tests['compound select window recursive limit current source next224 source window trace'] = static function (TestRunner $t) use ($summary224): void {
    $window = $summary224()['sourceWindow'];
    $t->same(['home'], $window['currentSkippedLabels']);
    $t->same(['plugin_prime'], $window['nextSkippedLabels']);
    $t->same([], $window['currentTruncatedLabels']);
    $t->same([], $window['nextTruncatedLabels']);
    $t->true($window['intersectExceptBoundaryChanged']);
    $t->same(false, $window['currentToken'] === $window['nextToken']);
};

$tests['compound select window recursive limit current source next224 cursor token fence'] = static function (TestRunner $t) use ($summary224): void {
    $first = $summary224();
    $second = $summary224($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(4, $first['cursor']['resumeOffset']);
};

$tests['compound select window recursive limit current source next224 rejects stale cursor'] = static function (TestRunner $t) use ($summary224): void {
    $cursor = $summary224()['cursor'];
    $cursor['currentToken'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary224($cursor));
};

$tests['compound select window recursive limit current source next224 executor parity'] = static function (TestRunner $t) use ($sql224, $currentTables224, $summary224): void {
    $t->same(SQLiteSelectSql::execute($sql224, $currentTables224), $summary224()['currentRows']);
};

$tests['compound select window recursive limit current source next224 replan reasons'] = static function (TestRunner $t) use ($summary224): void {
    $plan = $summary224();
    $t->contains('does not repeat next218', $plan['non_overlap']);
    $t->true(in_array('compound-union-all-intersect-except-window-current-source-next224', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-before-intersect-except-window-next224', $plan['replanReasons'], true));
    $t->true(in_array('window-rank-shift-changes-except-final-limit-page-next224', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next224 rejects missing except'] = static function (TestRunner $t) use ($currentTables224): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMixedCompoundRankFence(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS rn FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC, option_id) FROM wp_options INTERSECT SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC, option_id) FROM wp_options ORDER BY rn, label LIMIT 3 OFFSET 1",
        $currentTables224,
        $currentTables224,
    ));
};

$tests['compound select window recursive limit current source next224 rejects missing recursive offset'] = static function (TestRunner $t) use ($currentTables224): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMixedCompoundRankFence(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 6) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS rn FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC, option_id) FROM wp_options INTERSECT SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC, option_id) FROM wp_options EXCEPT SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC, option_id) FROM wp_options ORDER BY rn, label LIMIT 3 OFFSET 1",
        $currentTables224,
        $currentTables224,
    ));
};

foreach (range(1, 60) as $case) {
    $tests['compound select window recursive limit current source next224 generated intersect except boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 80 + $case],
                ['option_id' => 4, 'option_name' => 'blog_' . $case, 'autoload' => 'yes', 'score' => 70 + $case],
                ['option_id' => 5, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 60 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 6, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 95 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS rn FROM q UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS rn FROM wp_options WHERE option_name IN ('siteurl_{$case}') ORDER BY rn, label LIMIT 3 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMixedCompoundRankFence($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareMixedCompoundRankFence($sql, $tables, $nextTables, $plan['cursor']);

        $t->same(['rewrite_' . $case, 'blog_' . $case], array_column($plan['currentRows'], 'label'));
        $t->same(['home_' . $case, 'rewrite_' . $case, 'blog_' . $case], array_column($plan['nextRows'], 'label'));
        $t->same(['plugin_' . $case], $plan['sourceWindow']['nextSkippedLabels']);
        $t->same([], $plan['sourceWindow']['nextTruncatedLabels']);
        $t->same(['home_' . $case], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
        $t->same('seed_' . $case, $plan['recursiveQueue']['currentSkippedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
    };
}

return $tests;

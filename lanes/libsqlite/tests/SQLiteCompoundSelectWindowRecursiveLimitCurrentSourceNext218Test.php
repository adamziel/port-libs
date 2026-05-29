<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions218 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 100],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 90],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 4, 'option_name' => 'blogname', 'autoload' => 'yes', 'score' => 70],
    ['option_id' => 5, 'option_name' => 'transient_cache', 'autoload' => 'no', 'score' => 65],
];
$nextOptions218 = [
    ...$currentOptions218,
    ['option_id' => 6, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 95],
];
$currentTables218 = ['wp_options' => $currentOptions218];
$nextTables218 = ['wp_options' => $nextOptions218];

$sql218 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       row_number() OVER (ORDER BY score DESC, id) AS rn
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (ORDER BY score DESC, option_id) AS rn
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY rn, label
 LIMIT 3 OFFSET 1
SQL;

$summary218 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext218($sql218, $currentTables218, $nextTables218, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next218 status dependencies'] = static function (TestRunner $t) use ($summary218): void {
    $plan = $summary218();
    $t->same('compound-select-window-recursive-limit-current-source-next218-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-limit-offset-next218',
        'sqlite-select-sql-row-number-window-intersect-next218',
        'sqlite-compound-current-source-token-fence-next218',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next218 compound metadata'] = static function (TestRunner $t) use ($summary218): void {
    $compound = $summary218()['compound'];
    $t->same(['UNION ALL', 'INTERSECT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['rn', 'label'], $compound['orderColumns']);
    $t->same([3, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasIntersectTail']);
};

$tests['compound select window recursive limit current source next218 current rows'] = static function (TestRunner $t) use ($summary218): void {
    $rows = $summary218()['currentRows'];
    $t->same(['home', 'rewrite_rules', 'blogname'], array_column($rows, 'label'));
    $t->same([2, 3, 4], array_column($rows, 'rn'));
};

$tests['compound select window recursive limit current source next218 next rows shift window page'] = static function (TestRunner $t) use ($summary218): void {
    $plan = $summary218();
    $t->same(['plugin_prime', 'home', 'rewrite_rules'], array_column($plan['nextRows'], 'label'));
    $t->same([2, 3, 4], array_column($plan['nextRows'], 'rn'));
    $t->same(['plugin_prime', 'home', 'rewrite_rules'], $plan['sourceWindow']['nextAdmittedLabels']);
    $t->same(['plugin_prime'], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['blogname'], $plan['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next218 recursive queue limit'] = static function (TestRunner $t) use ($summary218): void {
    $queue = $summary218()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_slice($queue['currentEmittedLabels'], 0, 4));
    $t->same([6, 6], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next218 window metadata'] = static function (TestRunner $t) use ($summary218): void {
    $windows = $summary218()['windows'];
    $t->same(['row_number'], $windows['functions']);
    $t->same([0, 0, 0], array_column($windows['current'], 'partitionCount'));
    $t->same([2, 2, 2], array_column($windows['current'], 'orderCount'));
    $t->same([1, 2, 3, 4], array_map('intval', $windows['currentRanks']));
    $t->same([1, 2, 3, 4, 5], array_map('intval', $windows['nextRanks']));
};

$tests['compound select window recursive limit current source next218 source window trace'] = static function (TestRunner $t) use ($summary218): void {
    $window = $summary218()['sourceWindow'];
    $t->same(['siteurl'], $window['currentSkippedLabels']);
    $t->same(['siteurl'], $window['nextSkippedLabels']);
    $t->same([], $window['currentTruncatedLabels']);
    $t->same(['blogname'], $window['nextTruncatedLabels']);
    $t->same(false, $window['currentToken'] === $window['nextToken']);
};

$tests['compound select window recursive limit current source next218 cursor token fence'] = static function (TestRunner $t) use ($summary218): void {
    $first = $summary218();
    $second = $summary218($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(4, $first['cursor']['resumeOffset']);
};

$tests['compound select window recursive limit current source next218 rejects stale cursor'] = static function (TestRunner $t) use ($summary218): void {
    $cursor = $summary218()['cursor'];
    $cursor['currentToken'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary218($cursor));
};

$tests['compound select window recursive limit current source next218 executor parity'] = static function (TestRunner $t) use ($sql218, $currentTables218, $summary218): void {
    $t->same(SQLiteSelectSql::execute($sql218, $currentTables218), $summary218()['currentRows']);
};

$tests['compound select window recursive limit current source next218 replan reasons'] = static function (TestRunner $t) use ($summary218): void {
    $plan = $summary218();
    $t->contains('avoids accepted next212', $plan['non_overlap']);
    $t->true(in_array('compound-union-all-intersect-window-current-source-next218', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-offset-before-intersect-window-next218', $plan['replanReasons'], true));
    $t->true(in_array('window-rank-shift-changes-final-limit-page-next218', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next218 rejects missing intersect'] = static function (TestRunner $t) use ($currentTables218): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext218(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 5 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS rn FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC, option_id) FROM wp_options ORDER BY rn, label LIMIT 3 OFFSET 1",
        $currentTables218,
        $currentTables218,
    ));
};

$tests['compound select window recursive limit current source next218 rejects missing recursive offset'] = static function (TestRunner $t) use ($currentTables218): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext218(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 140) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 5) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS rn FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC, option_id) FROM wp_options INTERSECT SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC, option_id) FROM wp_options ORDER BY rn, label LIMIT 3 OFFSET 1",
        $currentTables218,
        $currentTables218,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit current source next218 generated rank shift ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 LIMIT 5 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS rn FROM q UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY score DESC, option_id) AS rn FROM wp_options WHERE autoload = 'yes' INTERSECT SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY score DESC, option_id) AS rn FROM wp_options WHERE autoload = 'yes' ORDER BY rn, label LIMIT 3 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext218($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext218($sql, $tables, $nextTables, $plan['cursor']);

        $t->same(['home_' . $case, 'rewrite_' . $case, 'blog_' . $case], array_column($plan['currentRows'], 'label'));
        $t->same(['plugin_' . $case, 'home_' . $case, 'rewrite_' . $case], array_column($plan['nextRows'], 'label'));
        $t->same(['plugin_' . $case], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
        $t->same(['blog_' . $case], $plan['sourceWindow']['currentOnlyAdmittedLabels']);
        $t->same('seed_' . $case, $plan['recursiveQueue']['currentSkippedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
    };
}

return $tests;

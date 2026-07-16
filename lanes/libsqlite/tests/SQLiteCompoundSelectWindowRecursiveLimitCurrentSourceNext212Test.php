<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions212 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 126],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 98],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 78],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 39],
];
$nextOptions212 = [
    ...$currentOptions212,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 116],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 86],
];
$currentTables212 = ['wp_options' => $currentOptions212];
$nextTables212 = ['wp_options' => $nextOptions212];

$sql212 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 142)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 9
     ORDER BY score DESC
     LIMIT 7 OFFSET 1
)
SELECT id,
       label,
       group_concat(label, '>') OVER (
           ORDER BY score DESC, id
           ROWS BETWEEN 1 PRECEDING AND CURRENT ROW
       ) AS trail
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       row_number() OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
       ) AS trail
  FROM wp_options
 WHERE autoload = 'yes'
EXCEPT
SELECT option_id AS id,
       option_name AS label,
       '2' AS trail
  FROM wp_options
 WHERE option_name = 'home'
 ORDER BY trail DESC, id
 LIMIT 6 OFFSET 1
SQL;

$summary212 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareGroupConcatRowNumberExceptLimit($sql212, $currentTables212, $nextTables212, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next212 status dependencies'] = static function (TestRunner $t) use ($summary212): void {
    $plan = $summary212();
    $t->same('compound-select-window-recursive-limit-current-source-next212-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next212',
        'sqlite-select-sql-group-concat-row-number-window-next212',
        'sqlite-compound-except-current-source-token-fence-next212',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next212 compound metadata'] = static function (TestRunner $t) use ($summary212): void {
    $compound = $summary212()['compound'];
    $t->same(['UNION ALL', 'EXCEPT'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['trail', 'id'], $compound['orderColumns']);
    $t->same([6, 1], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasExceptTail']);
};

$tests['compound select window recursive limit current source next212 current rows'] = static function (TestRunner $t) use ($summary212): void {
    $rows = $summary212()['currentRows'];
    $t->same(6, count($rows));
    $t->same(['seed:2:3:4:5:6:7', 'seed:2:3:4:5:6', 'seed:2:3:4:5', 'seed:2:3:4', 'seed:2:3', 'seed:2'], array_column($rows, 'label'));
    $t->contains('seed:2', (string) $rows[0]['trail']);
};

$tests['compound select window recursive limit current source next212 next boundary shifts prelimit only'] = static function (TestRunner $t) use ($summary212): void {
    $plan = $summary212();
    $t->same(['seed:2:3:4:5:6:7', 'seed:2:3:4:5:6', 'seed:2:3:4:5', 'seed:2:3:4', 'seed:2:3', 'seed:2'], array_column($plan['nextRows'], 'label'));
    $t->same(['theme_mods_next', 'plugin_prime'], array_values(array_filter(array_column($plan['nextPreLimitRows'], 'label'), static fn (string $label): bool => in_array($label, ['plugin_prime', 'theme_mods_next'], true))));
    $t->same([], $plan['sourceWindow']['nextOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next212 recursive queue trace'] = static function (TestRunner $t) use ($summary212): void {
    $queue = $summary212()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentEmittedLabels'], 0, 3));
    $t->same([8, 8], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next212 window shape'] = static function (TestRunner $t) use ($summary212): void {
    $windows = $summary212()['windows'];
    $t->same(['group_concat', 'row_number'], $windows['functions']);
    $t->same([true, false], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->contains('seed', (string) $windows['concatMetrics'][0]);
    $t->same(['3', '2', '1'], array_slice(array_map('strval', $windows['rowNumberMetrics']), 0, 3));
};

$tests['compound select window recursive limit current source next212 token fence'] = static function (TestRunner $t) use ($summary212): void {
    $first = $summary212();
    $second = $summary212($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same(7, $first['cursor']['nextOffset']);
};

$tests['compound select window recursive limit current source next212 rejects stale cursor'] = static function (TestRunner $t) use ($summary212): void {
    $cursor = $summary212()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary212($cursor));
};

$tests['compound select window recursive limit current source next212 limit trace'] = static function (TestRunner $t) use ($summary212): void {
    $trace = $summary212()['limitTrace'];
    $t->same(1, count($trace['current']['skippedBeforeOffset']));
    $t->same(10, $trace['current']['preLimitCount']);
    $t->same(12, $trace['next']['preLimitCount']);
    $t->same(6, $trace['current']['finalCount']);
};

$tests['compound select window recursive limit current source next212 replan reasons'] = static function (TestRunner $t) use ($summary212): void {
    $plan = $summary212();
    $t->contains('avoids accepted next209', $plan['non_overlap']);
    $t->true(in_array('compound-group-concat-row-number-current-source-next212', $plan['replanReasons'], true));
    $t->true(in_array('recursive-ordered-limit-before-string-window-next212', $plan['replanReasons'], true));
    $t->true(in_array('except-after-window-output-next212', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next212 base rows match executor'] = static function (TestRunner $t) use ($sql212, $currentTables212, $summary212): void {
    $t->same(SQLiteSelectSql::execute($sql212, $currentTables212), $summary212()['currentRows']);
};

$tests['compound select window recursive limit current source next212 rejects missing group concat'] = static function (TestRunner $t) use ($currentTables212): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareGroupConcatRowNumberExceptLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 142) UNION ALL SELECT id + 1, label, score - 8 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS trail FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options EXCEPT SELECT option_id, option_name, '2' FROM wp_options ORDER BY trail DESC, id LIMIT 6 OFFSET 1",
        $currentTables212,
        $currentTables212,
    ));
};

$tests['compound select window recursive limit current source next212 rejects missing except'] = static function (TestRunner $t) use ($currentTables212): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareGroupConcatRowNumberExceptLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 142) UNION ALL SELECT id + 1, label, score - 8 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, group_concat(label, '>') OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS trail FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options ORDER BY trail DESC, id LIMIT 6 OFFSET 1",
        $currentTables212,
        $currentTables212,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit current source next212 generated concat boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 126 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 98 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 78 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 39 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 116 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (142 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < 9 ORDER BY score DESC LIMIT 7 OFFSET 1) SELECT id, label, group_concat(label, '>') OVER (ORDER BY score DESC, id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS trail FROM q UNION ALL SELECT option_id AS id, option_name AS label, row_number() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS trail FROM wp_options WHERE autoload = 'yes' EXCEPT SELECT option_id AS id, option_name AS label, '2' AS trail FROM wp_options WHERE option_name = 'home_{$case}' ORDER BY trail DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareGroupConcatRowNumberExceptLimit($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareGroupConcatRowNumberExceptLimit($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['group_concat', 'row_number'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->contains('seed_' . $case, (string) $plan['windows']['concatMetrics'][0]);
    };
}

return $tests;

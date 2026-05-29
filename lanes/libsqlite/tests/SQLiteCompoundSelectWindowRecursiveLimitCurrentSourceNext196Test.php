<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions196 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 94],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 76],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
];
$nextOptions196 = [
    ...$currentOptions196,
    ['option_id' => 5, 'option_name' => 'plugin_prime', 'autoload' => 'yes', 'score' => 109],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 82],
];
$currentTables196 = ['wp_options' => $currentOptions196];
$nextTables196 = ['wp_options' => $nextOptions196];

$sql196 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 136)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 8
     ORDER BY score DESC
     LIMIT 6 OFFSET 1
)
SELECT id,
       label,
       ntile(4) OVER (ORDER BY score DESC, id) AS metric
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       first_value(score) OVER (
           PARTITION BY autoload
           ORDER BY score DESC, option_id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS metric
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT id,
       label,
       score AS metric
  FROM q
 ORDER BY metric DESC, id
 LIMIT 7 OFFSET 2
SQL;

$summary196 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext196($sql196, $currentTables196, $nextTables196, $cursor);
$tests = [];

$tests['compound select window recursive limit current source next196 status dependencies'] = static function (TestRunner $t) use ($summary196): void {
    $plan = $summary196();
    $t->same('compound-select-window-recursive-limit-current-source-next196-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-queue-order-limit-next196',
        'sqlite-select-sql-ntile-first-value-window-next196',
        'sqlite-current-source-token-fence-next196',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next196 compound metadata'] = static function (TestRunner $t) use ($summary196): void {
    $compound = $summary196()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([7, 2], [$compound['limit'], $compound['offset']]);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasDistinctTail']);
};

$tests['compound select window recursive limit current source next196 current row boundary'] = static function (TestRunner $t) use ($summary196): void {
    $rows = $summary196()['currentRows'];
    $t->same(['seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'home', 'seed:2:3:4:5:6', 'rewrite_rules', 'seed:2:3:4:5:6:7'], array_column($rows, 'label'));
    $t->same([116, 106, 96, 94, 86, 76, 76], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next196 next row boundary'] = static function (TestRunner $t) use ($summary196): void {
    $rows = $summary196()['nextRows'];
    $t->same(['seed:2:3', 'plugin_prime', 'seed:2:3:4', 'seed:2:3:4:5', 'home', 'seed:2:3:4:5:6', 'theme_mods_next'], array_column($rows, 'label'));
    $t->same([116, 109, 106, 96, 94, 86, 82], array_column($rows, 'metric'));
};

$tests['compound select window recursive limit current source next196 recursive queue trace'] = static function (TestRunner $t) use ($summary196): void {
    $queue = $summary196()['recursiveQueue'];
    $t->same('q', $queue['name']);
    $t->same(['id', 'label', 'score'], $queue['columns']);
    $t->same('UNION ALL', $queue['operator']);
    $t->same(['seed'], $queue['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6', 'seed:2:3:4:5:6:7'], $queue['currentEmittedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4'], array_slice($queue['currentQueueFronts'], 0, 3));
    $t->same([7, 7], [$queue['currentTraceCount'], $queue['nextTraceCount']]);
    $t->same([0, 0], [$queue['currentLimitRemaining'], $queue['currentOffsetRemaining']]);
};

$tests['compound select window recursive limit current source next196 window shape'] = static function (TestRunner $t) use ($summary196): void {
    $windows = $summary196()['windows'];
    $t->same(['ntile', 'first_value'], $windows['functions']);
    $t->same(['first_value'], $windows['frameFunctions']);
    $t->same([1, 2, 3, 4], $windows['ntileBuckets']);
    $t->same([false, true], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
};

$tests['compound select window recursive limit current source next196 token fence'] = static function (TestRunner $t) use ($summary196): void {
    $first = $summary196();
    $second = $summary196($first['cursor']);
    $t->same(64, strlen($first['sourceWindow']['currentToken']));
    $t->same(64, strlen($first['sourceWindow']['nextToken']));
    $t->same(false, $first['sourceWindow']['currentToken'] === $first['sourceWindow']['nextToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
    $t->same(['plugin_prime', 'theme_mods_next'], $first['sourceWindow']['nextOnlyAdmittedLabels']);
    $t->same(['rewrite_rules', 'seed:2:3:4:5:6:7'], $first['sourceWindow']['currentOnlyAdmittedLabels']);
};

$tests['compound select window recursive limit current source next196 rejects stale cursor'] = static function (TestRunner $t) use ($summary196): void {
    $cursor = $summary196()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary196($cursor));
};

$tests['compound select window recursive limit current source next196 limit trace'] = static function (TestRunner $t) use ($summary196): void {
    $trace = $summary196()['limitTrace'];
    $t->same(['seed:2', 'siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2', 'siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6:7', 'seed:2:3:4:5:6'], array_slice(array_column($trace['current']['truncatedAfterLimit'], 'label'), 0, 2));
    $t->same(['rewrite_rules', 'seed:2:3:4:5:6:7'], array_slice(array_column($trace['next']['truncatedAfterLimit'], 'label'), 0, 2));
};

$tests['compound select window recursive limit current source next196 replan reasons'] = static function (TestRunner $t) use ($summary196): void {
    $plan = $summary196();
    $t->contains('avoids accepted next192', $plan['non_overlap']);
    $t->true(in_array('compound-ntile-first-value-current-source-next196', $plan['replanReasons'], true));
    $t->true(in_array('recursive-order-limit-offset-before-frame-windows-next196', $plan['replanReasons'], true));
    $t->true(in_array('union-distinct-after-window-frame-next196', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source next196 rejects missing first value'] = static function (TestRunner $t) use ($currentTables196): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext196(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 136) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, ntile(4) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY score DESC) FROM wp_options UNION SELECT id, label, score FROM q ORDER BY metric DESC, id LIMIT 7 OFFSET 2",
        $currentTables196,
        $currentTables196,
    ));
};

$tests['compound select window recursive limit current source next196 rejects unordered recursive limit'] = static function (TestRunner $t) use ($currentTables196): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext196(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 136) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 8 LIMIT 6 OFFSET 1) SELECT id, label, ntile(4) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id, option_name, first_value(score) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options UNION SELECT id, label, score FROM q ORDER BY metric DESC, id LIMIT 7 OFFSET 2",
        $currentTables196,
        $currentTables196,
    ));
};

foreach (range(1, 56) as $case) {
    $tests['compound select window recursive limit current source next196 generated frame bucket ' . $case] = static function (TestRunner $t) use ($case): void {
        $finalLimit = 4 + ($case % 4);
        $bucketCount = 2 + ($case % 4);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 118 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 92 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_' . $case, 'autoload' => 'yes', 'score' => 75 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'score' => 20 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 105 + $case];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (136 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 8 ORDER BY score DESC LIMIT 6 OFFSET 1) SELECT id, label, ntile({$bucketCount}) OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, first_value(score) OVER (PARTITION BY autoload ORDER BY score DESC, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS metric FROM wp_options WHERE autoload = 'yes' UNION SELECT id, label, score AS metric FROM q ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 2";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext196($sql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext196($sql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same(['ntile', 'first_value'], $plan['windows']['functions']);
        $t->same(['seed_' . $case], $plan['recursiveQueue']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursiveQueue']['currentEmittedLabels'][0]);
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->true(in_array(1, $plan['windows']['ntileBuckets'], true));
    };
}

return $tests;

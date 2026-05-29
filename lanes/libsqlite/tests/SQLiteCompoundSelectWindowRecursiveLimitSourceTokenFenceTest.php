<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 103],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 81],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 19],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 111],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 73],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 130)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 9
      FROM q
     WHERE id < 9
     LIMIT 7 OFFSET 2
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
UNION
SELECT id,
       label,
       score AS metric
  FROM q
 ORDER BY metric DESC, id
 LIMIT 5 OFFSET 2
SQL;

$summary = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceTokenFence($sql, $currentTables, $nextTables, $cursor);
$tests = [];

$tests['compound select window recursive limit source-token-fence status'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-window-recursive-limit-current-source-source-token-fence-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-compound-window-recursive-limit-source-token-fence',
        'sqlite-current-source-token-fence-source-token-fence',
        'sqlite-window-before-final-compound-limit-source-token-fence',
    ], $plan['dependencies']);
};
$tests['compound select window recursive limit source-token-fence compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(5, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
};
$tests['compound select window recursive limit source-token-fence recursive limit trace'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursiveLimit'];
    $t->same('q', $recursive['name']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(2, count($recursive['currentSkippedLabels']));
    $t->same(7, count($recursive['currentEmittedLabels']));
    $t->same([9, 9], [$recursive['currentTraceCount'], $recursive['nextTraceCount']]);
};
$tests['compound select window recursive limit source-token-fence window terms'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['row_number', 'dense_rank'], $windows['functions']);
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};
$tests['compound select window recursive limit source-token-fence source window labels'] = static function (TestRunner $t) use ($summary): void {
    $source = $summary()['sourceWindow'];
    $t->same(64, strlen($source['currentToken']));
    $t->same(64, strlen($source['nextToken']));
    $t->same(false, $source['currentToken'] === $source['nextToken']);
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6'], array_slice($source['nextAdmittedLabels'], 0, 2));
    $t->true(in_array('theme_mods_next', $source['nextTruncatedLabels'], true));
};
$tests['compound select window recursive limit source-token-fence cursor accepts current token'] = static function (TestRunner $t) use ($summary): void {
    $first = $summary();
    $second = $summary($first['cursor']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
};
$tests['compound select window recursive limit source-token-fence rejects stale cursor'] = static function (TestRunner $t) use ($summary): void {
    $cursor = $summary()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary($cursor));
};
$tests['compound select window recursive limit source-token-fence rejects missing recursive'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceTokenFence(
        'SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY option_id) AS metric FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2 OFFSET 0',
        $currentTables,
        $currentTables,
    ));
};
$tests['compound select window recursive limit source-token-fence rejects missing window'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceTokenFence(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, score AS metric FROM q UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables,
        $currentTables,
    ));
};
$tests['compound select window recursive limit source-token-fence rejects missing final offset'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceTokenFence(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2",
        $currentTables,
        $currentTables,
    ));
};
$tests['compound select window recursive limit source-token-fence dependency closure and non overlap'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->contains('no new support component needed', $plan['dependency_closure']);
    $t->contains('avoids accepted comma LIMIT', $plan['non_overlap']);
    $t->true(in_array('recursive-limit-current-source-token-source-token-fence', $plan['replanReasons'], true));
};

foreach (range(1, 72) as $case) {
    $tests['compound select window recursive limit source-token-fence generated token fence ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 5 + ($case % 5);
        $finalLimit = 3 + ($case % 4);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 120 + $case],
                ['option_id' => 2, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 106 + $case],
                ['option_id' => 3, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 94 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 12 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'queued_' . $case, 'autoload' => 'yes', 'score' => 111 + $case];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 7 FROM q WHERE id < 10 LIMIT {$recursiveLimit} OFFSET 2) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' UNION SELECT id, label, score AS metric FROM q ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceTokenFence($generatedSql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareSourceTokenFence($generatedSql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->same(64, strlen($plan['sourceWindow']['nextToken']));
        $t->true(isset($plan['recursiveLimit']['currentEmittedLabels'][0]));
    };
}

return $tests;

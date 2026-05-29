<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions189 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 120],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 103],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 81],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 19],
];
$nextOptions189 = [
    ...$currentOptions189,
    ['option_id' => 5, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 111],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 73],
];
$currentTables189 = ['wp_options' => $currentOptions189];
$nextTables189 = ['wp_options' => $nextOptions189];

$sql189 = <<<'SQL'
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

$summary189 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext189($sql189, $currentTables189, $nextTables189, $cursor);
$tests = [];

$tests['compound select window recursive limit next189 status'] = static function (TestRunner $t) use ($summary189): void {
    $plan = $summary189();
    $t->same('compound-select-window-recursive-limit-current-source-next189-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-compound-window-recursive-limit-next189',
        'sqlite-current-source-token-fence-next189',
        'sqlite-window-before-final-compound-limit-next189',
    ], $plan['dependencies']);
};
$tests['compound select window recursive limit next189 compound metadata'] = static function (TestRunner $t) use ($summary189): void {
    $compound = $summary189()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(5, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->same(['metric', 'id'], $compound['orderColumns']);
    $t->same([3, 3], [$compound['currentArms'], $compound['nextArms']]);
};
$tests['compound select window recursive limit next189 recursive limit trace'] = static function (TestRunner $t) use ($summary189): void {
    $recursive = $summary189()['recursiveLimit'];
    $t->same('q', $recursive['name']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(2, count($recursive['currentSkippedLabels']));
    $t->same(7, count($recursive['currentEmittedLabels']));
    $t->same([9, 9], [$recursive['currentTraceCount'], $recursive['nextTraceCount']]);
};
$tests['compound select window recursive limit next189 window terms'] = static function (TestRunner $t) use ($summary189): void {
    $windows = $summary189()['windows'];
    $t->same(['row_number', 'dense_rank'], $windows['functions']);
    $t->same([0, 1], array_column($windows['current'], 'partitionCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};
$tests['compound select window recursive limit next189 source window labels'] = static function (TestRunner $t) use ($summary189): void {
    $source = $summary189()['sourceWindow'];
    $t->same(64, strlen($source['currentToken']));
    $t->same(64, strlen($source['nextToken']));
    $t->same(false, $source['currentToken'] === $source['nextToken']);
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6'], array_slice($source['nextAdmittedLabels'], 0, 2));
    $t->true(in_array('theme_mods_next', $source['nextTruncatedLabels'], true));
};
$tests['compound select window recursive limit next189 cursor accepts current token'] = static function (TestRunner $t) use ($summary189): void {
    $first = $summary189();
    $second = $summary189($first['cursor']);
    $t->same($first['sourceWindow']['currentToken'], $second['sourceWindow']['currentToken']);
    $t->same($first['cursor']['nextOffset'], $second['cursor']['nextOffset']);
};
$tests['compound select window recursive limit next189 rejects stale cursor'] = static function (TestRunner $t) use ($summary189): void {
    $cursor = $summary189()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary189($cursor));
};
$tests['compound select window recursive limit next189 rejects missing recursive'] = static function (TestRunner $t) use ($currentTables189): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext189(
        'SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY option_id) AS metric FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2 OFFSET 0',
        $currentTables189,
        $currentTables189,
    ));
};
$tests['compound select window recursive limit next189 rejects missing window'] = static function (TestRunner $t) use ($currentTables189): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext189(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, score AS metric FROM q UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables189,
        $currentTables189,
    ));
};
$tests['compound select window recursive limit next189 rejects missing final offset'] = static function (TestRunner $t) use ($currentTables189): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext189(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY id) AS metric FROM q UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2",
        $currentTables189,
        $currentTables189,
    ));
};
$tests['compound select window recursive limit next189 dependency closure and non overlap'] = static function (TestRunner $t) use ($summary189): void {
    $plan = $summary189();
    $t->contains('no new support component needed', $plan['dependency_closure']);
    $t->contains('avoids accepted next186', $plan['non_overlap']);
    $t->true(in_array('recursive-limit-current-source-token-next189', $plan['replanReasons'], true));
};

foreach (range(1, 72) as $case) {
    $tests['compound select window recursive limit next189 generated token fence ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext189($generatedSql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext189($generatedSql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same($plan['sourceWindow']['currentToken'], $again['sourceWindow']['currentToken']);
        $t->same(64, strlen($plan['sourceWindow']['nextToken']));
        $t->true(isset($plan['recursiveLimit']['currentEmittedLabels'][0]));
    };
}

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions193 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 126],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 114],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 89],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 21],
];
$nextOptions193 = [
    ...$currentOptions193,
    ['option_id' => 5, 'option_name' => 'plugin_loaded', 'autoload' => 'yes', 'score' => 118],
    ['option_id' => 6, 'option_name' => 'theme_mods_next', 'autoload' => 'yes', 'score' => 84],
];
$currentTables193 = ['wp_options' => $currentOptions193];
$nextTables193 = ['wp_options' => $nextOptions193];

$sql193 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 140)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 8
      FROM q
     WHERE id < 10
     LIMIT 8 OFFSET 2
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
 LIMIT 6 OFFSET 2
SQL;

$summary193 = static fn (?array $cursor = null): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext193($sql193, $currentTables193, $nextTables193, $cursor);
$tests = [];

$tests['compound select window recursive limit next193 status'] = static function (TestRunner $t) use ($summary193): void {
    $plan = $summary193();
    $t->same('compound-select-window-recursive-limit-current-source-next193-ready', $plan['status']);
    $t->true(in_array('sqlite-current-source-recursive-window-signature-next193', $plan['dependencies'], true));
};
$tests['compound select window recursive limit next193 source signature'] = static function (TestRunner $t) use ($summary193): void {
    $source = $summary193()['currentSourceNext193'];
    $t->same(64, strlen($source['sourceSignature']));
    $t->same(64, strlen($source['nextSourceSignature']));
    $t->same(false, $source['sourceSignature'] === $source['nextSourceSignature']);
};
$tests['compound select window recursive limit next193 boundary admission'] = static function (TestRunner $t) use ($summary193): void {
    $source = $summary193()['currentSourceNext193'];
    $t->same('next-source-boundary-reprepare-required', $source['admission']);
    $t->same(false, $source['boundaryChanged']);
    $t->same(false, $source['sourceSignature'] === $source['nextSourceSignature']);
    $t->same(6, $source['currentAdmittedCount']);
    $t->same(6, $source['nextAdmittedCount']);
};
$tests['compound select window recursive limit next193 recursive counts'] = static function (TestRunner $t) use ($summary193): void {
    $source = $summary193()['currentSourceNext193'];
    $t->same(8, $source['currentRecursiveEmittedCount']);
    $t->same(8, $source['nextRecursiveEmittedCount']);
};
$tests['compound select window recursive limit next193 window functions'] = static function (TestRunner $t) use ($summary193): void {
    $source = $summary193()['currentSourceNext193'];
    $t->same(['row_number', 'dense_rank'], $source['windowFunctions']);
};
$tests['compound select window recursive limit next193 cursor accepts source signature'] = static function (TestRunner $t) use ($summary193): void {
    $first = $summary193();
    $second = $summary193($first['cursor']);
    $t->same($first['currentSourceNext193']['sourceSignature'], $second['currentSourceNext193']['sourceSignature']);
    $t->same($first['cursor']['currentSourceSignature'], $second['cursor']['currentSourceSignature']);
};
$tests['compound select window recursive limit next193 rejects stale source signature'] = static function (TestRunner $t) use ($summary193): void {
    $cursor = $summary193()['cursor'];
    $cursor['currentSourceSignature'] = str_repeat('f', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary193($cursor));
};
$tests['compound select window recursive limit next193 rejects stale row token'] = static function (TestRunner $t) use ($summary193): void {
    $cursor = $summary193()['cursor'];
    $cursor['currentToken'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $summary193($cursor));
};
$tests['compound select window recursive limit next193 non overlap'] = static function (TestRunner $t) use ($summary193): void {
    $plan = $summary193();
    $t->contains('separate current-source signature', $plan['non_overlap']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};
$tests['compound select window recursive limit next193 base rows match executor'] = static function (TestRunner $t) use ($sql193, $currentTables193, $summary193): void {
    $t->same(SQLiteSelectSql::execute($sql193, $currentTables193), $summary193()['currentRows']);
};
$tests['compound select window recursive limit next193 rejects missing recursive'] = static function (TestRunner $t) use ($currentTables193): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext193(
        'SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY option_id) AS metric FROM wp_options UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2 OFFSET 0',
        $currentTables193,
        $currentTables193,
    ));
};
$tests['compound select window recursive limit next193 rejects missing window'] = static function (TestRunner $t) use ($currentTables193): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext193(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label, score FROM q WHERE id < 4 LIMIT 2 OFFSET 1) SELECT id, label, score AS metric FROM q UNION SELECT option_id, option_name, score FROM wp_options ORDER BY metric LIMIT 2 OFFSET 0",
        $currentTables193,
        $currentTables193,
    ));
};

foreach (range(1, 76) as $case) {
    $tests['compound select window recursive limit next193 generated current source signature ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 6 + ($case % 4);
        $finalLimit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 130 + $case],
                ['option_id' => 2, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 114 + $case],
                ['option_id' => 3, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 96 + $case],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 20 + $case],
            ],
        ];
        $nextTables = $tables;
        $nextTables['wp_options'][] = ['option_id' => 5, 'option_name' => 'queued_' . $case, 'autoload' => 'yes', 'score' => 119 + $case];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (150 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 8 FROM q WHERE id < 11 LIMIT {$recursiveLimit} OFFSET 2) SELECT id, label, row_number() OVER (ORDER BY score DESC, id) AS metric FROM q UNION ALL SELECT option_id AS id, option_name AS label, dense_rank() OVER (PARTITION BY autoload ORDER BY score DESC, option_id) AS metric FROM wp_options WHERE autoload = 'yes' UNION SELECT id, label, score AS metric FROM q ORDER BY metric DESC, id LIMIT {$finalLimit} OFFSET 2";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext193($generatedSql, $tables, $nextTables);
        $again = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext193($generatedSql, $tables, $nextTables, $plan['cursor']);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($finalLimit, count($rows));
        $t->same($plan['currentSourceNext193']['sourceSignature'], $again['currentSourceNext193']['sourceSignature']);
        $t->same(64, strlen($plan['cursor']['currentSourceSignature']));
        $t->true($plan['currentSourceNext193']['currentRecursiveEmittedCount'] >= 1);
    };
}

return $tests;

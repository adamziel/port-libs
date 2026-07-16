<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 96],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 76],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 66],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 89],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 71],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 125)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 11
      FROM q
     WHERE id < 7
     ORDER BY 3 DESC
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       nth_value(label, 2) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS peer
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       ntile(3) OVER (ORDER BY score DESC, option_id) AS peer
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_id AS id,
       option_name AS label,
       lead(option_name, 2, option_name) OVER (ORDER BY option_id) AS peer
  FROM wp_options
 WHERE score >= 60
 ORDER BY peer, id
 LIMIT 6 OFFSET 2
SQL;

$summary = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareOrderedValueOffsetCompoundBoundary($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound select window recursive limit current source ordered-value-offset-boundary status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-window-recursive-limit-current-source-ordered-value-offset-boundary-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-ordered-limit-offset-ordered-value-offset-boundary',
        'sqlite-select-sql-compound-nth-value-ntile-lead-ordered-value-offset-boundary',
        'sqlite-select-sql-union-distinct-value-offset-boundary-ordered-value-offset-boundary',
        'sqlite-current-source-ordered-value-offset-boundary',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['peer', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasDistinctTail']);
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same(['home', 'rewrite_rules', 'siteurl', 'seed:2', 'seed:2:3', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([2, 3, 'rewrite_rules', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_column($rows, 'peer'));
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same(['plugin_alpha', 'home', 'theme_mods', 'rewrite_rules', 'home', 'siteurl'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3, 'plugin_alpha', 'rewrite_rules'], array_column($rows, 'peer'));
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary prelimit rowsets'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(11, count($plan['currentPreLimitRows']));
    $t->same(15, count($plan['nextPreLimitRows']));
    $t->same(['seed:2:3:4:5:6', 'siteurl', 'home', 'rewrite_rules'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 4));
    $t->same(['seed:2:3:4:5:6', 'siteurl', 'plugin_alpha', 'home'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 4));
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary window functions'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['nth_value', 'ntile', 'lead'], $windows['functions']);
    $t->same(['nth_value', 'ntile', 'lead'], $windows['valueOffsetFunctions']);
    $t->same([1, 2, 3], $windows['ntileBuckets']);
    $t->same([true, false, false], array_column($windows['current'], 'hasFrame'));
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary recursive ordered trace'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->true($recursive['orderedQueue']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary value offset tape'] = static function (TestRunner $t) use ($summary): void {
    $tape = $summary()['valueOffsetTape'];
    $t->same(['seed:2:3:4:5:6', 'siteurl', 'home', 'rewrite_rules'], array_slice(array_column($tape['current'], 'label'), 0, 4));
    $t->same(['seed:2:3:4:5:6', 'siteurl', 'plugin_alpha', 'home'], array_slice(array_column($tape['next'], 'label'), 0, 4));
    $t->same([1, 'plugin_alpha', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], $tape['changedPeerLabels']);
    $t->same(2, $tape['peerBoundary']['currentFirstPeer']);
    $t->same(1, $tape['peerBoundary']['nextFirstPeer']);
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary limit trace'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['limitTrace'];
    $t->same(['seed:2:3:4:5:6', 'siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6', 'siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5', 'home', 'rewrite_rules'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'plugin_alpha', 'theme_mods', 'rewrite_rules'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary boundary delta'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['boundary'];
    $t->same('home', $boundary['currentFirst']['label']);
    $t->same('plugin_alpha', $boundary['nextFirst']['label']);
    $t->same('seed:2:3:4', $boundary['currentLast']['label']);
    $t->same('siteurl', $boundary['nextLast']['label']);
    $t->contains('"label":"plugin_alpha"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"seed:2"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary replan reasons'] = static function (TestRunner $t) use ($summary): void {
    $reasons = $summary()['replanReasons'];
    $t->true(in_array('compound-nth-value-ntile-lead-window-offsets', $reasons, true));
    $t->true(in_array('limited-value-offset-rowset-changed', $reasons, true));
    $t->true(in_array('prelimit-value-offset-rowset-changed', $reasons, true));
    $t->true(in_array('value-offset-peer-boundary-changed', $reasons, true));
    $t->true(in_array('ordered-recursive-limit-offset-feeds-compound-arm', $reasons, true));
    $t->true(in_array('nth-value-ntile-lead-before-union-distinct', $reasons, true));
    $t->true(in_array('compound-tail-limit-offset-after-value-sort', $reasons, true));
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary rejects missing ordered recursive queue'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareOrderedValueOffsetCompoundBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 125) UNION ALL SELECT id + 1, label, score - 11 FROM q WHERE id < 7 LIMIT 5 OFFSET 1) SELECT id, label, nth_value(label, 2) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS peer FROM q UNION ALL SELECT option_id, option_name, ntile(3) OVER (ORDER BY score DESC) FROM wp_options UNION SELECT option_id, option_name, lead(option_name, 2, option_name) OVER (ORDER BY option_id) FROM wp_options ORDER BY peer LIMIT 6 OFFSET 2",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select window recursive limit current source ordered-value-offset-boundary rejects missing value offset windows'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareOrderedValueOffsetCompoundBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 125) UNION ALL SELECT id + 1, label, score - 11 FROM q WHERE id < 7 ORDER BY 3 DESC LIMIT 5 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS peer FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY option_id) FROM wp_options ORDER BY peer LIMIT 6 OFFSET 2",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit current source ordered-value-offset-boundary generated ordered value offset ' . $case] = static function (TestRunner $t) use ($case): void {
        $limit = 4 + ($case % 3);
        $offset = 1 + ($case % 2);
        $threshold = 60 + $case;
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 120 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => $threshold],
                ['option_id' => 4, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'score' => 20 + $case],
                ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (140 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 9 FROM q WHERE id < 7 ORDER BY 3 DESC LIMIT 5 OFFSET 1) SELECT id, label, nth_value(label, 2) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS peer FROM q UNION ALL SELECT option_id AS id, option_name AS label, ntile(3) OVER (ORDER BY score DESC, option_id) AS peer FROM wp_options WHERE autoload = 'yes' UNION SELECT option_id AS id, option_name AS label, lead(option_name, 2, option_name) OVER (ORDER BY option_id) AS peer FROM wp_options WHERE score >= {$threshold} ORDER BY peer, id LIMIT {$limit} OFFSET {$offset}";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareOrderedValueOffsetCompoundBoundary($generatedSql, $tables, $tables);
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same($limit, count($rows));
        $t->same(['nth_value', 'ntile', 'lead'], $plan['windows']['valueOffsetFunctions']);
        $t->same(['seed_' . $case], $plan['recursive']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursive']['currentEmittedLabels'][0]);
        $t->true(in_array(1, $plan['windows']['ntileBuckets'], true));
        $t->true(in_array('ordered-recursive-limit-offset-feeds-compound-arm', $plan['replanReasons'], true));
    };
}

return $tests;

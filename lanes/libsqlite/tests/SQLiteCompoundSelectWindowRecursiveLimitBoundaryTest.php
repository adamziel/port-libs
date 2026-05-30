<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptionsBoundary = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 95],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 80],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 60],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 30],
];
$nextOptionsBoundary = [
    ...$currentOptionsBoundary,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 88],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 70],
];
$currentTablesBoundary = ['wp_options' => $currentOptionsBoundary];
$nextTablesBoundary = ['wp_options' => $nextOptionsBoundary];

$sqlBoundary = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'seed', 120)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), score - 10
      FROM q
     WHERE id < 7
     LIMIT 4 OFFSET 1
)
SELECT id,
       label,
       first_value(label) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS peer
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       last_value(option_name) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS peer
  FROM wp_options
 WHERE autoload = 'yes'
UNION
SELECT option_id AS id,
       option_name AS label,
       lag(option_name, 1, option_name) OVER (ORDER BY option_id) AS peer
  FROM wp_options
 WHERE score >= 70
 ORDER BY peer, id
 LIMIT 6 OFFSET 1
SQL;

$summaryBoundary = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCompoundWindowRecursiveLimitBoundary($sqlBoundary, $currentTablesBoundary, $nextTablesBoundary);
$tests = [];

$tests['compound select window recursive limit boundary status dependencies'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $plan = $summaryBoundary();
    $t->same('compound-select-window-recursive-limit-current-source-next188-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-limit-offset-next188',
        'sqlite-select-sql-compound-first-last-value-window-next188',
        'sqlite-select-sql-union-distinct-peer-boundary-next188',
        'sqlite-select-sql-compound-tail-limit-offset-next188',
        'sqlite-current-source-next188',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit boundary compound metadata'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $compound = $summaryBoundary()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['peer', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasDistinctTail']);
};

$tests['compound select window recursive limit boundary current rows'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $rows = $summaryBoundary()['currentRows'];
    $t->same(['home', 'rewrite_rules', 'seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same(['rewrite_rules', 'rewrite_rules', 'seed:2', 'seed:2', 'seed:2:3', 'seed:2:3:4'], array_column($rows, 'peer'));
};

$tests['compound select window recursive limit boundary next rows'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $rows = $summaryBoundary()['nextRows'];
    $t->same(['siteurl', 'theme_mods', 'rewrite_rules', 'theme_mods', 'seed:2', 'seed:2:3'], array_column($rows, 'label'));
    $t->same(['plugin_alpha', 'plugin_alpha', 'rewrite_rules', 'rewrite_rules', 'seed:2', 'seed:2'], array_column($rows, 'peer'));
};

$tests['compound select window recursive limit boundary prelimit rowsets'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $plan = $summaryBoundary();
    $t->same(9, count($plan['currentPreLimitRows']));
    $t->same(12, count($plan['nextPreLimitRows']));
    $t->same(['siteurl', 'home', 'rewrite_rules'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 3));
    $t->same(['plugin_alpha', 'siteurl', 'theme_mods'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 3));
};

$tests['compound select window recursive limit boundary window endpoints'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $windows = $summaryBoundary()['windows'];
    $t->same(['first_value', 'last_value', 'lag'], $windows['functions']);
    $t->same(['first_value', 'last_value'], $windows['frameEndpointFunctions']);
    $t->same([true, true, false], array_column($windows['current'], 'hasFrame'));
    $t->same([0, 1, 0], array_column($windows['current'], 'partitionCount'));
};

$tests['compound select window recursive limit boundary recursive trace'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $recursive = $summaryBoundary()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit boundary endpoint tape'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $tape = $summaryBoundary()['frameEndpointTape'];
    $t->same(['siteurl', 'home', 'rewrite_rules', 'seed:2'], array_slice(array_column($tape['current'], 'label'), 0, 4));
    $t->same(['plugin_alpha', 'siteurl', 'theme_mods', 'rewrite_rules'], array_slice(array_column($tape['next'], 'label'), 0, 4));
    $t->same(['plugin_alpha', 'seed:2:3', 'seed:2:3:4'], $tape['changedPeerLabels']);
    $t->same('plugin_alpha', $tape['peerBoundary']['nextFirstPeer']);
    $t->same('seed:2', $tape['peerBoundary']['nextLastPeer']);
};

$tests['compound select window recursive limit boundary limit trace'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $trace = $summaryBoundary()['limitTrace'];
    $t->same(['siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['plugin_alpha'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['siteurl', 'home'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['seed:2:3:4', 'seed:2:3:4:5', 'siteurl', 'home', 'home'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit boundary boundary delta'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $boundary = $summaryBoundary()['boundary'];
    $t->same('home', $boundary['currentFirst']['label']);
    $t->same('siteurl', $boundary['nextFirst']['label']);
    $t->same('seed:2:3:4:5', $boundary['currentLast']['label']);
    $t->same('seed:2:3', $boundary['nextLast']['label']);
    $t->contains('"label":"theme_mods"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"seed:2:3:4:5"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit boundary replan reasons'] = static function (TestRunner $t) use ($summaryBoundary): void {
    $reasons = $summaryBoundary()['replanReasons'];
    $t->true(in_array('compound-first-last-value-window-frame-endpoints', $reasons, true));
    $t->true(in_array('limited-peer-rowset-changed', $reasons, true));
    $t->true(in_array('prelimit-peer-rowset-changed', $reasons, true));
    $t->true(in_array('frame-endpoint-peer-boundary-changed', $reasons, true));
    $t->true(in_array('recursive-limit-offset-feeds-compound-arm', $reasons, true));
    $t->true(in_array('first-last-value-before-union-distinct', $reasons, true));
    $t->true(in_array('compound-tail-limit-offset-after-peer-sort', $reasons, true));
};

$tests['compound select window recursive limit boundary rejects missing endpoint windows'] = static function (TestRunner $t) use ($currentTablesBoundary): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCompoundWindowRecursiveLimitBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 120) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 7 LIMIT 4 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS peer FROM q UNION ALL SELECT option_id, option_name, dense_rank() OVER (ORDER BY score DESC) FROM wp_options UNION SELECT option_id, option_name, lag(option_name, 1, option_name) OVER (ORDER BY option_id) FROM wp_options ORDER BY peer LIMIT 6 OFFSET 1",
        $currentTablesBoundary,
        $currentTablesBoundary,
    ));
};

$tests['compound select window recursive limit boundary rejects missing final limit'] = static function (TestRunner $t) use ($currentTablesBoundary): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCompoundWindowRecursiveLimitBoundary(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 120) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 7 LIMIT 4 OFFSET 1) SELECT id, label, first_value(label) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS peer FROM q UNION ALL SELECT option_id, option_name, last_value(option_name) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM wp_options UNION SELECT option_id, option_name, lag(option_name, 1, option_name) OVER (ORDER BY option_id) FROM wp_options ORDER BY peer",
        $currentTablesBoundary,
        $currentTablesBoundary,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit boundary generated endpoint boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'score' => 100 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'score' => 80 + $case],
                ['option_id' => 3, 'option_name' => 'rewrite_rules_' . $case, 'autoload' => 'yes', 'score' => 65 + $case],
                ['option_id' => 4, 'option_name' => 'cache_seed_' . $case, 'autoload' => 'no', 'score' => 25 + $case],
                ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'score' => 90 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed_{$case}', " . (130 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), score - 10 FROM q WHERE id < 7 LIMIT 4 OFFSET 1) SELECT id, label, first_value(label) OVER (ORDER BY score DESC ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING) AS peer FROM q UNION ALL SELECT option_id AS id, option_name AS label, last_value(option_name) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS peer FROM wp_options WHERE autoload = 'yes' UNION SELECT option_id AS id, option_name AS label, lag(option_name, 1, option_name) OVER (ORDER BY option_id) AS peer FROM wp_options WHERE score >= " . (80 + $case) . " ORDER BY peer, id LIMIT 6 OFFSET 1";
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareCompoundWindowRecursiveLimitBoundary($sql, $tables, $tables);
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(6, count($rows));
        $t->same(['first_value', 'last_value'], $plan['windows']['frameEndpointFunctions']);
        $t->same(['seed_' . $case], $plan['recursive']['currentSkippedLabels']);
        $t->same('seed_' . $case . ':2', $plan['recursive']['currentEmittedLabels'][0]);
        $t->true(in_array('plugin_' . $case, array_column($plan['currentPreLimitRows'], 'label'), true));
        $t->true(in_array('compound-first-last-value-window-frame-endpoints', $plan['replanReasons'], true));
    };
}

return $tests;

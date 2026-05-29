<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions191 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'score' => 96],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'score' => 76],
    ['option_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'score' => 66],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'autoload' => 'no', 'score' => 38],
];
$nextOptions191 = [
    ...$currentOptions191,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'score' => 89],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'score' => 71],
];
$currentTables191 = ['wp_options' => $currentOptions191];
$nextTables191 = ['wp_options' => $nextOptions191];

$sql191 = <<<'SQL'
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

$summary191 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext191($sql191, $currentTables191, $nextTables191);
$tests = [];

$tests['compound select window recursive limit current source next191 status dependencies'] = static function (TestRunner $t) use ($summary191): void {
    $plan = $summary191();
    $t->same('compound-select-window-recursive-limit-current-source-next191-ready', $plan['status']);
    $t->same([
        'sqlite-select-sql-recursive-ordered-limit-offset-next191',
        'sqlite-select-sql-compound-nth-value-ntile-lead-next191',
        'sqlite-select-sql-union-distinct-value-offset-boundary-next191',
        'sqlite-current-source-next191',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound select window recursive limit current source next191 compound metadata'] = static function (TestRunner $t) use ($summary191): void {
    $compound = $summary191()['compound'];
    $t->same(['UNION ALL', 'UNION'], $compound['operators']);
    $t->same(3, $compound['currentArms']);
    $t->same(3, $compound['nextArms']);
    $t->same(['peer', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->true($compound['hasUnionAllHead']);
    $t->true($compound['hasDistinctTail']);
};

$tests['compound select window recursive limit current source next191 current rows'] = static function (TestRunner $t) use ($summary191): void {
    $rows = $summary191()['currentRows'];
    $t->same(['home', 'rewrite_rules', 'siteurl', 'seed:2', 'seed:2:3', 'seed:2:3:4'], array_column($rows, 'label'));
    $t->same([2, 3, 'rewrite_rules', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], array_column($rows, 'peer'));
};

$tests['compound select window recursive limit current source next191 next rows'] = static function (TestRunner $t) use ($summary191): void {
    $rows = $summary191()['nextRows'];
    $t->same(['plugin_alpha', 'home', 'theme_mods', 'rewrite_rules', 'home', 'siteurl'], array_column($rows, 'label'));
    $t->same([1, 2, 2, 3, 'plugin_alpha', 'rewrite_rules'], array_column($rows, 'peer'));
};

$tests['compound select window recursive limit current source next191 prelimit rowsets'] = static function (TestRunner $t) use ($summary191): void {
    $plan = $summary191();
    $t->same(11, count($plan['currentPreLimitRows']));
    $t->same(15, count($plan['nextPreLimitRows']));
    $t->same(['seed:2:3:4:5:6', 'siteurl', 'home', 'rewrite_rules'], array_slice(array_column($plan['currentPreLimitRows'], 'label'), 0, 4));
    $t->same(['seed:2:3:4:5:6', 'siteurl', 'plugin_alpha', 'home'], array_slice(array_column($plan['nextPreLimitRows'], 'label'), 0, 4));
};

$tests['compound select window recursive limit current source next191 window functions'] = static function (TestRunner $t) use ($summary191): void {
    $windows = $summary191()['windows'];
    $t->same(['nth_value', 'ntile', 'lead'], $windows['functions']);
    $t->same(['nth_value', 'ntile', 'lead'], $windows['valueOffsetFunctions']);
    $t->same([1, 2, 3], $windows['ntileBuckets']);
    $t->same([true, false, false], array_column($windows['current'], 'hasFrame'));
};

$tests['compound select window recursive limit current source next191 recursive ordered trace'] = static function (TestRunner $t) use ($summary191): void {
    $recursive = $summary191()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->true($recursive['orderedQueue']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentFinalLimitRemaining']);
    $t->same(0, $recursive['currentFinalOffsetRemaining']);
};

$tests['compound select window recursive limit current source next191 value offset tape'] = static function (TestRunner $t) use ($summary191): void {
    $tape = $summary191()['valueOffsetTape'];
    $t->same(['seed:2:3:4:5:6', 'siteurl', 'home', 'rewrite_rules'], array_slice(array_column($tape['current'], 'label'), 0, 4));
    $t->same(['seed:2:3:4:5:6', 'siteurl', 'plugin_alpha', 'home'], array_slice(array_column($tape['next'], 'label'), 0, 4));
    $t->same([1, 'plugin_alpha', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5'], $tape['changedPeerLabels']);
    $t->same(2, $tape['peerBoundary']['currentFirstPeer']);
    $t->same(1, $tape['peerBoundary']['nextFirstPeer']);
};

$tests['compound select window recursive limit current source next191 limit trace'] = static function (TestRunner $t) use ($summary191): void {
    $trace = $summary191()['limitTrace'];
    $t->same(['seed:2:3:4:5:6', 'siteurl'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5:6', 'siteurl'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5', 'home', 'rewrite_rules'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'plugin_alpha', 'theme_mods', 'rewrite_rules'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit current source next191 boundary delta'] = static function (TestRunner $t) use ($summary191): void {
    $boundary = $summary191()['boundary'];
    $t->same('home', $boundary['currentFirst']['label']);
    $t->same('plugin_alpha', $boundary['nextFirst']['label']);
    $t->same('seed:2:3:4', $boundary['currentLast']['label']);
    $t->same('siteurl', $boundary['nextLast']['label']);
    $t->contains('"label":"plugin_alpha"', implode("\n", $boundary['gainedRows']));
    $t->contains('"label":"seed:2"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit current source next191 replan reasons'] = static function (TestRunner $t) use ($summary191): void {
    $reasons = $summary191()['replanReasons'];
    $t->true(in_array('compound-nth-value-ntile-lead-window-offsets', $reasons, true));
    $t->true(in_array('limited-value-offset-rowset-changed', $reasons, true));
    $t->true(in_array('prelimit-value-offset-rowset-changed', $reasons, true));
    $t->true(in_array('value-offset-peer-boundary-changed', $reasons, true));
    $t->true(in_array('ordered-recursive-limit-offset-feeds-compound-arm', $reasons, true));
    $t->true(in_array('nth-value-ntile-lead-before-union-distinct', $reasons, true));
    $t->true(in_array('compound-tail-limit-offset-after-value-sort', $reasons, true));
};

$tests['compound select window recursive limit current source next191 rejects missing ordered recursive queue'] = static function (TestRunner $t) use ($currentTables191): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext191(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 125) UNION ALL SELECT id + 1, label, score - 11 FROM q WHERE id < 7 LIMIT 5 OFFSET 1) SELECT id, label, nth_value(label, 2) OVER (ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS peer FROM q UNION ALL SELECT option_id, option_name, ntile(3) OVER (ORDER BY score DESC) FROM wp_options UNION SELECT option_id, option_name, lead(option_name, 2, option_name) OVER (ORDER BY option_id) FROM wp_options ORDER BY peer LIMIT 6 OFFSET 2",
        $currentTables191,
        $currentTables191,
    ));
};

$tests['compound select window recursive limit current source next191 rejects missing value offset windows'] = static function (TestRunner $t) use ($currentTables191): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext191(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'seed', 125) UNION ALL SELECT id + 1, label, score - 11 FROM q WHERE id < 7 ORDER BY 3 DESC LIMIT 5 OFFSET 1) SELECT id, label, row_number() OVER (ORDER BY score DESC) AS peer FROM q UNION ALL SELECT option_id, option_name, rank() OVER (ORDER BY score DESC) FROM wp_options UNION SELECT option_id, option_name, dense_rank() OVER (ORDER BY option_id) FROM wp_options ORDER BY peer LIMIT 6 OFFSET 2",
        $currentTables191,
        $currentTables191,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit current source next191 generated ordered value offset ' . $case] = static function (TestRunner $t) use ($case): void {
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
        $plan = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareNext191($generatedSql, $tables, $tables);
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

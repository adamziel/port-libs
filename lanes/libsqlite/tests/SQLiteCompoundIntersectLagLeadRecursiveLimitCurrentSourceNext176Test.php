<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions176 = [
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 90],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 80],
    ['option_id' => 4, 'option_name' => 'cache', 'autoload' => 'no', 'weight' => 70],
];
$nextOptions176 = [
    ...$currentOptions176,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 60],
];
$currentTables176 = ['wp_options' => $currentOptions176];
$nextTables176 = ['wp_options' => $nextOptions176];

$sql176 = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'siteurl', 100)
    UNION ALL
    SELECT id + 1,
           CASE id + 1
             WHEN 2 THEN 'home'
             WHEN 3 THEN 'blogname'
             WHEN 4 THEN 'cache'
             WHEN 5 THEN 'plugin_alpha'
             ELSE 'extra'
           END,
           score - 10
      FROM q
     WHERE id < 7
     ORDER BY 3 DESC
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       lag(label, 1, 'siteurl') OVER (ORDER BY score DESC, id) AS marker
  FROM q
INTERSECT
SELECT option_id AS id,
       option_name AS label,
       lag(option_name, 1, 'siteurl') OVER (ORDER BY weight DESC, option_id) AS marker
  FROM wp_options
 ORDER BY marker, id
 LIMIT 3 OFFSET 1
SQL;

$summary176 = static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareNext176($sql176, $currentTables176, $nextTables176);
$tests = [];

$tests['compound intersect lag lead recursive limit next176 status dependencies'] = static function (TestRunner $t) use ($summary176): void {
    $plan = $summary176();
    $t->same('compound-intersect-lag-lead-recursive-limit-current-source-next176-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-limit-offset-before-intersect-next176',
        'sqlite-window-lag-lead-before-compound-intersect-next176',
        'sqlite-current-source-limit-boundary-next176',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound intersect lag lead recursive limit next176 compound metadata'] = static function (TestRunner $t) use ($summary176): void {
    $compound = $summary176()['compound'];
    $t->same(['INTERSECT'], $compound['operators']);
    $t->same(2, $compound['armCount']);
    $t->same(['marker', 'id'], $compound['orderColumns']);
    $t->same(3, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->same(1, $compound['intersectArmIndex']);
};

$tests['compound intersect lag lead recursive limit next176 current rows'] = static function (TestRunner $t) use ($summary176): void {
    $rows = $summary176()['currentRows'];
    $t->same([3, 2], array_column($rows, 'id'));
    $t->same(['blogname', 'home'], array_column($rows, 'label'));
    $t->same(['home', 'siteurl'], array_column($rows, 'marker'));
};

$tests['compound intersect lag lead recursive limit next176 next rows'] = static function (TestRunner $t) use ($summary176): void {
    $rows = $summary176()['nextRows'];
    $t->same([5, 3, 2], array_column($rows, 'id'));
    $t->same(['plugin_alpha', 'blogname', 'home'], array_column($rows, 'label'));
    $t->same(['cache', 'home', 'siteurl'], array_column($rows, 'marker'));
};

$tests['compound intersect lag lead recursive limit next176 prelimit rows show intersect before final limit'] = static function (TestRunner $t) use ($summary176): void {
    $plan = $summary176();
    $t->same(['cache', 'blogname', 'home'], array_column($plan['currentPreLimitRows'], 'label'));
    $t->same(['cache', 'plugin_alpha', 'blogname', 'home'], array_column($plan['nextPreLimitRows'], 'label'));
    $t->same(['blogname', 'cache', 'home', 'siteurl'], $plan['intersect']['nextMarkers']);
};

$tests['compound intersect lag lead recursive limit next176 recursive trace'] = static function (TestRunner $t) use ($summary176): void {
    $recursive = $summary176()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['siteurl'], $recursive['currentSkippedLabels']);
    $t->same(['home', 'blogname', 'cache', 'plugin_alpha', 'extra'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['currentOffsetRemaining']);
};

$tests['compound intersect lag lead recursive limit next176 window metadata'] = static function (TestRunner $t) use ($summary176): void {
    $windows = $summary176()['windows'];
    $t->same(['lag'], $windows['functions']);
    $t->same(['marker', 'marker'], array_column($windows['current'], 'alias'));
    $t->same([3, 3], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound intersect lag lead recursive limit next176 lead diagnostics'] = static function (TestRunner $t) use ($summary176): void {
    $lead = $summary176()['leadDiagnostics'];
    $t->same(['blogname', 'cache', 'tail'], array_column($lead['current'], 'lead_marker'));
    $t->same(['blogname', 'cache', 'plugin_alpha', 'tail'], array_column($lead['next'], 'lead_marker'));
};

$tests['compound intersect lag lead recursive limit next176 limit trace'] = static function (TestRunner $t) use ($summary176): void {
    $trace = $summary176()['limitTrace'];
    $t->same(1, $trace['current']['offset']);
    $t->same(3, $trace['current']['limit']);
    $t->same(3, $trace['current']['preLimitCount']);
    $t->same(['cache'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(4, $trace['next']['preLimitCount']);
    $t->same(['cache'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
};

$tests['compound intersect lag lead recursive limit next176 current next boundary delta'] = static function (TestRunner $t) use ($summary176): void {
    $boundary = $summary176()['boundary'];
    $t->same('blogname', $boundary['currentFirst']['label']);
    $t->same('plugin_alpha', $boundary['nextFirst']['label']);
    $t->same('home', $boundary['currentLast']['label']);
    $t->same('home', $boundary['nextLast']['label']);
    $t->same(['plugin_alpha'], $boundary['gainedLabels']);
    $t->same([], $boundary['lostLabels']);
};

$tests['compound intersect lag lead recursive limit next176 changed signatures and reasons'] = static function (TestRunner $t) use ($summary176): void {
    $plan = $summary176();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->true(in_array('recursive-limit-offset-before-intersect', $plan['replanReasons'], true));
    $t->true(in_array('lag-lead-window-before-compound-intersect', $plan['replanReasons'], true));
    $t->true(in_array('compound-intersect-before-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('limited-intersect-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-intersect-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-offset-skipped-anchor', $plan['replanReasons'], true));
};

$tests['compound intersect lag lead recursive limit next176 rejects missing recursive'] = static function (TestRunner $t) use ($currentTables176): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareNext176(
        "SELECT option_id AS id, option_name AS label, lag(option_name, 1, 'siteurl') OVER (ORDER BY weight) AS marker FROM wp_options INTERSECT SELECT option_id, option_name, lag(option_name, 1, 'siteurl') OVER (ORDER BY weight) FROM wp_options ORDER BY marker LIMIT 2 OFFSET 1",
        $currentTables176,
        $currentTables176,
    ));
};

$tests['compound intersect lag lead recursive limit next176 rejects missing intersect'] = static function (TestRunner $t) use ($currentTables176): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareNext176(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 4 LIMIT 3 OFFSET 1) SELECT id, label, lag(label, 1, 'siteurl') OVER (ORDER BY score) AS marker FROM q UNION ALL SELECT option_id, option_name, lag(option_name, 1, 'siteurl') OVER (ORDER BY weight) FROM wp_options ORDER BY marker LIMIT 2 OFFSET 1",
        $currentTables176,
        $currentTables176,
    ));
};

$tests['compound intersect lag lead recursive limit next176 rejects missing recursive offset'] = static function (TestRunner $t) use ($currentTables176): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareNext176(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 4 LIMIT 3) SELECT id, label, lag(label, 1, 'siteurl') OVER (ORDER BY score) AS marker FROM q INTERSECT SELECT option_id, option_name, lag(option_name, 1, 'siteurl') OVER (ORDER BY weight) FROM wp_options ORDER BY marker LIMIT 2 OFFSET 1",
        $currentTables176,
        $currentTables176,
    ));
};

$tests['compound intersect lag lead recursive limit next176 rejects missing final offset'] = static function (TestRunner $t) use ($currentTables176): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareNext176(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 4 LIMIT 3 OFFSET 1) SELECT id, label, lag(label, 1, 'siteurl') OVER (ORDER BY score) AS marker FROM q INTERSECT SELECT option_id, option_name, lag(option_name, 1, 'siteurl') OVER (ORDER BY weight) FROM wp_options ORDER BY marker LIMIT 2",
        $currentTables176,
        $currentTables176,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound intersect lag lead recursive limit next176 generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 90 + $case],
                ['option_id' => 3, 'option_name' => 'blogname_' . $case, 'autoload' => 'yes', 'weight' => 80 + $case],
                ['option_id' => 4, 'option_name' => 'cache_' . $case, 'autoload' => 'no', 'weight' => 70 + $case],
                ['option_id' => 5, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 60 + $case],
            ],
        ];
        $limit = 4 + ($case % 2);
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'siteurl_{$case}', " . (100 + $case) . ") UNION ALL SELECT id + 1, CASE id + 1 WHEN 2 THEN 'home_{$case}' WHEN 3 THEN 'blogname_{$case}' WHEN 4 THEN 'cache_{$case}' WHEN 5 THEN 'plugin_{$case}' ELSE 'extra_{$case}' END, score - 10 FROM q WHERE id < 7 ORDER BY 3 DESC LIMIT {$limit} OFFSET 1) SELECT id, label, lag(label, 1, 'siteurl_{$case}') OVER (ORDER BY score DESC, id) AS marker FROM q INTERSECT SELECT option_id AS id, option_name AS label, lag(option_name, 1, 'siteurl_{$case}') OVER (ORDER BY weight DESC, option_id) AS marker FROM wp_options ORDER BY marker, id LIMIT 3 OFFSET 1";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same(3, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['marker']));
        $t->true($rows[0]['label'] !== 'home_' . $case);
        $t->contains((string) $case, implode('|', array_column($rows, 'label')));
    };
}

return $tests;

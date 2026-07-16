<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['setting_id' => 2, 'key_name' => 'home', 'load_policy' => 'yes', 'weight' => 90],
    ['setting_id' => 3, 'key_name' => 'site_title', 'load_policy' => 'yes', 'weight' => 80],
    ['setting_id' => 4, 'key_name' => 'cache', 'load_policy' => 'no', 'weight' => 70],
];
$nextOptions = [
    ...$currentOptions,
    ['setting_id' => 5, 'key_name' => 'module_alpha', 'load_policy' => 'yes', 'weight' => 60],
];
$currentTables = ['app_settings' => $currentOptions];
$nextTables = ['app_settings' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE q(id, label, score) AS (
    VALUES (1, 'base_url', 100)
    UNION ALL
    SELECT id + 1,
           CASE id + 1
             WHEN 2 THEN 'home'
             WHEN 3 THEN 'site_title'
             WHEN 4 THEN 'cache'
             WHEN 5 THEN 'module_alpha'
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
       lag(label, 1, 'base_url') OVER (ORDER BY score DESC, id) AS marker
  FROM q
INTERSECT
SELECT setting_id AS id,
       key_name AS label,
       lag(key_name, 1, 'base_url') OVER (ORDER BY weight DESC, setting_id) AS marker
  FROM app_settings
 ORDER BY marker, id
 LIMIT 3 OFFSET 1
SQL;

$summary = static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareIntersectLagLeadRecursiveLimit($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound intersect lag lead recursive limit recursive-limit status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-intersect-lag-lead-recursive-limit-current-source-recursive-limit-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-limit-offset-before-intersect-recursive-limit',
        'sqlite-window-lag-lead-before-compound-intersect-recursive-limit',
        'sqlite-current-source-limit-boundary-recursive-limit',
    ], $plan['dependencies']);
    $t->contains('no new support component needed', $plan['dependency_closure']);
};

$tests['compound intersect lag lead recursive limit recursive-limit compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['INTERSECT'], $compound['operators']);
    $t->same(2, $compound['armCount']);
    $t->same(['marker', 'id'], $compound['orderColumns']);
    $t->same(3, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->same(1, $compound['intersectArmIndex']);
};

$tests['compound intersect lag lead recursive limit recursive-limit current rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([3, 4], array_column($rows, 'id'));
    $t->same(['site_title', 'cache'], array_column($rows, 'label'));
    $t->same(['home', 'site_title'], array_column($rows, 'marker'));
};

$tests['compound intersect lag lead recursive limit recursive-limit next rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([5, 3, 4], array_column($rows, 'id'));
    $t->same(['module_alpha', 'site_title', 'cache'], array_column($rows, 'label'));
    $t->same(['cache', 'home', 'site_title'], array_column($rows, 'marker'));
};

$tests['compound intersect lag lead recursive limit recursive-limit prelimit rows show intersect before final limit'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same(['home', 'site_title', 'cache'], array_column($plan['currentPreLimitRows'], 'label'));
    $t->same(['home', 'module_alpha', 'site_title', 'cache'], array_column($plan['nextPreLimitRows'], 'label'));
    $t->same(['base_url', 'cache', 'home', 'site_title'], $plan['intersect']['nextMarkers']);
};

$tests['compound intersect lag lead recursive limit recursive-limit recursive trace'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'score'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['base_url'], $recursive['currentSkippedLabels']);
    $t->same(['home', 'site_title', 'cache', 'module_alpha', 'extra'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['currentOffsetRemaining']);
};

$tests['compound intersect lag lead recursive limit recursive-limit window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows'];
    $t->same(['lag'], $windows['functions']);
    $t->same(['marker', 'marker'], array_column($windows['current'], 'alias'));
    $t->same([3, 3], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound intersect lag lead recursive limit recursive-limit lead diagnostics'] = static function (TestRunner $t) use ($summary): void {
    $lead = $summary()['leadDiagnostics'];
    $t->same(['site_title', 'cache', 'tail'], array_column($lead['current'], 'lead_marker'));
    $t->same(['site_title', 'cache', 'module_alpha', 'tail'], array_column($lead['next'], 'lead_marker'));
};

$tests['compound intersect lag lead recursive limit recursive-limit limit trace'] = static function (TestRunner $t) use ($summary): void {
    $trace = $summary()['limitTrace'];
    $t->same(1, $trace['current']['offset']);
    $t->same(3, $trace['current']['limit']);
    $t->same(3, $trace['current']['preLimitCount']);
    $t->same(['home'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same([], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(4, $trace['next']['preLimitCount']);
    $t->same(['home'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
};

$tests['compound intersect lag lead recursive limit recursive-limit current next boundary delta'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['boundary'];
    $t->same('site_title', $boundary['currentFirst']['label']);
    $t->same('module_alpha', $boundary['nextFirst']['label']);
    $t->same('cache', $boundary['currentLast']['label']);
    $t->same('cache', $boundary['nextLast']['label']);
    $t->same(['module_alpha'], $boundary['gainedLabels']);
    $t->same([], $boundary['lostLabels']);
};

$tests['compound intersect lag lead recursive limit recursive-limit changed signatures and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"module_alpha"', $changed);
    $t->true(in_array('recursive-limit-offset-before-intersect', $plan['replanReasons'], true));
    $t->true(in_array('lag-lead-window-before-compound-intersect', $plan['replanReasons'], true));
    $t->true(in_array('compound-intersect-before-final-limit', $plan['replanReasons'], true));
    $t->true(in_array('limited-intersect-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-intersect-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-offset-skipped-anchor', $plan['replanReasons'], true));
};

$tests['compound intersect lag lead recursive limit recursive-limit rejects missing recursive'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareIntersectLagLeadRecursiveLimit(
        "SELECT setting_id AS id, key_name AS label, lag(key_name, 1, 'base_url') OVER (ORDER BY weight) AS marker FROM app_settings INTERSECT SELECT setting_id, key_name, lag(key_name, 1, 'base_url') OVER (ORDER BY weight) FROM app_settings ORDER BY marker LIMIT 2 OFFSET 1",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound intersect lag lead recursive limit recursive-limit rejects missing intersect'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareIntersectLagLeadRecursiveLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'base_url', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 4 LIMIT 3 OFFSET 1) SELECT id, label, lag(label, 1, 'base_url') OVER (ORDER BY score) AS marker FROM q UNION ALL SELECT setting_id, key_name, lag(key_name, 1, 'base_url') OVER (ORDER BY weight) FROM app_settings ORDER BY marker LIMIT 2 OFFSET 1",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound intersect lag lead recursive limit recursive-limit rejects missing recursive offset'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareIntersectLagLeadRecursiveLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'base_url', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 4 LIMIT 3) SELECT id, label, lag(label, 1, 'base_url') OVER (ORDER BY score) AS marker FROM q INTERSECT SELECT setting_id, key_name, lag(key_name, 1, 'base_url') OVER (ORDER BY weight) FROM app_settings ORDER BY marker LIMIT 2 OFFSET 1",
        $currentTables,
        $currentTables,
    ));
};

$tests['compound intersect lag lead recursive limit recursive-limit rejects missing final offset'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceNextPlan::compareIntersectLagLeadRecursiveLimit(
        "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'base_url', 100) UNION ALL SELECT id + 1, label, score - 10 FROM q WHERE id < 4 LIMIT 3 OFFSET 1) SELECT id, label, lag(label, 1, 'base_url') OVER (ORDER BY score) AS marker FROM q INTERSECT SELECT setting_id, key_name, lag(key_name, 1, 'base_url') OVER (ORDER BY weight) FROM app_settings ORDER BY marker LIMIT 2",
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 50) as $case) {
    $tests['compound intersect lag lead recursive limit recursive-limit generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'app_settings' => [
                ['setting_id' => 2, 'key_name' => 'home_' . $case, 'load_policy' => 'yes', 'weight' => 90 + $case],
                ['setting_id' => 3, 'key_name' => 'site_title_' . $case, 'load_policy' => 'yes', 'weight' => 80 + $case],
                ['setting_id' => 4, 'key_name' => 'cache_' . $case, 'load_policy' => 'no', 'weight' => 70 + $case],
                ['setting_id' => 5, 'key_name' => 'plugin_' . $case, 'load_policy' => 'yes', 'weight' => 60 + $case],
            ],
        ];
        $limit = 4 + ($case % 2);
        $generatedSql = "WITH RECURSIVE q(id, label, score) AS (VALUES (1, 'base_url_{$case}', " . (100 + $case) . ") UNION ALL SELECT id + 1, CASE id + 1 WHEN 2 THEN 'home_{$case}' WHEN 3 THEN 'site_title_{$case}' WHEN 4 THEN 'cache_{$case}' WHEN 5 THEN 'plugin_{$case}' ELSE 'extra_{$case}' END, score - 10 FROM q WHERE id < 7 ORDER BY 3 DESC LIMIT {$limit} OFFSET 1) SELECT id, label, lag(label, 1, 'base_url_{$case}') OVER (ORDER BY score DESC, id) AS marker FROM q INTERSECT SELECT setting_id AS id, key_name AS label, lag(key_name, 1, 'base_url_{$case}') OVER (ORDER BY weight DESC, setting_id) AS marker FROM app_settings ORDER BY marker, id LIMIT 3 OFFSET 1";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same(3, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['marker']));
        $t->true($rows[0]['label'] !== 'home_' . $case);
        $t->contains((string) $case, implode('|', array_column($rows, 'label')));
    };
}

return $tests;

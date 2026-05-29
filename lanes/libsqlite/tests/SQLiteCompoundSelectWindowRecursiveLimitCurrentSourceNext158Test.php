<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions158 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 18],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 16],
    ['option_id' => 3, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 14],
];
$nextOptions158 = [
    ...$currentOptions158,
    ['option_id' => 4, 'option_name' => 'plugin_alpha', 'autoload' => 'yes', 'weight' => 25],
    ['option_id' => 5, 'option_name' => 'plugin_beta', 'autoload' => 'no', 'weight' => 21],
];
$currentTables158 = ['wp_options' => $currentOptions158];
$nextTables158 = ['wp_options' => $nextOptions158];

$sql158 = <<<'SQL'
WITH RECURSIVE option_queue(id, label, weight) AS (
    VALUES (1, 'seed', 20)
    UNION ALL
    SELECT id + 1, 'seed:' || (id + 1), weight - 2
      FROM option_queue
     WHERE id < 8
     LIMIT 5 OFFSET 1
)
SELECT id,
       label,
       sum(weight) OVER (
           ORDER BY id
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS score
  FROM option_queue
UNION
SELECT option_id AS id,
       option_name AS label,
       first_value(weight) OVER (
           PARTITION BY autoload
           ORDER BY weight DESC, option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS score
  FROM wp_options
 ORDER BY score DESC, id
 LIMIT 4 OFFSET 1
SQL;

$summary158 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowRecursiveLimitOffset($sql158, $currentTables158, $nextTables158);
$tests = [];

$tests['compound select window recursive limit current source window-recursive-limit-offset status dependencies'] = static function (TestRunner $t) use ($summary158): void {
    $plan = $summary158();
    $t->same('compound-select-window-recursive-limit-current-source-window-recursive-limit-offset-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-limit-offset-queue',
        'sqlite-window-arm-before-compound-select',
        'sqlite-compound-select-final-limit-offset',
        'sqlite-current-source-window-recursive-limit-offset',
    ], $plan['dependencies']);
};

$tests['compound select window recursive limit current source window-recursive-limit-offset compound metadata'] = static function (TestRunner $t) use ($summary158): void {
    $compound = $summary158()['compound'];
    $t->same(['UNION'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['score', 'id'], $compound['orderColumns']);
    $t->same(4, $compound['limit']);
    $t->same(1, $compound['offset']);
};

$tests['compound select window recursive limit current source window-recursive-limit-offset current rows'] = static function (TestRunner $t) use ($summary158): void {
    $rows = $summary158()['currentRows'];
    $t->same([3, 4, 5, 1], array_column($rows, 'id'));
    $t->same(['seed:3', 'seed:4', 'seed:5', 'siteurl'], array_column($rows, 'label'));
    $t->same([30, 26, 22, 18], array_column($rows, 'score'));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset next rows'] = static function (TestRunner $t) use ($summary158): void {
    $rows = $summary158()['nextRows'];
    $t->same([3, 4, 4, 5], array_column($rows, 'id'));
    $t->same(['seed:3', 'seed:4', 'plugin_alpha', 'seed:5'], array_column($rows, 'label'));
    $t->same([30, 26, 25, 22], array_column($rows, 'score'));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset prelimit boundary'] = static function (TestRunner $t) use ($summary158): void {
    $plan = $summary158();
    $t->same(['seed:2', 'seed:3', 'seed:4', 'seed:5', 'siteurl', 'home', 'active_plugins', 'seed:6'], array_column($plan['currentPreLimitRows'], 'label'));
    $t->same(['seed:2', 'seed:3', 'seed:4', 'plugin_alpha', 'seed:5', 'plugin_beta', 'siteurl', 'home', 'active_plugins', 'seed:6'], array_column($plan['nextPreLimitRows'], 'label'));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset recursive limit offset trace'] = static function (TestRunner $t) use ($summary158): void {
    $recursive = $summary158()['recursive'];
    $t->same('option_queue', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same([2, 3, 4, 5, 6], array_column($recursive['currentRows'], 'id'));
    $t->same(6, $recursive['currentTraceCount']);
    $t->same([false, true, true, true, true, true], $recursive['currentEmitted']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['currentOffsetRemaining']);
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset window metadata'] = static function (TestRunner $t) use ($summary158): void {
    $windows = $summary158()['windows']['current'];
    $t->same(['sum', 'first_value'], array_column($windows, 'function'));
    $t->same(['score', 'score'], array_column($windows, 'alias'));
    $t->same([0, 1], array_column($windows, 'partitionCount'));
    $t->same([1, 2], array_column($windows, 'orderCount'));
    $t->same(['ROWS', 'ROWS'], array_column($windows, 'frameUnit'));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset limit trace'] = static function (TestRunner $t) use ($summary158): void {
    $trace = $summary158()['limitTrace'];
    $t->same(8, $trace['current']['preLimitCount']);
    $t->same(10, $trace['next']['preLimitCount']);
    $t->same(['seed:2'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['home', 'active_plugins', 'seed:6'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['plugin_beta', 'siteurl', 'home', 'active_plugins', 'seed:6'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset boundary changes'] = static function (TestRunner $t) use ($summary158): void {
    $boundary = $summary158()['boundary'];
    $t->same('seed:3', $boundary['currentFirst']['label']);
    $t->same('seed:3', $boundary['nextFirst']['label']);
    $t->same('siteurl', $boundary['currentLast']['label']);
    $t->same('seed:5', $boundary['nextLast']['label']);
    $t->same(['plugin_alpha'], $boundary['newAdmittedLabels']);
    $t->true(in_array('plugin_beta', $boundary['truncatedLabelsChanged'], true));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset changed signatures and reasons'] = static function (TestRunner $t) use ($summary158): void {
    $plan = $summary158();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"plugin_alpha"', $changed);
    $t->contains('"label":"siteurl"', $changed);
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-cte-offset-skipped-anchor', $plan['replanReasons'], true));
    $t->true(in_array('window-before-compound-select', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-limit-offset', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset rejects non recursive'] = static function (TestRunner $t) use ($currentTables158): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowRecursiveLimitOffset(
        'SELECT option_id AS id, option_name AS label FROM wp_options UNION SELECT option_id, option_name FROM wp_options LIMIT 2 OFFSET 1',
        $currentTables158,
        $currentTables158,
    ));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset rejects missing window'] = static function (TestRunner $t) use ($currentTables158): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowRecursiveLimitOffset(
        "WITH RECURSIVE option_queue(id, label, weight) AS (VALUES (1, 'seed', 20) UNION ALL SELECT id + 1, label, weight - 1 FROM option_queue WHERE id < 3 LIMIT 2 OFFSET 1) SELECT id, label, weight AS score FROM option_queue UNION SELECT option_id, option_name, weight FROM wp_options ORDER BY score LIMIT 2 OFFSET 1",
        $currentTables158,
        $currentTables158,
    ));
};

$tests['compound select window recursive limit current source window-recursive-limit-offset rejects missing final limit'] = static function (TestRunner $t) use ($currentTables158): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareWindowRecursiveLimitOffset(
        "WITH RECURSIVE option_queue(id, label, weight) AS (VALUES (1, 'seed', 20) UNION ALL SELECT id + 1, label, weight - 1 FROM option_queue WHERE id < 3 LIMIT 2 OFFSET 1) SELECT id, label, sum(weight) OVER (ORDER BY id) AS score FROM option_queue UNION SELECT option_id, option_name, weight FROM wp_options ORDER BY score",
        $currentTables158,
        $currentTables158,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit current source window-recursive-limit-offset generated limit offset boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $cteLimit = 3 + ($case % 4);
        $finalLimit = 2 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 20, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 30 + $case],
                ['option_id' => 21, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 28 + $case],
                ['option_id' => 22, 'option_name' => 'transient_' . $case, 'autoload' => 'no', 'weight' => 24 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE option_queue(id, label, weight) AS (VALUES (1, 'seed', {$case}) UNION ALL SELECT id + 1, label || ':' || (id + 1), weight + 2 FROM option_queue WHERE id < 9 LIMIT {$cteLimit} OFFSET 1) SELECT id, label, sum(weight) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS score FROM option_queue UNION SELECT option_id AS id, option_name AS label, first_value(weight) OVER (PARTITION BY autoload ORDER BY weight DESC, option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS score FROM wp_options ORDER BY score DESC, id LIMIT {$finalLimit} OFFSET 1";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same($finalLimit, count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['score']));
        $t->true($rows[0]['score'] >= $rows[count($rows) - 1]['score']);
    };
}

return $tests;

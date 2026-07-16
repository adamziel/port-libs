<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions172 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 26],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => 20],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 35],
    ['option_id' => 5, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 18],
];
$nextOptions172 = [
    ...$currentOptions172,
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 34],
    ['option_id' => 7, 'option_name' => 'plugin_queue', 'autoload' => 'yes', 'weight' => 17],
];
$currentTables172 = ['wp_options' => $currentOptions172];
$nextTables172 = ['wp_options' => $nextOptions172];

$sql172 = <<<'SQL'
WITH RECURSIVE staged(id, label, weight) AS (
    VALUES (1, 'seed', 40)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 4
      FROM staged
     WHERE id < 8
     ORDER BY weight DESC
     LIMIT 5
)
SELECT id,
       label,
       lead(label, 1, 'tail') OVER (ORDER BY weight DESC, id) AS window_label,
       cume_dist() OVER (ORDER BY weight DESC, id) AS window_rank
  FROM staged
UNION
SELECT option_id AS id,
       option_name AS label,
       lead(option_name, 1, 'tail') OVER (ORDER BY weight DESC, option_id) AS window_label,
       cume_dist() OVER (ORDER BY weight DESC, option_id) AS window_rank
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY window_rank DESC, id
 LIMIT 6 OFFSET 2
SQL;

$summary172 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareDistinctUnionSourceClassLimit($sql172, $currentTables172, $nextTables172);
$tests = [];

$tests['compound select window recursive limit distinct-union-source-class-limit status dependencies'] = static function (TestRunner $t) use ($summary172): void {
    $plan = $summary172();
    $t->same('compound-select-window-recursive-limit-current-source-distinct-union-source-class-limit-ready', $plan['status']);
    $t->same([
        'sqlite-compound-distinct-union-window-distinct-union-source-class-limit',
        'sqlite-recursive-cte-limit-exhaustion-before-compound-window-distinct-union-source-class-limit',
        'sqlite-current-source-final-limit-boundary-distinct-union-source-class-limit',
    ], $plan['dependencies']);
    $t->true(str_contains($plan['dependency_closure'], 'no new support component needed'));
};

$tests['compound select window recursive limit distinct-union-source-class-limit compound metadata'] = static function (TestRunner $t) use ($summary172): void {
    $compound = $summary172()['compound'];
    $t->same(['UNION'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['window_rank', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(2, $compound['offset']);
    $t->true($compound['distinctUnion']);
};

$tests['compound select window recursive limit distinct-union-source-class-limit window metadata'] = static function (TestRunner $t) use ($summary172): void {
    $windows = $summary172()['windows'];
    $t->same(['lead', 'cume_dist'], $windows['functions']);
    $t->same(['window_label', 'window_rank', 'window_label', 'window_rank'], array_column($windows['current'], 'alias'));
    $t->same([3, 0, 3, 0], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 2, 2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit distinct-union-source-class-limit recursive trace'] = static function (TestRunner $t) use ($summary172): void {
    $recursive = $summary172()['recursive'];
    $t->same('staged', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(5, $recursive['currentTraceCount']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same([1, 2, 3, 4, 5], array_column($recursive['currentRows'], 'id'));
};

$tests['compound select window recursive limit distinct-union-source-class-limit current rows'] = static function (TestRunner $t) use ($summary172): void {
    $rows = $summary172()['currentRows'];
    $t->same([4, 3, 3, 2, 2, 1], array_column($rows, 'id'));
    $t->same(['seed:2:3:4', 'blogname', 'seed:2:3', 'home', 'seed:2', 'siteurl'], array_column($rows, 'label'));
    $t->same(['seed:2:3:4:5', 'theme_mods', 'seed:2:3:4', 'blogname', 'seed:2:3', 'home'], array_column($rows, 'window_label'));
    $t->same([0.8, 0.75, 0.6, 0.5, 0.4, 0.25], array_column($rows, 'window_rank'));
};

$tests['compound select window recursive limit distinct-union-source-class-limit next rows'] = static function (TestRunner $t) use ($summary172): void {
    $rows = $summary172()['nextRows'];
    $t->same([5, 4, 3, 3, 2, 2], array_column($rows, 'id'));
    $t->same(['theme_mods', 'seed:2:3:4', 'blogname', 'seed:2:3', 'home', 'seed:2'], array_column($rows, 'label'));
    $t->same(['plugin_queue', 'seed:2:3:4:5', 'theme_mods', 'seed:2:3:4', 'blogname', 'seed:2:3'], array_column($rows, 'window_label'));
    $t->same([0.8333333333333334, 0.8, 0.6666666666666666, 0.6, 0.5, 0.4], array_column($rows, 'window_rank'));
};

$tests['compound select window recursive limit distinct-union-source-class-limit prelimit rows expose final boundary'] = static function (TestRunner $t) use ($summary172): void {
    $trace = $summary172()['limitTrace'];
    $t->same(9, $trace['current']['preLimitCount']);
    $t->same(11, $trace['next']['preLimitCount']);
    $t->same(['seed:2:3:4:5', 'theme_mods'], array_column($trace['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5', 'plugin_queue'], array_column($trace['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed'], array_column($trace['current']['truncatedAfterLimit'], 'label'));
    $t->same(['siteurl', 'seed', 'rewrite_rules'], array_column($trace['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit distinct-union-source-class-limit source classes stay mixed'] = static function (TestRunner $t) use ($summary172): void {
    $classes = $summary172()['sourceClasses'];
    $t->same(['recursive' => 3, 'table' => 3], $classes['current']);
    $t->same(['recursive' => 3, 'table' => 3], $classes['next']);
};

$tests['compound select window recursive limit distinct-union-source-class-limit boundary delta'] = static function (TestRunner $t) use ($summary172): void {
    $boundary = $summary172()['boundary'];
    $t->same('seed:2:3:4', $boundary['currentFirst']['label']);
    $t->same('theme_mods', $boundary['nextFirst']['label']);
    $t->contains('"label":"theme_mods"', implode("\n", $boundary['gainedRows']));
    $t->contains('"window_label":"home"', implode("\n", $boundary['lostRows']));
};

$tests['compound select window recursive limit distinct-union-source-class-limit changed reasons'] = static function (TestRunner $t) use ($summary172): void {
    $plan = $summary172();
    $t->true(in_array('distinct-union-after-window-arm-values', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-exhausted-before-final-compound-limit', $plan['replanReasons'], true));
    $t->true(in_array('limited-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('prelimit-compound-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-limit-exhausted', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit distinct-union-source-class-limit rejects union all only'] = static function (TestRunner $t) use ($currentTables172): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareDistinctUnionSourceClassLimit(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 40) UNION ALL SELECT id + 1, label, weight - 4 FROM staged WHERE id < 8 LIMIT 5) SELECT id, label, lead(label, 1, 'tail') OVER (ORDER BY weight) AS window_label, cume_dist() OVER (ORDER BY weight) AS window_rank FROM staged UNION ALL SELECT option_id, option_name, lead(option_name, 1, 'tail') OVER (ORDER BY weight), cume_dist() OVER (ORDER BY weight) FROM wp_options ORDER BY window_rank LIMIT 2 OFFSET 1",
        $currentTables172,
        $currentTables172,
    ));
};

$tests['compound select window recursive limit distinct-union-source-class-limit rejects missing final offset'] = static function (TestRunner $t) use ($currentTables172): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareDistinctUnionSourceClassLimit(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 40) UNION ALL SELECT id + 1, label, weight - 4 FROM staged WHERE id < 8 LIMIT 5) SELECT id, label, lead(label, 1, 'tail') OVER (ORDER BY weight) AS window_label, cume_dist() OVER (ORDER BY weight) AS window_rank FROM staged UNION SELECT option_id, option_name, lead(option_name, 1, 'tail') OVER (ORDER BY weight), cume_dist() OVER (ORDER BY weight) FROM wp_options ORDER BY window_rank LIMIT 2",
        $currentTables172,
        $currentTables172,
    ));
};

$tests['compound select window recursive limit distinct-union-source-class-limit rejects missing cume dist'] = static function (TestRunner $t) use ($currentTables172): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareDistinctUnionSourceClassLimit(
        "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed', 40) UNION ALL SELECT id + 1, label, weight - 4 FROM staged WHERE id < 8 LIMIT 5) SELECT id, label, lead(label, 1, 'tail') OVER (ORDER BY weight) AS window_label, row_number() OVER (ORDER BY weight) AS window_rank FROM staged UNION SELECT option_id, option_name, lead(option_name, 1, 'tail') OVER (ORDER BY weight), row_number() OVER (ORDER BY weight) FROM wp_options ORDER BY window_rank LIMIT 2 OFFSET 1",
        $currentTables172,
        $currentTables172,
    ));
};

foreach (range(1, 52) as $case) {
    $tests['compound select window recursive limit distinct-union-source-class-limit generated boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $recursiveLimit = 4 + ($case % 3);
        $limit = 3 + ($case % 4);
        $offset = 1 + ($case % 2);
        $tables = [
            'wp_options' => [
                ['option_id' => 20, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 50 + $case],
                ['option_id' => 21, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 44 + $case],
                ['option_id' => 22, 'option_name' => 'skip_' . $case, 'autoload' => 'no', 'weight' => 99 + $case],
                ['option_id' => 23, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 36 + $case],
            ],
        ];
        $generatedSql = "WITH RECURSIVE staged(id, label, weight) AS (VALUES (1, 'seed_{$case}', " . (60 + $case) . ") UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 4 FROM staged WHERE id < 9 ORDER BY weight DESC LIMIT {$recursiveLimit}) SELECT id, label, lead(label, 1, 'tail') OVER (ORDER BY weight DESC, id) AS window_label, cume_dist() OVER (ORDER BY weight DESC, id) AS window_rank FROM staged UNION SELECT option_id AS id, option_name AS label, lead(option_name, 1, 'tail') OVER (ORDER BY weight DESC, option_id) AS window_label, cume_dist() OVER (ORDER BY weight DESC, option_id) AS window_rank FROM wp_options WHERE autoload = 'yes' ORDER BY window_rank DESC, id LIMIT {$limit} OFFSET {$offset}";
        $rows = SQLiteSelectSql::execute($generatedSql, $tables);

        $t->same(min($limit, $recursiveLimit + 3 - $offset), count($rows));
        $t->true(isset($rows[0]['id'], $rows[0]['label'], $rows[0]['window_label'], $rows[0]['window_rank']));
        $t->true($rows[0]['window_rank'] >= $rows[count($rows) - 1]['window_rank']);
        $t->true(in_array('tail', array_column($rows, 'window_label'), true) || $offset > 0);
    };
}

return $tests;

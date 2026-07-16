<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentTables169 = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 10],
        ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 20],
        ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'no', 'weight' => 18],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'weight' => 14],
    ],
];
$nextTables169 = [
    'wp_options' => [
        ...$currentTables169['wp_options'],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'weight' => 30],
        ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'weight' => 9],
    ],
];

$sql169 = <<<'SQL'
WITH RECURSIVE q(id, label, weight) AS (
    VALUES (1, 'seed', 36)
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1), weight - 5
      FROM q
     WHERE id < 7
     ORDER BY 1
     LIMIT 1, 5
)
SELECT id,
       label,
       ntile(2) OVER (ORDER BY weight DESC, id) AS bucket
  FROM q
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       ntile(2) OVER (ORDER BY weight DESC, option_id) AS bucket
  FROM wp_options
 WHERE autoload = 'yes'
 ORDER BY bucket, id
 LIMIT 6 OFFSET 1
SQL;

$summary169 = static fn (): array => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveOrderCommaLimitNtile($sql169, $currentTables169, $nextTables169);
$tests = [];

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile status dependencies'] = static function (TestRunner $t) use ($summary169): void {
    $plan = $summary169();
    $t->same('compound-select-window-recursive-limit-current-source-recursive-order-comma-limit-ntile-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-order-limit-comma-recursive-order-comma-limit-ntile',
        'sqlite-select-sql-window-ntile-before-compound-recursive-order-comma-limit-ntile',
        'sqlite-select-sql-compound-final-limit-offset-recursive-order-comma-limit-ntile',
        'sqlite-current-source-recursive-order-comma-limit-ntile',
    ], $plan['dependencies']);
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile compound metadata'] = static function (TestRunner $t) use ($summary169): void {
    $compound = $summary169()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['bucket', 'id'], $compound['orderColumns']);
    $t->same(6, $compound['limit']);
    $t->same(1, $compound['offset']);
    $t->true($compound['usesFinalLimitOffset']);
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile current rows'] = static function (TestRunner $t) use ($summary169): void {
    $rows = $summary169()['currentRows'];
    $t->same([2, 3, 4, 4, 1, 5], array_column($rows, 'id'));
    $t->same(['home', 'seed:2:3', 'seed:2:3:4', 'active_plugins', 'siteurl', 'seed:2:3:4:5'], array_column($rows, 'label'));
    $t->same([1, 1, 1, 1, 2, 2], array_column($rows, 'bucket'));
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile next rows'] = static function (TestRunner $t) use ($summary169): void {
    $rows = $summary169()['nextRows'];
    $t->same([2, 3, 4, 4, 5, 1], array_column($rows, 'id'));
    $t->same(['home', 'seed:2:3', 'seed:2:3:4', 'active_plugins', 'rewrite_rules', 'siteurl'], array_column($rows, 'label'));
    $t->same([1, 1, 1, 1, 1, 2], array_column($rows, 'bucket'));
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile recursive comma limit trace'] = static function (TestRunner $t) use ($summary169): void {
    $recursive = $summary169()['recursive'];
    $t->same('q', $recursive['name']);
    $t->same(['id', 'label', 'weight'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['seed'], $recursive['currentSkippedLabels']);
    $t->same(['seed:2', 'seed:2:3', 'seed:2:3:4', 'seed:2:3:4:5', 'seed:2:3:4:5:6'], $recursive['currentEmittedLabels']);
    $t->same(0, $recursive['currentLimitRemaining']);
    $t->same(0, $recursive['currentOffsetRemaining']);
    $t->true($recursive['usesCommaQueueLimit']);
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile window metadata'] = static function (TestRunner $t) use ($summary169): void {
    $windows = $summary169()['windows'];
    $t->same(['ntile'], $windows['functions']);
    $t->same(['ntile', 'ntile'], array_column($windows['current'], 'function'));
    $t->same(['bucket', 'bucket'], array_column($windows['current'], 'alias'));
    $t->same([1, 1], array_column($windows['current'], 'argumentCount'));
    $t->same([2, 2], array_column($windows['current'], 'orderCount'));
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile prelimit trace'] = static function (TestRunner $t) use ($summary169): void {
    $plan = $summary169();
    $t->same(8, $plan['limitTrace']['current']['preLimitCount']);
    $t->same(10, $plan['limitTrace']['next']['preLimitCount']);
    $t->same(['seed:2'], array_column($plan['limitTrace']['current']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2'], array_column($plan['limitTrace']['next']['skippedBeforeOffset'], 'label'));
    $t->same(['seed:2:3:4:5', 'seed:2:3:4:5:6', 'theme_mods'], array_column($plan['limitTrace']['next']['truncatedAfterLimit'], 'label'));
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile bucket delta'] = static function (TestRunner $t) use ($summary169): void {
    $delta = $summary169()['bucketDelta'];
    $t->same([1 => 4, 2 => 2], $delta['current']);
    $t->same([1 => 5, 2 => 1], $delta['next']);
    $t->same(['rewrite_rules'], $delta['newLabels']);
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile boundary and reasons'] = static function (TestRunner $t) use ($summary169): void {
    $plan = $summary169();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->contains('"label":"rewrite_rules"', $changed);
    $t->same('home', $plan['boundary']['currentFirst']['label']);
    $t->same('home', $plan['boundary']['nextFirst']['label']);
    $t->same('seed:2:3:4:5', $plan['boundary']['currentLast']['label']);
    $t->same('siteurl', $plan['boundary']['nextLast']['label']);
    $t->true(in_array('recursive-queue-order-limit-comma', $plan['replanReasons'], true));
    $t->true(in_array('ntile-window-before-compound-limit', $plan['replanReasons'], true));
    $t->true(in_array('compound-tail-limit-offset', $plan['replanReasons'], true));
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile rejects missing comma limit'] = static function (TestRunner $t) use ($currentTables169): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveOrderCommaLimitNtile(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 1 FROM q WHERE id < 3 LIMIT 3) SELECT id, label, ntile(2) OVER (ORDER BY weight) AS bucket FROM q UNION ALL SELECT option_id, option_name, ntile(2) OVER (ORDER BY weight) FROM wp_options LIMIT 2 OFFSET 0",
        $currentTables169,
        $currentTables169,
    ));
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile rejects missing ntile'] = static function (TestRunner $t) use ($currentTables169): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveOrderCommaLimitNtile(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 1 FROM q WHERE id < 3 ORDER BY 1 LIMIT 1, 3) SELECT id, label, row_number() OVER (ORDER BY weight) AS bucket FROM q UNION ALL SELECT option_id, option_name, row_number() OVER (ORDER BY weight) FROM wp_options LIMIT 2 OFFSET 0",
        $currentTables169,
        $currentTables169,
    ));
};

$tests['compound select window recursive limit current source recursive-order-comma-limit-ntile rejects non compound'] = static function (TestRunner $t) use ($currentTables169): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveOrderCommaLimitNtile(
        "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed', 1) UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 1 FROM q WHERE id < 3 ORDER BY 1 LIMIT 1, 3) SELECT id, label, ntile(2) OVER (ORDER BY weight) AS bucket FROM q LIMIT 2 OFFSET 0",
        $currentTables169,
        $currentTables169,
    ));
};

foreach (range(1, 58) as $case) {
    $tests['compound select window recursive limit current source recursive-order-comma-limit-ntile generated ntile comma limit boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'autoload_' . $case, 'autoload' => 'yes', 'weight' => 10 + $case],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 20 + $case],
                ['option_id' => 3, 'option_name' => 'skip_' . $case, 'autoload' => 'no', 'weight' => 18 + $case],
                ['option_id' => 4, 'option_name' => 'plugin_' . $case, 'autoload' => 'yes', 'weight' => 14 + $case],
            ],
        ];
        $sql = "WITH RECURSIVE q(id, label, weight) AS (VALUES (1, 'seed_{$case}', 36) UNION ALL SELECT id + 1, label || ':' || (id + 1), weight - 5 FROM q WHERE id < 7 ORDER BY 1 LIMIT 1, 5) SELECT id, label, ntile(2) OVER (ORDER BY weight DESC, id) AS bucket FROM q UNION ALL SELECT option_id AS id, option_name AS label, ntile(2) OVER (ORDER BY weight DESC, option_id) AS bucket FROM wp_options WHERE autoload = 'yes' ORDER BY bucket, id LIMIT 6 OFFSET 1";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(6, count($rows));
        $t->same([1, 1, 1, 1, 2, 2], array_column($rows, 'bucket'));
        $t->same('home_' . $case, $rows[0]['label'] ?? null);
        $t->contains('seed_' . $case . ':2:3', implode('|', array_column($rows, 'label')));
    };
}

return $tests;

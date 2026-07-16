<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['setting_id' => 1, 'key_name' => 'root', 'parent_id' => 0, 'priority' => '8', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'numeric_child', 'parent_id' => 1, 'priority' => 2, 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'text_child', 'parent_id' => 1, 'priority' => '1', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => 'leaf_numeric', 'parent_id' => 2, 'priority' => 3, 'load_policy' => 'no'],
    ['setting_id' => 50, 'key_name' => 'direct', 'parent_id' => -1, 'priority' => 1, 'load_policy' => 'yes'],
];
$nextOptions = [
    ...$currentOptions,
    ['setting_id' => 6, 'key_name' => 'module_beta', 'parent_id' => 1, 'priority' => 1.5, 'load_policy' => 'yes'],
    ['setting_id' => 7, 'key_name' => 'module_beta_child', 'parent_id' => 6, 'priority' => '2', 'load_policy' => 'no'],
];
$currentTables = ['app_settings' => $currentOptions];
$nextTables = ['app_settings' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE setting_walk(id, label, queue_key, depth) AS (
    SELECT setting_id, key_name, priority, 0
      FROM app_settings
     WHERE parent_id = 0
    UNION ALL
    SELECT child.setting_id, child.key_name, child.priority, setting_walk.depth + 1
      FROM app_settings AS child
      JOIN setting_walk ON child.parent_id = setting_walk.id
     WHERE setting_walk.depth < 3
     ORDER BY 3 ASC, 1 ASC
     LIMIT 8
)
SELECT id,
       label,
       depth,
       queue_key,
       row_number() OVER (ORDER BY queue_key ASC, id ASC) AS visit_rank
  FROM setting_walk
UNION ALL
SELECT setting_id AS id,
       key_name AS label,
       0 AS depth,
       priority AS queue_key,
       row_number() OVER (ORDER BY priority ASC, setting_id ASC) AS visit_rank
  FROM app_settings
 WHERE parent_id = -1
 ORDER BY visit_rank ASC, queue_key ASC, label ASC
 LIMIT 7
SQL;

$summary = static fn (): array => SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan::compare($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound select recursive window order current source status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-recursive-window-order-current-source-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-queue-order-before-window-arm',
        'sqlite-select-sql-window-arm-before-compound-final-order',
        'sqlite-compound-select-final-order-current-source',
    ], $plan['dependencies']);
};

$tests['compound select recursive window order current source compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['visit_rank', 'queue_key', 'label'], $compound['orderColumns']);
    $t->same(['ASC', 'ASC', 'ASC'], $compound['orderDirections']);
    $t->same(7, $compound['limit']);
    $t->same(0, $compound['offset']);
};

$tests['compound select recursive window order current source current ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([50, 2, 4, 3, 1], array_column($rows, 'id'));
    $t->same(['direct', 'numeric_child', 'leaf_numeric', 'text_child', 'root'], array_column($rows, 'label'));
    $t->same([1, 2, 3, '1', '8'], array_column($rows, 'queue_key'));
    $t->same([1, 1, 2, 3, 4], array_column($rows, 'visit_rank'));
};

$tests['compound select recursive window order current source next ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([50, 6, 2, 4, 3, 7, 1], array_column($rows, 'id'));
    $t->same(['direct', 'module_beta', 'numeric_child', 'leaf_numeric', 'text_child', 'module_beta_child', 'root'], array_column($rows, 'label'));
    $t->same([1, 1.5, 2, 3, '1', '2', '8'], array_column($rows, 'queue_key'));
    $t->same([1, 1, 2, 3, 4, 5, 6], array_column($rows, 'visit_rank'));
};

$tests['compound select recursive window order current source recursive queue order'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('setting_walk', $recursive['name']);
    $t->same(['id', 'label', 'queue_key', 'depth'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['root', 'numeric_child', 'leaf_numeric', 'text_child'], $recursive['currentVisitedLabels']);
    $t->same(['root', 'module_beta', 'numeric_child', 'leaf_numeric', 'text_child', 'module_beta_child'], $recursive['nextVisitedLabels']);
    $t->same(['string:8', 'numeric:2', 'numeric:3', 'string:1'], $recursive['currentQueueKeys']);
    $t->same(['string:8', 'numeric:1.5', 'numeric:2', 'numeric:3', 'string:1', 'string:2'], $recursive['nextQueueKeys']);
};

$tests['compound select recursive window order current source recursive accepted labels'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same([['numeric_child', 'text_child'], ['leaf_numeric'], [], []], $recursive['currentAcceptedNextLabels']);
    $t->same([['numeric_child', 'text_child', 'module_beta'], ['module_beta_child'], ['leaf_numeric']], array_slice($recursive['nextAcceptedNextLabels'], 0, 3));
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound select recursive window order current source window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['row_number', 'row_number'], array_column($windows, 'function'));
    $t->same(['visit_rank', 'visit_rank'], $summary()['windows']['outputAliases']);
    $t->same([0, 0], array_column($windows, 'partitionCount'));
    $t->same([2, 2], array_column($windows, 'orderCount'));
};

$tests['compound select recursive window order current source order boundary'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['orderBoundary'];
    $t->same(50, $boundary['currentFirst']['id']);
    $t->same(50, $boundary['nextFirst']['id']);
    $t->same(1, $boundary['currentLast']['id']);
    $t->same(1, $boundary['nextLast']['id']);
    $t->same(5, $boundary['currentCount']);
    $t->same(7, $boundary['nextCount']);
};

$tests['compound select recursive window order current source changed signatures and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->true(str_contains($changed, 'module_beta'));
    $t->true(str_contains($changed, '"queue_key":1.5'));
    $t->true(in_array('compound-recursive-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-queue-order-before-window-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-queue-storage-class-boundary-changed', $plan['replanReasons'], true));
    $t->true(in_array('window-arm-evaluated-before-compound-order', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-order-after-recursive-window', $plan['replanReasons'], true));
};

$tests['compound select recursive window order current source rejects non recursive select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan::compare(
        'SELECT setting_id AS id, key_name AS label, row_number() OVER (ORDER BY setting_id) AS visit_rank FROM app_settings ORDER BY visit_rank',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select recursive window order current source rejects non compound select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan::compare(
        'WITH RECURSIVE setting_walk(id, label, queue_key, depth) AS (SELECT setting_id, key_name, priority, 0 FROM app_settings WHERE parent_id = 0 UNION ALL SELECT child.setting_id, child.key_name, child.priority, setting_walk.depth + 1 FROM app_settings AS child JOIN setting_walk ON child.parent_id = setting_walk.id WHERE setting_walk.depth < 3 ORDER BY 3 ASC, 1 ASC LIMIT 8) SELECT id, label, depth, queue_key, row_number() OVER (ORDER BY queue_key ASC, id ASC) AS visit_rank FROM setting_walk ORDER BY visit_rank',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 56) as $case) {
    $tests['compound select recursive window order current source generated recursive window ordering ' . $case] = static function (TestRunner $t) use ($case): void {
        $numeric = 1 + ($case % 5);
        $text = (string) (1 + ($case % 4));
        $limit = 4 + ($case % 3);
        $tables = [
            'app_settings' => [
                ['setting_id' => 1, 'key_name' => 'root_' . $case, 'parent_id' => 0, 'priority' => '9', 'load_policy' => 'yes'],
                ['setting_id' => 2, 'key_name' => 'numeric_' . $case, 'parent_id' => 1, 'priority' => $numeric, 'load_policy' => 'yes'],
                ['setting_id' => 3, 'key_name' => 'text_' . $case, 'parent_id' => 1, 'priority' => $text, 'load_policy' => 'no'],
                ['setting_id' => 4, 'key_name' => 'numeric_child_' . $case, 'parent_id' => 2, 'priority' => $numeric + 1, 'load_policy' => 'no'],
                ['setting_id' => 50, 'key_name' => 'direct_' . $case, 'parent_id' => -1, 'priority' => 0, 'load_policy' => 'yes'],
            ],
        ];
        $sql = "WITH RECURSIVE setting_walk(id, label, queue_key, depth) AS (SELECT setting_id, key_name, priority, 0 FROM app_settings WHERE parent_id = 0 UNION ALL SELECT child.setting_id, child.key_name, child.priority, setting_walk.depth + 1 FROM app_settings AS child JOIN setting_walk ON child.parent_id = setting_walk.id WHERE setting_walk.depth < 3 ORDER BY 3 ASC, 1 ASC LIMIT 8) SELECT id, label, depth, queue_key, row_number() OVER (ORDER BY queue_key ASC, id ASC) AS visit_rank FROM setting_walk UNION ALL SELECT setting_id AS id, key_name AS label, 0 AS depth, priority AS queue_key, row_number() OVER (ORDER BY priority ASC, setting_id ASC) AS visit_rank FROM app_settings WHERE parent_id = -1 ORDER BY visit_rank ASC, queue_key ASC, label ASC LIMIT {$limit}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(min($limit, 5), count($rows));
        $t->same('direct_' . $case, $rows[0]['label']);
        $t->same('numeric_' . $case, $rows[1]['label']);
        $t->true($rows[1]['visit_rank'] <= $rows[count($rows) - 1]['visit_rank']);
    };
}

return $tests;

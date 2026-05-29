<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'root', 'parent_id' => 0, 'priority' => '8', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'numeric_child', 'parent_id' => 1, 'priority' => 2, 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'text_child', 'parent_id' => 1, 'priority' => '1', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'leaf_numeric', 'parent_id' => 2, 'priority' => 3, 'autoload' => 'no'],
    ['option_id' => 50, 'option_name' => 'direct', 'parent_id' => -1, 'priority' => 1, 'autoload' => 'yes'],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 6, 'option_name' => 'plugin_beta', 'parent_id' => 1, 'priority' => 1.5, 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_beta_child', 'parent_id' => 6, 'priority' => '2', 'autoload' => 'no'],
];
$currentTables = ['wp_options' => $currentOptions];
$nextTables = ['wp_options' => $nextOptions];

$sql = <<<'SQL'
WITH RECURSIVE option_walk(id, label, queue_key, depth) AS (
    SELECT option_id, option_name, priority, 0
      FROM wp_options
     WHERE parent_id = 0
    UNION ALL
    SELECT child.option_id, child.option_name, child.priority, option_walk.depth + 1
      FROM wp_options AS child
      JOIN option_walk ON child.parent_id = option_walk.id
     WHERE option_walk.depth < 3
     ORDER BY 3 ASC, 1 ASC
     LIMIT 8
)
SELECT id,
       label,
       depth,
       queue_key,
       row_number() OVER (ORDER BY queue_key ASC, id ASC) AS visit_rank
  FROM option_walk
UNION ALL
SELECT option_id AS id,
       option_name AS label,
       0 AS depth,
       priority AS queue_key,
       row_number() OVER (ORDER BY priority ASC, option_id ASC) AS visit_rank
  FROM wp_options
 WHERE parent_id = -1
 ORDER BY visit_rank ASC, queue_key ASC, label ASC
 LIMIT 7
SQL;

$summary = static fn (): array => SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan::compareNext144($sql, $currentTables, $nextTables);
$tests = [];

$tests['compound select recursive window order current source next144 status dependencies'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $t->same('compound-select-recursive-window-order-current-source-next144-ready', $plan['status']);
    $t->same([
        'sqlite-recursive-cte-queue-order-before-window-arm',
        'sqlite-select-sql-window-arm-before-compound-final-order',
        'sqlite-compound-select-final-order-current-source-next144',
    ], $plan['dependencies']);
};

$tests['compound select recursive window order current source next144 compound metadata'] = static function (TestRunner $t) use ($summary): void {
    $compound = $summary()['compound'];
    $t->same(['UNION ALL'], $compound['operators']);
    $t->same(2, $compound['currentArms']);
    $t->same(2, $compound['nextArms']);
    $t->same(['visit_rank', 'queue_key', 'label'], $compound['orderColumns']);
    $t->same(['ASC', 'ASC', 'ASC'], $compound['orderDirections']);
    $t->same(7, $compound['limit']);
    $t->same(0, $compound['offset']);
};

$tests['compound select recursive window order current source next144 current ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['currentRows'];
    $t->same([50, 2, 4, 3, 1], array_column($rows, 'id'));
    $t->same(['direct', 'numeric_child', 'leaf_numeric', 'text_child', 'root'], array_column($rows, 'label'));
    $t->same([1, 2, 3, '1', '8'], array_column($rows, 'queue_key'));
    $t->same([1, 1, 2, 3, 4], array_column($rows, 'visit_rank'));
};

$tests['compound select recursive window order current source next144 next ordered rows'] = static function (TestRunner $t) use ($summary): void {
    $rows = $summary()['nextRows'];
    $t->same([50, 6, 2, 4, 3, 7, 1], array_column($rows, 'id'));
    $t->same(['direct', 'plugin_beta', 'numeric_child', 'leaf_numeric', 'text_child', 'plugin_beta_child', 'root'], array_column($rows, 'label'));
    $t->same([1, 1.5, 2, 3, '1', '2', '8'], array_column($rows, 'queue_key'));
    $t->same([1, 1, 2, 3, 4, 5, 6], array_column($rows, 'visit_rank'));
};

$tests['compound select recursive window order current source next144 recursive queue order'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same('option_walk', $recursive['name']);
    $t->same(['id', 'label', 'queue_key', 'depth'], $recursive['columns']);
    $t->same('UNION ALL', $recursive['operator']);
    $t->same(['root', 'numeric_child', 'leaf_numeric', 'text_child'], $recursive['currentVisitedLabels']);
    $t->same(['root', 'plugin_beta', 'numeric_child', 'leaf_numeric', 'text_child', 'plugin_beta_child'], $recursive['nextVisitedLabels']);
    $t->same(['string:8', 'numeric:2', 'numeric:3', 'string:1'], $recursive['currentQueueKeys']);
    $t->same(['string:8', 'numeric:1.5', 'numeric:2', 'numeric:3', 'string:1', 'string:2'], $recursive['nextQueueKeys']);
};

$tests['compound select recursive window order current source next144 recursive accepted labels'] = static function (TestRunner $t) use ($summary): void {
    $recursive = $summary()['recursive'];
    $t->same([['numeric_child', 'text_child'], ['leaf_numeric'], [], []], $recursive['currentAcceptedNextLabels']);
    $t->same([['numeric_child', 'text_child', 'plugin_beta'], ['plugin_beta_child'], ['leaf_numeric']], array_slice($recursive['nextAcceptedNextLabels'], 0, 3));
    $t->true(in_array('sqlite-recursive-cte-current-row', $recursive['dependencies'], true));
};

$tests['compound select recursive window order current source next144 window metadata'] = static function (TestRunner $t) use ($summary): void {
    $windows = $summary()['windows']['current'];
    $t->same(['row_number', 'row_number'], array_column($windows, 'function'));
    $t->same(['visit_rank', 'visit_rank'], $summary()['windows']['outputAliases']);
    $t->same([0, 0], array_column($windows, 'partitionCount'));
    $t->same([2, 2], array_column($windows, 'orderCount'));
};

$tests['compound select recursive window order current source next144 order boundary'] = static function (TestRunner $t) use ($summary): void {
    $boundary = $summary()['orderBoundary'];
    $t->same(50, $boundary['currentFirst']['id']);
    $t->same(50, $boundary['nextFirst']['id']);
    $t->same(1, $boundary['currentLast']['id']);
    $t->same(1, $boundary['nextLast']['id']);
    $t->same(5, $boundary['currentCount']);
    $t->same(7, $boundary['nextCount']);
};

$tests['compound select recursive window order current source next144 changed signatures and reasons'] = static function (TestRunner $t) use ($summary): void {
    $plan = $summary();
    $changed = implode("\n", $plan['changedSignatures']);
    $t->true(str_contains($changed, 'plugin_beta'));
    $t->true(str_contains($changed, '"queue_key":1.5'));
    $t->true(in_array('compound-recursive-window-rowset-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-queue-order-before-window-changed', $plan['replanReasons'], true));
    $t->true(in_array('recursive-queue-storage-class-boundary-changed', $plan['replanReasons'], true));
    $t->true(in_array('window-arm-evaluated-before-compound-order', $plan['replanReasons'], true));
    $t->true(in_array('compound-final-order-after-recursive-window', $plan['replanReasons'], true));
};

$tests['compound select recursive window order current source next144 rejects non recursive select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan::compareNext144(
        'SELECT option_id AS id, option_name AS label, row_number() OVER (ORDER BY option_id) AS visit_rank FROM wp_options ORDER BY visit_rank',
        $currentTables,
        $currentTables,
    ));
};

$tests['compound select recursive window order current source next144 rejects non compound select'] = static function (TestRunner $t) use ($currentTables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan::compareNext144(
        'WITH RECURSIVE option_walk(id, label, queue_key, depth) AS (SELECT option_id, option_name, priority, 0 FROM wp_options WHERE parent_id = 0 UNION ALL SELECT child.option_id, child.option_name, child.priority, option_walk.depth + 1 FROM wp_options AS child JOIN option_walk ON child.parent_id = option_walk.id WHERE option_walk.depth < 3 ORDER BY 3 ASC, 1 ASC LIMIT 8) SELECT id, label, depth, queue_key, row_number() OVER (ORDER BY queue_key ASC, id ASC) AS visit_rank FROM option_walk ORDER BY visit_rank',
        $currentTables,
        $currentTables,
    ));
};

foreach (range(1, 56) as $case) {
    $tests['compound select recursive window order current source next144 generated recursive window ordering ' . $case] = static function (TestRunner $t) use ($case): void {
        $numeric = 1 + ($case % 5);
        $text = (string) (1 + ($case % 4));
        $limit = 4 + ($case % 3);
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'root_' . $case, 'parent_id' => 0, 'priority' => '9', 'autoload' => 'yes'],
                ['option_id' => 2, 'option_name' => 'numeric_' . $case, 'parent_id' => 1, 'priority' => $numeric, 'autoload' => 'yes'],
                ['option_id' => 3, 'option_name' => 'text_' . $case, 'parent_id' => 1, 'priority' => $text, 'autoload' => 'no'],
                ['option_id' => 4, 'option_name' => 'numeric_child_' . $case, 'parent_id' => 2, 'priority' => $numeric + 1, 'autoload' => 'no'],
                ['option_id' => 50, 'option_name' => 'direct_' . $case, 'parent_id' => -1, 'priority' => 0, 'autoload' => 'yes'],
            ],
        ];
        $sql = "WITH RECURSIVE option_walk(id, label, queue_key, depth) AS (SELECT option_id, option_name, priority, 0 FROM wp_options WHERE parent_id = 0 UNION ALL SELECT child.option_id, child.option_name, child.priority, option_walk.depth + 1 FROM wp_options AS child JOIN option_walk ON child.parent_id = option_walk.id WHERE option_walk.depth < 3 ORDER BY 3 ASC, 1 ASC LIMIT 8) SELECT id, label, depth, queue_key, row_number() OVER (ORDER BY queue_key ASC, id ASC) AS visit_rank FROM option_walk UNION ALL SELECT option_id AS id, option_name AS label, 0 AS depth, priority AS queue_key, row_number() OVER (ORDER BY priority ASC, option_id ASC) AS visit_rank FROM wp_options WHERE parent_id = -1 ORDER BY visit_rank ASC, queue_key ASC, label ASC LIMIT {$limit}";
        $rows = SQLiteSelectSql::execute($sql, $tables);

        $t->same(min($limit, 5), count($rows));
        $t->same('direct_' . $case, $rows[0]['label']);
        $t->same('numeric_' . $case, $rows[1]['label']);
        $t->true($rows[1]['visit_rank'] <= $rows[count($rows) - 1]['visit_rank']);
    };
}

return $tests;

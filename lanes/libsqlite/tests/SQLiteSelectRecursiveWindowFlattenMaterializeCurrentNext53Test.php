<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectRecursiveWindowMaterializePlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'import_batch_alpha', 'autoload' => 'yes', 'parent_id' => null, 'step' => 1, 'payload' => 'root'],
    ['option_id' => 2, 'option_name' => 'import_batch_alpha_child_a', 'autoload' => 'yes', 'parent_id' => 1, 'step' => 2, 'payload' => 'rewrite'],
    ['option_id' => 3, 'option_name' => 'import_batch_alpha_child_b', 'autoload' => 'yes', 'parent_id' => 1, 'step' => 3, 'payload' => 'media'],
    ['option_id' => 4, 'option_name' => 'import_batch_alpha_grandchild', 'autoload' => 'yes', 'parent_id' => 2, 'step' => 4, 'payload' => 'term'],
    ['option_id' => 5, 'option_name' => 'import_batch_beta', 'autoload' => 'yes', 'parent_id' => null, 'step' => 1, 'payload' => 'root'],
    ['option_id' => 6, 'option_name' => 'import_batch_beta_child', 'autoload' => 'yes', 'parent_id' => 5, 'step' => 2, 'payload' => 'rewrite'],
    ['option_id' => 7, 'option_name' => 'draft_batch', 'autoload' => 'no', 'parent_id' => null, 'step' => 1, 'payload' => 'skip'],
];

$sql = "
WITH RECURSIVE queue(option_id, parent_id, option_name, depth, step, path) AS MATERIALIZED (
    SELECT option_id AS seed_option_id, parent_id AS seed_parent_id, option_name AS seed_option_name, 0 AS seed_depth, step AS seed_step, option_name AS seed_path
    FROM wp_options
    WHERE parent_id IS NULL AND autoload = 'yes'
    UNION ALL
    SELECT child.option_id AS child_option_id, child.parent_id AS child_parent_id, child.option_name AS child_option_name, queue.depth + 1 AS child_depth, child.step AS child_step, queue.path || '/' || child.option_name AS child_path
    FROM queue
    JOIN wp_options AS child ON child.parent_id = queue.option_id
    WHERE queue.depth < 3
)
SELECT option_id, parent_id, option_name, depth, step, path,
       row_number() OVER (PARTITION BY parent_id ORDER BY step, option_id) AS sibling_row,
       lead(option_name, 1, 'done') OVER (PARTITION BY parent_id ORDER BY step, option_id) AS next_sibling,
       first_value(option_name) OVER (PARTITION BY parent_id ORDER BY step, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_first,
       last_value(option_name) OVER (PARTITION BY parent_id ORDER BY step, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_last,
       count(*) OVER (PARTITION BY parent_id ORDER BY step, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS following_count
FROM queue
ORDER BY depth, parent_id, step, option_id
";

$plan = static fn (): array => SQLiteSelectRecursiveWindowMaterializePlan::execute(
    $sql,
    ['wp_options' => $rows],
    ['parent_id', 'option_id'],
    ['sibling_row', 'next_sibling', 'frame_first', 'frame_last', 'following_count'],
);

$column = static fn (array $plan, string $column): array => array_column($plan['rows'], $column);

$tests = [];

$tests['select recursive window flatten materialize current next53 materializes recursive cte'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan();
    $t->same(true, $result['ctePlan']['recursive']);
    $t->same(['queue'], $result['ctePlan']['materialized']);
    $t->same('materialize', $result['ctePlan']['ctes'][0]['decision']);
    $t->true(in_array('materialized-hint', $result['ctePlan']['ctes'][0]['blockers'], true));
    $t->true(in_array('recursive', $result['ctePlan']['ctes'][0]['blockers'], true));
};

$tests['select recursive window flatten materialize current next53 recursive trace records current rows'] = static function (TestRunner $t) use ($plan): void {
    $trace = $plan()['recursiveTrace'];
    $t->same('queue', $trace['name']);
    $t->same(['option_id', 'parent_id', 'option_name', 'depth', 'step', 'path'], $trace['columns']);
    $t->same(6, count($trace['rows']));
    $t->same('import_batch_alpha', $trace['trace'][0]['current']['option_name']);
    $t->true(in_array('sqlite-recursive-cte-current-row', $trace['dependencies'], true));
};

$expectedColumns = [
    'option_id' => [1, 5, 2, 3, 6, 4],
    'option_name' => ['import_batch_alpha', 'import_batch_beta', 'import_batch_alpha_child_a', 'import_batch_alpha_child_b', 'import_batch_beta_child', 'import_batch_alpha_grandchild'],
    'depth' => [0, 0, 1, 1, 1, 2],
    'path' => [
        'import_batch_alpha',
        'import_batch_beta',
        'import_batch_alpha/import_batch_alpha_child_a',
        'import_batch_alpha/import_batch_alpha_child_b',
        'import_batch_beta/import_batch_beta_child',
        'import_batch_alpha/import_batch_alpha_child_a/import_batch_alpha_grandchild',
    ],
    'sibling_row' => [1, 2, 1, 2, 1, 1],
    'next_sibling' => ['import_batch_beta', 'done', 'import_batch_alpha_child_b', 'done', 'done', 'done'],
    'frame_first' => ['import_batch_alpha', 'import_batch_beta', 'import_batch_alpha_child_a', 'import_batch_alpha_child_b', 'import_batch_beta_child', 'import_batch_alpha_grandchild'],
    'frame_last' => ['import_batch_beta', 'import_batch_beta', 'import_batch_alpha_child_b', 'import_batch_alpha_child_b', 'import_batch_beta_child', 'import_batch_alpha_grandchild'],
    'following_count' => [1, 0, 1, 0, 0, 0],
];

foreach ($expectedColumns as $name => $expected) {
    $tests['select recursive window flatten materialize current next53 column ' . $name] = static function (TestRunner $t) use ($plan, $column, $name, $expected): void {
        $t->same($expected, $column($plan(), $name));
    };
}

foreach (range(0, 5) as $index) {
    $tests['select recursive window flatten materialize current next53 current next pair ' . $index] = static function (TestRunner $t) use ($plan, $index): void {
        $pair = $plan()['currentNext'][$index];
        $t->same($index, $pair['position']);
        $t->same($pair['current']['parent_id'], $pair['key']['parent_id']);
        $t->same($pair['current']['option_id'], $pair['key']['option_id']);
        $t->same($pair['current']['sibling_row'], $pair['currentWindow']['sibling_row']);
        $t->same($pair['next']['sibling_row'] ?? null, $pair['nextWindow']['sibling_row'] ?? null);
    };
}

$tests['select recursive window flatten materialize current next53 reports partition boundaries'] = static function (TestRunner $t) use ($plan): void {
    $pairs = $plan()['currentNext'];
    $t->same(true, $pairs[0]['samePartition']);
    $t->same(false, $pairs[1]['samePartition']);
    $t->same(true, $pairs[2]['samePartition']);
    $t->same(false, $pairs[3]['samePartition']);
    $t->same(false, $pairs[5]['samePartition']);
};

$tests['select recursive window flatten materialize current next53 records dependency tags'] = static function (TestRunner $t) use ($plan): void {
    $dependencies = $plan()['dependencies'];
    $t->true(in_array('sqlite-select-recursive-materialized-current-source', $dependencies, true));
    $t->true(in_array('sqlite-select-window-current-next-yield', $dependencies, true));
    $t->true(in_array('sqlite-select-cte-flatten-materialize-boundary', $dependencies, true));
};

$tests['select recursive window flatten materialize current next53 validates input guards'] = static function (TestRunner $t) use ($sql, $rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveWindowMaterializePlan::execute($sql, ['wp_options' => $rows], [], ['sibling_row']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveWindowMaterializePlan::execute($sql, ['wp_options' => $rows], ['option_id'], []));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveWindowMaterializePlan::execute('SELECT option_id FROM wp_options', ['wp_options' => $rows], ['option_id'], ['sibling_row']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveWindowMaterializePlan::execute(str_replace(' AS MATERIALIZED ', ' AS ', $sql), ['wp_options' => $rows], ['option_id'], ['sibling_row']));
    $noWindowSql = preg_replace('/row_number\(\) OVER \(PARTITION BY parent_id ORDER BY step, option_id\) AS sibling_row,.*?count\(\*\) OVER \(PARTITION BY parent_id ORDER BY step, option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW\) AS following_count/s', '1 AS sibling_row, 2 AS next_sibling, 3 AS frame_first, 4 AS frame_last, 0 AS following_count', $sql);
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveWindowMaterializePlan::execute((string) $noWindowSql, ['wp_options' => $rows], ['option_id'], ['sibling_row']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveWindowMaterializePlan::execute($sql, ['wp_options' => $rows], ['missing_key'], ['sibling_row']));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveWindowMaterializePlan::execute($sql, ['wp_options' => $rows], ['option_id'], ['missing_window']));
};

return $tests;

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectRecursiveWindowMaterializePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'import_batch_alpha', 'autoload' => 'yes', 'parent_id' => null, 'step' => 1],
    ['option_id' => 2, 'option_name' => 'import_batch_alpha_child_a', 'autoload' => 'yes', 'parent_id' => 1, 'step' => 2],
    ['option_id' => 3, 'option_name' => 'import_batch_alpha_child_b', 'autoload' => 'yes', 'parent_id' => 1, 'step' => 3],
    ['option_id' => 4, 'option_name' => 'import_batch_beta', 'autoload' => 'yes', 'parent_id' => null, 'step' => 1],
    ['option_id' => 5, 'option_name' => 'draft_batch', 'autoload' => 'no', 'parent_id' => null, 'step' => 1],
];

$sql = "
WITH RECURSIVE queue(option_id, parent_id, option_name, depth, step) AS MATERIALIZED (
    SELECT option_id AS seed_option_id, parent_id AS seed_parent_id, option_name AS seed_option_name, 0 AS seed_depth, step AS seed_step FROM wp_options WHERE parent_id IS NULL AND autoload = 'yes'
    UNION ALL
    SELECT child.option_id AS child_option_id, child.parent_id AS child_parent_id, child.option_name AS child_option_name, queue.depth + 1 AS child_depth, child.step AS child_step
    FROM queue JOIN wp_options AS child ON child.parent_id = queue.option_id
    WHERE queue.depth < 2
)
SELECT option_id, parent_id, option_name, depth,
       row_number() OVER (PARTITION BY parent_id ORDER BY step, option_id) AS sibling_row,
       lead(option_name, 1, 'done') OVER (PARTITION BY parent_id ORDER BY step, option_id) AS next_sibling
FROM queue
ORDER BY depth, parent_id, step, option_id
";

$plan = SQLiteSelectRecursiveWindowMaterializePlan::execute(
    $sql,
    ['wp_options' => $options],
    ['parent_id', 'option_id'],
    ['sibling_row', 'next_sibling'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert(count($plan['rows']) === 4);
    assert($plan['ctePlan']['materialized'] === ['queue']);
    assert($plan['rows'][0]['next_sibling'] === 'import_batch_beta');
    assert($plan['rows'][2]['next_sibling'] === 'import_batch_alpha_child_b');
    echo "application-select-recursive-window-flatten-materialize-current-next53 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-select-recursive-window-flatten-materialize-current-next53',
    'applicationUse' => 'Copied wp_options import queues can keep a recursive CTE materialized while yielding parser-level window current/next sibling diagnostics for each row without requiring ext/sqlite.',
    'rows' => $plan['rows'],
    'materialized' => $plan['ctePlan']['materialized'],
    'currentNext' => $plan['currentNext'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

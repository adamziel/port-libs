<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectCteFlattenMaterializePlan;

$sql = "WITH autoloaded AS NOT MATERIALIZED (SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes'), grouped AS MATERIALIZED (SELECT autoload, count(*) AS n FROM wp_options GROUP BY autoload) SELECT option_name FROM autoloaded ORDER BY option_id";
$plan = SQLiteSelectCteFlattenMaterializePlan::plan($sql);

if (($argv[1] ?? null) === '--self-test') {
    if ($plan['flattened'] !== ['autoloaded'] || $plan['materialized'] !== ['grouped']) {
        fwrite(STDERR, "unexpected CTE plan\n");
        exit(1);
    }
    echo "application-select-cte-flatten-materialize-current-next35 self-test passed\n";
    exit(0);
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

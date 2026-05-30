<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '1', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => '1.0', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'rewrite_rules', 'option_value' => 'routes', 'autoload' => 'no'],
    ],
];

$sql = "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) "
    . "SELECT v FROM seq UNION SELECT option_id FROM wp_options WHERE option_id = 1 ORDER BY v";

$rows = SQLiteSelectSql::execute($sql, $tables);

if (($argv[1] ?? '') === '--self-test') {
    assert($rows === [['v' => 1]]);
    $trace = SQLiteSelectSql::recursiveCteCycleTrace(
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) SELECT v FROM seq",
        $tables
    );
    assert(array_column($trace['rows'], 'v') === [1]);
    assert($trace['trace'][0]['skipped_duplicates'] === [['v' => 1.0]]);
    echo "application-compound-recursive-affinity-current-source-next120 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-compound-recursive-affinity-current-source-next120',
    'sql' => $sql,
    'rows' => $rows,
    'applicationUse' => 'Copied wp_options repair/import queries can combine recursive numeric generators with current option rows while preserving SQLite compound duplicate semantics: integer and real compare equal, text values remain distinct, and left-most recursive output names survive current-source arms.',
], JSON_PRETTY_PRINT) . "\n";

<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'priority' => 20],
    ['option_id' => 5, 'option_name' => 'new_plugin_flag', 'autoload' => 'no', 'priority' => 50],
];

$sql = "VALUES (40, 'queued_seed'), ('40', 'text_seed') UNION ALL SELECT priority, option_name FROM wp_options WHERE autoload = 'no' ORDER BY 1 NULLS LAST, 2";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

$result = [
    'scenario' => 'application-compound-values-affinity-order-current-source-next127',
    'applicationUse' => 'Copied wp_options import previews can seed staged VALUES rows into a compound SELECT before reading the current table, preserving SQLite storage-class ordering and left-arm column names without ext/sqlite.',
    'sql' => $sql,
    'orderedPriorities' => array_column($rows, 'column1'),
    'orderedNames' => array_column($rows, 'column2'),
    'outputColumns' => array_keys($rows[0]),
];

if (PHP_SAPI === 'cli' && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    if ($result['orderedPriorities'] !== [40, 50, '40']) {
        fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(1);
    }
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}

return $result;

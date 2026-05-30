<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24, 'option_value' => 'https://example.test'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24, 'option_value' => 'https://example.test'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 12, 'option_value' => 'Example Site'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'option_value' => 'cached'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 12, 'option_value' => 'cached'],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'bytes' => 48, 'option_value' => 'serialized-rules'],
];

$sql = <<<'SQL'
SELECT autoload,
       count(*) AS current_rows,
       count(DISTINCT option_value) AS next_distinct_values
FROM wp_options
GROUP BY autoload
HAVING count(*) >= 3
ORDER BY next_distinct_values DESC, autoload
SQL;

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

$report = [
    'scenario' => 'application-select-aggregate-current-next56',
    'applicationUse' => 'Copied wp_options imports can compare current grouped row counts with next distinct option-value counts through parser-level SELECT aggregate text without requiring ext/sqlite.',
    'sql' => preg_replace('/\s+/', ' ', trim($sql)),
    'autoloadGroups' => array_column($rows, 'autoload'),
    'currentRows' => array_column($rows, 'current_rows'),
    'nextDistinctValues' => array_column($rows, 'next_distinct_values'),
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($report['autoloadGroups'] !== ['no', 'yes'] || $report['currentRows'] !== [3, 3] || $report['nextDistinctValues'] !== [2, 2]) {
        fwrite(STDERR, "application-select-aggregate-current-next56 self-test failed\n");
        exit(1);
    }
    fwrite(STDOUT, "application-select-aggregate-current-next56 self-test passed\n");
}

return $report;

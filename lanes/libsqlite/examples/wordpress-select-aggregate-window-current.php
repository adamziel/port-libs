<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteWindowFunction.php';
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
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bucket' => 'core', 'bytes' => 10, 'enabled' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bucket' => 'core', 'bytes' => 20, 'enabled' => 1],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bucket' => 'theme', 'bytes' => null, 'enabled' => 0],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bucket' => 'cache', 'bytes' => 5, 'enabled' => 1],
    ['option_id' => 5, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'bucket' => 'cache', 'bytes' => null, 'enabled' => 1],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'no', 'bucket' => 'rules', 'bytes' => 40, 'enabled' => 0],
];

$sql = <<<'SQL'
SELECT option_id,
       autoload,
       sum(bytes) OVER (PARTITION BY autoload ORDER BY bucket) AS cumulative_bytes,
       total(bytes) FILTER (WHERE enabled) OVER (PARTITION BY autoload) AS enabled_total,
       group_concat(option_name) OVER (PARTITION BY autoload ORDER BY bucket) AS option_names
FROM wp_options
ORDER BY option_id
SQL;

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

$report = [
    'scenario' => 'wordpress-select-aggregate-window-current',
    'wordpressUse' => 'Copied wp_options diagnostics can run parser-level aggregate window functions with SQLite default frames and total() semantics before imports commit grouped option summaries.',
    'sql' => preg_replace('/\s+/', ' ', trim($sql)),
    'cumulativeBytes' => array_column($rows, 'cumulative_bytes'),
    'enabledTotals' => array_column($rows, 'enabled_total'),
    'optionWindows' => array_column($rows, 'option_names'),
    'dependencyClosure' => 'no new support component needed; reuses native SELECT SQL, window frame, numeric aggregate, and group_concat helpers',
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (
        $report['cumulativeBytes'] !== [30, 30, 30, 5, 5, 45]
        || $report['enabledTotals'] !== [30.0, 30.0, 30.0, 5.0, 5.0, 5.0]
        || ($report['optionWindows'][5] ?? null) !== '_transient_feed,_transient_timeout_feed,rewrite_rules'
    ) {
        fwrite(STDERR, "wordpress-select-aggregate-window-current self-test failed\n");
        exit(1);
    }
    fwrite(STDOUT, "wordpress-select-aggregate-window-current self-test passed\n");
}

return $report;

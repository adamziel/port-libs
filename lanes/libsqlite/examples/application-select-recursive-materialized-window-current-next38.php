<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteWindowFunction.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$sql = <<<'SQL'
WITH RECURSIVE import_window(slot) AS MATERIALIZED (
    VALUES (1)
    UNION ALL
    SELECT slot + 1
    FROM import_window
    WHERE slot < 6
    LIMIT 6
)
SELECT
    slot,
    CASE slot
        WHEN 1 THEN 'siteurl'
        WHEN 2 THEN 'home'
        WHEN 3 THEN 'blogname'
        WHEN 4 THEN 'active_plugins'
        WHEN 5 THEN 'rewrite_rules'
        ELSE 'theme_mods'
    END AS option_name,
    last_value(slot) OVER (
        ORDER BY slot
        ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
    ) AS next_import_slot,
    nth_value(slot, 2) OVER (
        PARTITION BY slot % 2
        ORDER BY slot
        ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
    ) AS next_same_parity_slot
FROM import_window
ORDER BY slot
SQL;

$rows = SQLiteSelectSql::execute($sql, []);

if (($argv[1] ?? '') === '--self-test') {
    $expectedNext = [2, 3, 4, 5, 6, 6];
    $expectedPartitionNext = [3, 4, 5, 6, null, null];
    if (array_column($rows, 'next_import_slot') !== $expectedNext) {
        fwrite(STDERR, "unexpected next import window\n");
        exit(1);
    }
    if (array_column($rows, 'next_same_parity_slot') !== $expectedPartitionNext) {
        fwrite(STDERR, "unexpected partition import window\n");
        exit(1);
    }
    echo "application-select-recursive-materialized-window-current-next38 self-test passed\n";
    exit(0);
}

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

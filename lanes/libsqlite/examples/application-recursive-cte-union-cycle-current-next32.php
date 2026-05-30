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
require_once __DIR__ . '/../src/SQLiteNumericAggregateState.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregateState.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTableCursor.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonErrorPosition.php';
require_once __DIR__ . '/../src/SQLiteJsonQuote.php';
require_once __DIR__ . '/../src/SQLiteJsonConstructor.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$edges = [
    ['src' => 1, 'dst' => 2],
    ['src' => 1, 'dst' => 3],
    ['src' => 2, 'dst' => 4],
    ['src' => 3, 'dst' => 5],
    ['src' => 4, 'dst' => 2],
    ['src' => 5, 'dst' => 1],
];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl'],
    ['option_id' => 2, 'option_name' => 'home'],
    ['option_id' => 3, 'option_name' => 'blogname'],
    ['option_id' => 4, 'option_name' => 'active_plugins'],
    ['option_id' => 5, 'option_name' => 'stylesheet'],
];

$sql = <<<'SQL'
WITH RECURSIVE walk(id) AS (
    VALUES (1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 ASC
)
SELECT wp_options.option_name AS option_name
FROM walk JOIN wp_options ON wp_options.option_id = walk.id
ORDER BY walk.id
SQL;

$traceSql = <<<'SQL'
WITH RECURSIVE walk(id) AS (
    VALUES (1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 ASC
)
SELECT id FROM walk
SQL;

$rows = SQLiteSelectSql::execute($sql, ['edges' => $edges, 'wp_options' => $options]);
$trace = SQLiteSelectSql::recursiveCteCycleTrace($traceSql, ['edges' => $edges]);

$summary = [
    'optionNames' => array_column($rows, 'option_name'),
    'visitedIds' => array_column($trace['rows'], 'id'),
    'currentIds' => array_map(static fn (array $entry): mixed => $entry['current']['id'], $trace['trace']),
    'skippedCycleIds' => array_map(static fn (array $entry): mixed => $entry['row']['id'], $trace['skipped']),
    'dependencies' => $trace['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    $expected = [
        'optionNames' => ['siteurl', 'home', 'blogname', 'active_plugins', 'stylesheet'],
        'visitedIds' => [1, 2, 3, 4, 5],
        'currentIds' => [1, 2, 3, 4, 5],
        'skippedCycleIds' => [2, 1],
        'dependencies' => ['sqlite-recursive-cte-current-row', 'sqlite-recursive-union-cycle-dedup'],
    ];
    if ($summary !== $expected) {
        fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(1);
    }

    echo "application-recursive-cte-union-cycle-current-next32 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;

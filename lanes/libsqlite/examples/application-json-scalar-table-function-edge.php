<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonConstructor.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonMutation.php';
require_once __DIR__ . '/../src/SQLiteJsonPatch.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonPretty.php';
require_once __DIR__ . '/../src/SQLiteJsonQuote.php';
require_once __DIR__ . '/../src/SQLiteJsonRemove.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteCoreScalarFunction.php';
require_once __DIR__ . '/../src/SQLiteGroupedAggregate.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregateState.php';
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregateState.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => '{plugin:{enabled:true,modes:["sync","cache",],ttl:300}}',
        'autoload' => 'yes',
    ],
];

$result = SQLiteSelectSql::execute(
    "SELECT j.key AS setting_key, j.atom AS setting_value
     FROM wp_options AS o
     JOIN json_each(json_set(json(o.option_value), '$.plugin.source', o.option_name), '$.plugin') AS j
       ON j.key IN ('source', 'ttl')
     WHERE json_valid(o.option_value, 2) = 1
     ORDER BY j.key",
    ['wp_options' => $rows],
);

$expected = [
    ['setting_key' => 'source', 'setting_value' => 'plugin_settings'],
    ['setting_key' => 'ttl', 'setting_value' => 300],
];

if (in_array('--self-test', $argv, true)) {
    if ($result !== $expected) {
        fwrite(STDERR, "Unexpected JSON scalar table-function result\n");
        var_export($result);
        fwrite(STDERR, "\n");
        exit(1);
    }

    echo "application-json-scalar-table-function-edge self-test passed\n";
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

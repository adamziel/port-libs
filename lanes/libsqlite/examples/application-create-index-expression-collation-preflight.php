<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$indexes = [
    'lowerName' => "CREATE INDEX wp_options_lower_name ON wp_options((lower(option_name)) COLLATE nocase) WHERE option_name IS NOT NULL",
    'jsonCache' => "CREATE INDEX wp_options_json_cache ON wp_options((option_value ->> 'cache') COLLATE rtrim DESC) WHERE option_value IS NOT NULL",
    'prefixName' => "CREATE INDEX wp_options_prefix_name ON wp_options((substr(option_name, 1, 11)) COLLATE nocase DESC) WHERE option_name IS NOT NULL",
    'malformedPath' => "CREATE INDEX wp_options_bad_json ON wp_options((json_extract(option_value, '$.')) COLLATE nocase)",
];

$lower = SQLiteCreateIndex::firstLowerExpression($indexes['lowerName']);
$json = SQLiteCreateIndex::firstJsonTextOperatorExpression($indexes['jsonCache']);
$prefix = SQLiteCreateIndex::firstSubstringExpression($indexes['prefixName']);
$malformed = SQLiteCreateIndex::firstJsonExtractExpression($indexes['malformedPath']);

echo json_encode([
    'scenario' => 'application-create-index-expression-collation-preflight',
    'expressionIndexes' => [
        'lowerName' => [
            'column' => $lower?->columnName,
            'collation' => $lower?->collation,
            'partial' => $lower?->partial,
        ],
        'jsonCache' => [
            'column' => $json?->columnName,
            'path' => $json?->path,
            'collation' => $json?->collation,
            'descending' => $json?->descending,
        ],
        'prefixName' => [
            'column' => $prefix?->columnName,
            'start' => $prefix?->start,
            'length' => $prefix?->length,
            'collation' => $prefix?->collation,
            'descending' => $prefix?->descending,
        ],
    ],
    'malformedPathSkipped' => $malformed === null,
    'applicationUse' => 'Preflight copied wp_options CREATE INDEX expression terms that SQLite stores with redundant parentheses around expression/collation syntax before trusting expression-index root pages.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

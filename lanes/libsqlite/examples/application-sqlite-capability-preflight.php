<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionNames = [
    'ENABLE_FTS5',
    'ENABLE_RTREE',
    'ENABLE_MATH_FUNCTIONS',
    'ENABLE_JSON1',
    'OMIT_JSON',
    'THREADSAFE',
    'DEFAULT_PAGE_SIZE',
];

$used = [];
foreach ($optionNames as $optionName) {
    $used[$optionName] = SQLiteCoreScalarFunction::sqlFunctionArguments('sqlite_compileoption_used', [$optionName]);
}

$compileOptions = [];
for ($index = 0; $index < 6; $index++) {
    $compileOptions[] = SQLiteCoreScalarFunction::sqlFunctionArguments('sqlite_compileoption_get', [$index]);
}

echo json_encode([
    'sqliteVersion' => SQLiteCoreScalarFunction::sqlFunctionArguments('sqlite_version', []),
    'sqliteSourceId' => SQLiteCoreScalarFunction::sqlFunctionArguments('sqlite_source_id', []),
    'compileOptionsPreview' => $compileOptions,
    'capabilityPreflight' => $used,
    'applicationUse' => 'Preview SQLite feature gates that affect copied Application databases, including FTS, RTree, math functions, JSON availability, page-size defaults, and threadsafe metadata, without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

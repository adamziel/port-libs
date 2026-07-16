<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$prefixList = $argv[2] ?? null;
$limit = isset($argv[3]) ? (int) $argv[3] : null;
if ($databasePath === null || $prefixList === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-option-name-prefix-list.php path/to/application.sqlite prefix[,prefix...] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(substr(option_name,1,N)). All prefixes must share length N.\n");
    exit(1);
}

$prefixes = array_values(array_filter(
    array_map(trim(...), explode(',', $prefixList)),
    static fn (string $prefix): bool => $prefix !== '',
));
if ($prefixes === []) {
    fwrite(STDERR, "At least one non-empty prefix is required.\n");
    exit(1);
}

$prefixLength = strlen($prefixes[0]);
foreach ($prefixes as $prefix) {
    if (strlen($prefix) !== $prefixLength) {
        fwrite(STDERR, "All prefixes must share one length for a single substr(option_name,1,N) index.\n");
        exit(1);
    }
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForSubstringInLookup(
    'wp_options',
    'option_name',
    1,
    $prefixLength,
    $prefixes,
);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedNamePrefixes($prefixes, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'prefixes' => $prefixes,
    'prefixLength' => $prefixLength,
    'wpOptionsSubstrOptionNameInListIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

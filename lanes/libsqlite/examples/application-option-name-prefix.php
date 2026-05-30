<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$prefix = $argv[2] ?? '_transient_';
$limit = isset($argv[3]) ? (int) $argv[3] : 100;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-option-name-prefix.php path/to/application.sqlite [option_name_prefix] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(substr(option_name,1,N)).\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForSubstringPointLookup(
    'wp_options',
    'option_name',
    1,
    strlen($prefix),
    $prefix,
);
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRowsByIndexedNamePrefix($prefix, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'prefix' => $prefix,
    'prefixLength' => strlen($prefix),
    'wpOptionsSubstrOptionNameIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

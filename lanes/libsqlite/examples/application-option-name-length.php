<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$length = isset($argv[2]) ? (int) $argv[2] : strlen('home');
$limit = isset($argv[3]) ? (int) $argv[3] : 100;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-option-name-length.php path/to/application.sqlite [option_name_length] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(length(option_name)).\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForLengthPointLookup(
    'wp_options',
    'option_name',
    $length,
);
$options = array_map(
    static fn (SQLiteKeyValueRow $option): array => $option->toArray(),
    $database->keyValueRowsByIndexedNameLength($length, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'optionNameLength' => $length,
    'wpOptionsLengthOptionNameIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

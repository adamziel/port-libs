<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$suffix = $argv[2] ?? '_settings';
$limit = isset($argv[3]) ? (int) $argv[3] : 100;
if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-option-name-suffix.php path/to/application.sqlite [option_name_suffix] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(substr(option_name,-N)).\n");
    exit(1);
}
if ($suffix === '') {
    fwrite(STDERR, "option_name_suffix must be non-empty.\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$suffixLength = function_exists('mb_check_encoding') && function_exists('mb_strlen') && mb_check_encoding($suffix, 'UTF-8')
    ? mb_strlen($suffix, 'UTF-8')
    : strlen($suffix);
$indexRootPage = $database->indexRootPageForSubstringPointLookup(
    'wp_options',
    'option_name',
    -$suffixLength,
    null,
    $suffix,
);
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $database->optionRowsByIndexedNameSuffix($suffix, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'suffix' => $suffix,
    'suffixLength' => $suffixLength,
    'wpOptionsSubstrSuffixOptionNameIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

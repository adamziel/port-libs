<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteWordPressOption;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$lengthList = $argv[2] ?? null;
$limit = isset($argv[3]) ? (int) $argv[3] : null;
if ($databasePath === null || $lengthList === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-option-name-length-list.php path/to/wordpress.sqlite length[,length...] [limit]\n");
    fwrite(STDERR, "Requires an index shaped like CREATE INDEX ... ON wp_options(length(option_name)).\n");
    exit(1);
}

$lengths = [];
foreach (array_map(trim(...), explode(',', $lengthList)) as $length) {
    if ($length === '') {
        continue;
    }
    if (!preg_match('/^\d+$/', $length)) {
        fwrite(STDERR, "Lengths must be non-negative integers.\n");
        exit(1);
    }
    $lengths[] = (int) $length;
}
if ($lengths === []) {
    fwrite(STDERR, "At least one length is required.\n");
    exit(1);
}

$database = SQLiteDatabase::fromFile($databasePath);
$indexRootPage = $database->indexRootPageForLengthInLookup(
    'wp_options',
    'option_name',
    $lengths,
);
$options = array_map(
    static fn (SQLiteWordPressOption $option): array => $option->toArray(),
    $database->wordpressOptionsByIndexedNameLengths($lengths, $limit),
);

echo json_encode([
    'path' => $databasePath,
    'optionNameLengths' => $lengths,
    'wpOptionsLengthOptionNameInListIndexRootPage' => $indexRootPage,
    'limit' => $limit,
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
